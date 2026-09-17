<?php

namespace App\Models;

use App\Contracts\HasIconContract;
use App\Factories\UserFactory;
use App\Traits\HasIcon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements HasIconContract, OAuthenticatable
{
    use HasApiTokens, HasFactory, HasIcon, Notifiable, SoftDeletes;

    protected $table = 'users';

    public static string $icon = '/images/actor.png';

    protected $hidden = [
        'remember_token',
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'login',
        'name',
        'email',
        'password',
        'granularity',
        'language',
        'flow_label',
    ];

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    /**
     * Mutator mot de passe : hash seulement si nécessaire.
     */
    protected function password(): Attribute
    {
        return Attribute::set(function (?string $value) {
            if ($value === null || $value === '') {
                return null;
            }

            return Hash::needsRehash($value) ? Hash::make($value) : $value;
        });
    }

    /**
     * Relation rôles
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Ensemble distinct des `perimeter_id` de ses rôles.
     *
     * @return array<int, int>
     */
    public function perimeterIds(): array
    {
        return $this->roles()->pluck('perimeter_id')->unique()->values()->all();
    }

    /**
     * Vrai si l'utilisateur est responsable d'au moins 2 périmètres distincts.
     * Utilisé par le sélecteur de périmètre actif et les colonnes de liste
     * (incréments suivants).
     */
    public function hasMultiplePerimeters(): bool
    {
        return count($this->perimeterIds()) >= 2;
    }

    /**
     * Périmètre de travail courant, mémorisé en session par
     * EnsureActivePerimeter/PerimeterActiveController. `Perimeter::ALL_ID`
     * (0) signifie « tous mes périmètres » (aucun filtre).
     */
    public function activePerimeterId(): int
    {
        return (int) session('active_perimeter', Perimeter::ALL_ID);
    }

    /**
     * Périmètre par défaut pour un nouvel objet : le périmètre actif s'il en
     * est un réel (pas « tous »), sinon le premier périmètre de l'utilisateur,
     * sinon le périmètre par défaut. Utilisé par le sélecteur de création et
     * PerimeterAssignmentObserver.
     */
    public function activeOrDefaultPerimeterId(): int
    {
        $active = $this->activePerimeterId();
        if ($active !== Perimeter::ALL_ID && in_array($active, $this->perimeterIds(), true)) {
            return $active;
        }

        return $this->perimeterIds()[0] ?? Perimeter::DEFAULT_ID;
    }

    private ?bool $isAdminCache = null;

    /**
     * L'utilisateur es-til administrateur ?
     */
    public function isAdmin(): bool
    {
        if ($this->isAdminCache === null) {
            $this->isAdminCache = $this->relationLoaded('roles')
                ? $this->roles->contains('id', 1)
                : $this->roles()->whereKey(1)->exists();
        }

        return $this->isAdminCache;
    }

    /**
     * Ajouter un rôle (utilise attach sur pivot).
     */
    public function addRole(Role $role): void
    {
        if (! $this->hasRole($role)) {
            $this->roles()->attach($role->getKey());
            // Optionnel : tenir le cache en mémoire si déjà chargé :
            if ($this->relationLoaded('roles')) {
                $this->setRelation('roles', $this->getRelation('roles')->push($role));
            }
        }
    }

    /**
     * Vérifie si l'utilisateur possède un rôle.
     *
     * @param  string|Role  $role  Titre/slug OU instance Role.
     */
    public function hasRole(string|Role $role): bool
    {
        // Zéro requête si déjà eager-loaded
        if ($this->relationLoaded('roles')) {
            $roles = $this->getRelation('roles'); // Collection<Role>

            return $role instanceof Role
                ? $roles->contains('id', $role->getKey())
                : ($roles->contains('slug', $role) || $roles->contains('title', $role));
        }

        // Requête minimale
        if ($role instanceof Role) {
            return $this->roles()->whereKey($role->getKey())->exists();
        }

        // Privilégie 'slug' si disponible
        return $this->roles()
            ->where(fn ($q) => $q->where('slug', $role)->orWhere('title', $role))
            ->exists();
    }

    public function cartographerEntries(): HasMany
    {
        return $this->hasMany(Cartographer::class, 'user_id');
    }

    public function isCartographerOf(Model $object): bool
    {
        return Cartographer::isAllowed($this, $object);
    }
}
