<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LdapRecord\Auth\BindException;
use LdapRecord\Container;
use LdapRecord\DetailedError;
use LdapRecord\Models\Entry as LdapEntry;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected string $redirectTo = '/home';

    /**
     * Sous-codes Active Directory renvoyés dans le message de diagnostic d'un bind refusé
     * (ex. "80090308: LdapErr: DSID-0C09044E, comment: AcceptSecurityContext error, data 775, v4563").
     */
    private const AD_BIND_SUBCODES = [
        '525' => 'user not found',
        '52e' => 'invalid credentials',
        '530' => 'logon not permitted at this time',
        '531' => 'logon not permitted from this workstation',
        '532' => 'password expired',
        '533' => 'account disabled',
        '568' => 'too many security IDs (token size)',
        '701' => 'account expired',
        '773' => 'user must reset password',
        '775' => 'account locked out',
    ];

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    // ✅ Le framework utilisera toujours ce champ
    public function username(): string
    {
        return 'login';
    }

    /**
     * Hook appelé APRES un login réussi (LDAP ou local).
     *
     * Ici on :
     *  - eager-load les rôles / permissions pour éviter le N+1 dans la requête
     *  - stocke l'utilisateur enrichi en session pour un éventuel middleware
     */
    protected function authenticated(Request $request, User $user): void
    {
        $permissionData = $user->sessionPermissionData();
        session($permissionData);

        $context = [
            'user_id' => $user->id,
            'roles' => $permissionData['auth_role_ids'],
            'permissions' => count($permissionData['auth_permissions']),
        ];
        if ($permissionData['auth_role_ids'] === []) {
            // Connexion acceptée mais toutes les pages répondront 403 : ressenti comme un échec.
            $this->authLog('warning', 'Login succeeded but user has no role.', $request, $context);
        } else {
            $this->authLog('info', 'Login succeeded.', $request, $context);
        }

        AuditLog::query()->create([
            'description' => 'Login',
            'subject_id' => $user->id,
            'subject_type' => User::class,
            'user_id' => $user->id,
            'properties' => [
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ],
            'host' => $request->ip(),
        ]);
    }

    /**
     * LDAP bind (LDAPRecord v2)
     */
    protected function ldapBindAndGetUser(string $appUsername, string $password): ?LdapEntry
    {
        if ($appUsername === '' || $password === '') {
            Log::debug('LDAP skipped: empty identifier or password.');

            return null;
        }

        try {
            $query = LdapEntry::query();

            // Optionnel : restreindre à une OU si configuré
            $base = trim((string) config('app.ldap_users_base_dn'));
            if ($base !== '') {
                $query->in($base);
            }

            // Attributs de login à tester côté LDAP (uid, sAMAccountName, etc.)
            $attrs = array_values(array_filter(array_map('trim', explode(',', (string) config('app.ldap_login_attributes')))));
            if (empty($attrs)) {
                Log::warning('LDAP login aborted: app.ldap_login_attributes is empty.');

                return null;
            }

            // Filtre OR sur les attributs de login injecté via rawFilter().
            $escaped = ldap_escape($appUsername, '', LDAP_ESCAPE_FILTER);
            $attrFilters = array_map(fn (string $attr) => "({$attr}={$escaped})", $attrs);
            $loginFilter = count($attrFilters) === 1
                ? $attrFilters[0]
                : '(|'.implode('', $attrFilters).')';
            $query->rawFilter($loginFilter);

            // Filtre de groupe (ajouté après la closure de login pour préserver la priorité)
            $group = trim((string) config('app.ldap_group'));
            $useNested = (bool) config('app.ldap_nested_groups');

            if ($group !== '') {
                if ($useNested) {
                    // whereRaw() ne supporte pas la syntaxe OID de la matching rule AD.
                    // rawFilter() injecte directement le filtre LDAP sans interprétation.
                    // Génère : (memberOf:1.2.840.113556.1.4.1941:=<GROUP_DN>)
                    $query->rawFilter("(memberOf:1.2.840.113556.1.4.1941:={$group})");
                } else {
                    // Appartenance directe au groupe (non récursive)
                    $query->whereEquals('memberOf', $group);
                }
            }

            $searchContext = [
                'identifier' => $appUsername,
                'base_dn' => $base !== '' ? $base : config('ldap.connections.'.config('ldap.default').'.base_dn'),
                'filter' => $loginFilter,
                'group' => $group !== '' ? $group : null,
                'nested' => $useNested,
            ];

            // Collision guard
            $results = $query->limit(2)->get();
            if ($results->count() === 0) {
                // Utilisateur absent de la base de recherche, ou hors du groupe LDAP_GROUP.
                Log::warning('LDAP user not found (or not member of LDAP_GROUP).', $searchContext);

                return null;
            }
            if ($results->count() > 1) {
                // Aucun filtre objectClass : un contact, un groupe ou un second compte partageant
                // le même cn/mail/uid suffit à provoquer la collision.
                Log::warning('LDAP identifier collision: multiple entries match.', $searchContext + [
                    'dns' => $results->map(fn (LdapEntry $e) => $e->getDn())->all(),
                ]);

                return null;
            }

            /** @var LdapEntry $ldapUser */
            $ldapUser = $results->first();

            $connection = Container::getConnection();
            $dn = $ldapUser->getDn();

            if (! $dn) {
                Log::warning('LDAP entry found without DN.', $searchContext);

                return null;
            }

            // attempt() avale la BindException : on relit l'erreur détaillée sur la connexion
            // (toujours disponible car stayBound=true évite le re-bind du compte de service).
            if ($connection->auth()->attempt($dn, $password, true)) {
                Log::debug('LDAP bind succeeded.', ['identifier' => $appUsername, 'dn' => $dn]);

                return $ldapUser;
            }

            $ldap = $connection->getLdapConnection();
            Log::warning('LDAP bind refused for user.', [
                'identifier' => $appUsername,
                'dn' => $dn,
                'host' => $ldap->getHost(),
            ] + $this->describeLdapError($ldap->getDetailedError()));

            return null;
        } catch (BindException $e) {
            // Levée hors de attempt() : typiquement le bind du compte de service (LDAP_USERNAME)
            // ou la connexion au serveur (injoignable, certificat TLS invalide/expiré).
            Log::error('LDAP service bind / connection failed.', [
                'identifier' => $appUsername,
                'host' => config('ldap.connections.'.config('ldap.default').'.hosts'),
                'error' => $e->getMessage(),
            ] + $this->describeLdapError($e->getDetailedError()));

            return null;
        } catch (\Throwable $e) {
            Log::error('LDAP error: '.$e->getMessage(), [
                'identifier' => $appUsername,
                'exception' => get_class($e),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Login avec LDAP optionnel + fallback local (UNIQUEMENT via 'login').
     */
    protected function attemptLogin(Request $request): bool
    {
        $useLdap = (bool) config('ldap.enabled');
        $fallbackLocal = (bool) config('app.ldap_fallback_local');
        $autoProvision = (bool) config('app.ldap_auto_provision');

        $credentials = $request->only($this->username(), 'password'); // ['login' => ..., 'password' => ...]
        $identifier = (string) ($credentials[$this->username()] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $remember = $request->boolean('remember');

        $this->authLog('debug', 'Login attempt.', $request, [
            'ldap' => $useLdap,
            'fallback_local' => $fallbackLocal,
            'auto_provision' => $autoProvision,
        ]);

        if ($useLdap) {
            $ldapUser = $this->ldapBindAndGetUser($identifier, $password);

            if ($ldapUser) {
                // Mapping local UNIQUEMENT par 'login'
                $local = User::where('login', $identifier)->first();

                if (! $local) {
                    // Aide au diagnostic : un compte local existe-t-il sous un autre identifiant
                    // (ex. saisie de l'e-mail alors que le login local est le sAMAccountName) ?
                    $mail = $ldapUser->getFirstAttribute('mail');
                    $this->authLog('info', 'LDAP OK but no local user with this login.', $request, [
                        'ldap_dn' => $ldapUser->getDn(),
                        'ldap_mail' => $mail,
                        'local_user_by_email' => $mail ? User::where('email', $mail)->value('login') : null,
                        'auto_provision' => $autoProvision,
                    ]);
                }

                if (! $local && $autoProvision) {
                    $local = User::create([
                        'name' => $ldapUser->getFirstAttribute('cn') ?: $identifier,
                        'email' => $ldapUser->getFirstAttribute('mail') ?: 'user@localhost.local',
                        'login' => $identifier,
                        'password' => Hash::make(Str::random(32)), // inutilisable en local par défaut
                    ]);

                    // Assignation du rôle par défaut
                    // filled() rejette null ET les chaînes vides (ex: LDAP_AUTO_PROVISION_ROLE='')
                    $roleName = config('app.ldap_auto_provision_role');

                    $this->authLog('info', 'LDAP auto-provision: local user created.', $request, ['user_id' => $local->id]);

                    if (filled($roleName)) {
                        // first() au lieu de firstOrFail() : évite le crash 404 si le rôle
                        // est absent ou mal orthographié (la valeur est case-sensitive).
                        $role = Role::query()->where('title', $roleName)->first();

                        if ($role !== null) {
                            try {
                                $local->roles()->sync([$role->id]);
                                Log::info('LDAP auto-provision: role assigned.', [
                                    'user' => $identifier,
                                    'role' => $roleName,
                                ]);
                            } catch (\Throwable $e) {
                                // L'utilisateur est déjà persisté : on isole l'échec de
                                // l'assignation pour ne pas bloquer la connexion.
                                Log::error('LDAP auto-provision: failed to assign role.', [
                                    'user' => $identifier,
                                    'role' => $roleName,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        } else {
                            // Rôle introuvable → log d'avertissement sans crash.
                            // Vérifier que LDAP_AUTO_PROVISION_ROLE correspond exactement
                            // au champ `title` en base (sensible à la casse).
                            Log::warning('LDAP auto-provision: role not found — user created without role.', [
                                'user' => $identifier,
                                'role' => $roleName,
                            ]);
                        }
                    }
                }

                if ($local) {
                    // 🔐 Login manuel → AuthenticatesUsers appellera ensuite sendLoginResponse()
                    // qui déclenchera le hook authenticated()
                    $this->guard()->login($local, $remember);

                    return true;
                }

                // LDAP OK mais pas d'utilisateur local et pas d'auto-provision
                $this->authLog('warning', 'Login refused: LDAP OK but no local user and auto-provision disabled.', $request);

                return false;
            }

            // LDAP KO → éventuel fallback local (toujours via 'login')
            if (! $fallbackLocal) {
                $this->authLog('warning', 'Login refused: LDAP failed and local fallback disabled.', $request);

                return false;
            }
        }

        // Auth locale (Laravel) — utilisera ['login' => ..., 'password' => ...]
        $ok = $this->guard()->attempt(
            $this->credentials($request), // credentials() retournera login + password car username() = 'login'
            $remember
        );

        if (! $ok) {
            $local = User::where('login', $identifier)->first(['id', 'deleted_at']);
            $this->authLog('warning', 'Login refused: local authentication failed.', $request, [
                'after_ldap' => $useLdap,
                'local_user_exists' => $local !== null,
                'reason' => $local === null ? 'unknown login' : 'wrong local password',
            ]);
        }

        return $ok;
    }

    /**
     * Trace les verrouillages temporaires (trop de tentatives pour ce login depuis cette IP).
     *
     * @throws ValidationException
     */
    protected function sendLockoutResponse(Request $request): never
    {
        $seconds = $this->limiter()->availableIn($this->throttleKey($request));

        $this->authLog('warning', 'Login locked out: too many attempts.', $request, [
            'retry_after_seconds' => $seconds,
        ]);

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * Log de connexion avec le contexte commun (identifiant saisi, IP) — jamais le mot de passe.
     *
     * @param  array<string, mixed>  $context
     */
    private function authLog(string $level, string $message, Request $request, array $context = []): void
    {
        Log::log($level, '[auth] '.$message, [
            'identifier' => (string) $request->input($this->username()),
            'ip' => $request->ip(),
        ] + $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function describeLdapError(?DetailedError $error): array
    {
        if ($error === null) {
            return [];
        }

        $diagnostic = $error->getDiagnosticMessage();
        $adCode = null;
        if ($diagnostic && preg_match('/data ([0-9a-f]{3,4})/i', $diagnostic, $m)) {
            $adCode = strtolower($m[1]);
        }

        return [
            'ldap_code' => $error->getErrorCode(),
            'ldap_error' => $error->getErrorMessage(),
            'diagnostic' => $diagnostic,
            'ad_reason' => $adCode !== null ? (self::AD_BIND_SUBCODES[$adCode] ?? 'AD code '.$adCode) : null,
        ];
    }

    public function logout(Request $request): RedirectResponse|Response
    {
        $userId = auth()->id();

        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        try {
            AuditLog::query()->create([
                'description' => 'Logout',
                'subject_id' => $userId,
                'subject_type' => User::class,
                'user_id' => $userId,
                'properties' => [
                    'user_agent' => $request->userAgent(),
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                ],
                'host' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create logout audit log', ['error' => $e->getMessage()]);
        }

        return $this->loggedOut($request) ?: redirect('/');
    }
}
