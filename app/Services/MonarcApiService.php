<?php

namespace App\Services;

use App\Exceptions\MonarcApiException;
use App\Support\MonarcSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client for the MONARC Front Office API.
 *
 * Routes below were verified by hand against a live MONARC 2.13.3 instance
 * (not guessed from documentation):
 *
 *   POST   /auth                                   {login,password} -> {token,uid,language}
 *          Token is sent back as a `token` header (NOT "Authorization: Bearer")
 *          on every subsequent request. Server-side TTL is ~20 minutes, so a
 *          401 on any call must trigger a single re-authentication + retry
 *          rather than trusting a cached token indefinitely.
 *   DELETE /auth                                   logout (same `token` header)
 *   GET    /api/client-anr                         -> {count, anrs:[{id, uuid, label, ...}]}
 *
 *   POST   /api/client-anr/{anrId}/export           -> full anr export JSON, same flattened
 *          (single-language) shape as tests/fixtures/templates/monarc.json — this is the exact
 *          endpoint that produced that fixture. Used as the single source for knowledgeBase /
 *          scales / operationalRiskScales / method / thresholds / soas, instead of the raw
 *          per-resource endpoints (assets, threats, ... also exist, e.g.
 *          GET /api/client-anr/{anrId}/assets, but return multi-language records
 *          {label1..4, description1..4} that would need to be flattened by hand — the export
 *          endpoint already does this exactly as MONARC's own import/export code expects).
 *
 *          IMPORTANT: the request body keys do NOT match the response's "with*" keys they
 *          control (verified against zm-client's AnrExportService::export() and confirmed live):
 *              body "knowledgeBase"   -> response withKnowledgeBase
 *              body "assetsLibrary"   -> response withLibrary
 *              body "assessments"     -> response withEval
 *              body "methodSteps", "soas", "controls", "recommendations", "interviews", "records"
 *                  -> response withMethodSteps / withSoas / withControls / ... but are only
 *                     honoured when "assessments" is also true, confirmed empirically: with
 *                     assessments=false, "scales", "operationalRiskScales", "soas", "method" and
 *                     "thresholds" ALL come back empty regardless of the other flags. We still
 *                     need those (scales/method/thresholds/soas are un-gated top-level keys with
 *                     no dedicated with* flag of their own in the export shape), so we always
 *                     request assessments=true to harvest them — the "instances" branch of that
 *                     response is simply ignored, since Mercator builds its own instance tree
 *                     from the cartography with withEval=false in ITS generated file.
 *
 * Import validation (not used by this class, documented here for MonarcExportService
 * / manual test-instance validation): the "Importer une analyse" flow is
 * POST /api/client-anr/{anrId}/instances/import, multipart with fields `mode`,
 * `idparent`, and the file as `file[]` (NOT `file` — the FO reads it as a list
 * of uploads; a plain `file` field is silently ignored: HTTP 200, empty
 * `id`/`errors`, nothing imported). NOT /api/client-duplicate-anr (which
 * duplicates an existing in-DB anr and does not accept an uploaded export file).
 * Validated end-to-end on a live 2.13.3 instance for both "library" (flat
 * objects/categories created) and "analysis" (full instance tree + risks,
 * risks-dashboard count matching MonarcExportService::countRisks() exactly)
 * modes produced by MonarcExportService.
 */
class MonarcApiService
{
    private ?string $token = null;

    /** @var array<int, array> per-request in-memory cache, keyed by anr id */
    private array $anrExportCache = [];

    public function __construct(
        private ?string $url = null,
        private ?string $uid = null,
        private ?string $password = null,
    ) {
        $this->url = $this->url ?? MonarcSettings::url();
        $this->uid = $this->uid ?? MonarcSettings::uid();
        $this->password = $this->password ?? MonarcSettings::password();
    }

    /**
     * Authenticates against the Monarc FO and returns the session token,
     * caching it in memory for the lifetime of this instance.
     *
     * @throws MonarcApiException
     */
    public function authenticate(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        if (empty($this->url) || empty($this->uid) || empty($this->password)) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_not_configured'));
        }

        try {
            $response = Http::timeout((int) config('monarc.timeout', 15))
                ->baseUrl(rtrim($this->url, '/'))
                ->post('/auth', [
                    'login' => $this->uid,
                    'password' => $this->password,
                ]);
        } catch (ConnectionException) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_api_unreachable'));
        }

        $token = $response->json('token');

        if ($response->failed() || empty($token)) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_auth_failed'));
        }

        return $this->token = $token;
    }

    /**
     * Verifies the configured connection by authenticating.
     *
     * @return array{0: string, 1: bool} [message, success]
     */
    public function testConnection(): array
    {
        try {
            $this->authenticate();

            return [trans('cruds.configuration.monarc.test_connection_success'), true];
        } catch (MonarcApiException $e) {
            return [$e->getMessage(), false];
        }
    }

    /**
     * Lists the analyses (ANRs) available on the Monarc FO, for the
     * "modèle d'analyse" select. Short-lived cache: this list rarely
     * changes within the span of a single export session.
     *
     * @return array<int, array> each entry has at least id, uuid, label, description
     *
     * @throws MonarcApiException
     */
    public function getAnrs(): array
    {
        $ttl = (int) config('monarc.cache_ttl', 300);

        return Cache::remember('monarc:anrs:'.$this->cacheIdentity(), $ttl, function () {
            return $this->getJson('/api/client-anr')['anrs'] ?? [];
        });
    }

    /**
     * @throws MonarcApiException
     */
    public function getKnowledgeBase(int $anrId): array
    {
        return $this->fetchAnrExport($anrId)['knowledgeBase'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getAssets(int $anrId): array
    {
        return $this->getKnowledgeBase($anrId)['assets'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getThreats(int $anrId): array
    {
        return $this->getKnowledgeBase($anrId)['threats'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getVulnerabilities(int $anrId): array
    {
        return $this->getKnowledgeBase($anrId)['vulnerabilities'] ?? [];
    }

    /**
     * The AMV (asset/threat/vulnerability) links, called "informationRisks"
     * in the export/import format.
     *
     * @throws MonarcApiException
     */
    public function getAmvs(int $anrId): array
    {
        return $this->getKnowledgeBase($anrId)['informationRisks'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getReferentials(int $anrId): array
    {
        return $this->getKnowledgeBase($anrId)['referentials'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getRolfTags(int $anrId): array
    {
        return $this->getKnowledgeBase($anrId)['rolfTags'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getOperationalRisks(int $anrId): array
    {
        return $this->getKnowledgeBase($anrId)['operationalRisks'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getScales(int $anrId): array
    {
        return $this->fetchAnrExport($anrId)['scales'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getOperationalRiskScales(int $anrId): array
    {
        return $this->fetchAnrExport($anrId)['operationalRiskScales'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getMethod(int $anrId): array
    {
        return $this->fetchAnrExport($anrId)['method'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getThresholds(int $anrId): array
    {
        return $this->fetchAnrExport($anrId)['thresholds'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getSoas(int $anrId): array
    {
        return $this->fetchAnrExport($anrId)['soas'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getSoaScaleComments(int $anrId): array
    {
        return $this->fetchAnrExport($anrId)['soaScaleComments'] ?? [];
    }

    /** @throws MonarcApiException */
    public function getMonarcVersion(int $anrId): ?string
    {
        return $this->fetchAnrExport($anrId)['monarc_version'] ?? null;
    }

    /** @throws MonarcApiException */
    public function getLanguageCode(int $anrId): ?string
    {
        return $this->fetchAnrExport($anrId)['languageCode'] ?? null;
    }

    /**
     * Fetches (and caches, in-memory then in the Laravel cache) the full
     * "anr" export of the given analysis: the same payload shape as
     * tests/fixtures/templates/monarc.json, minus the instance tree and
     * the library (we don't need Monarc's own object library — Mercator
     * builds its own from the cartography).
     *
     * @throws MonarcApiException
     */
    private function fetchAnrExport(int $anrId): array
    {
        if (isset($this->anrExportCache[$anrId])) {
            return $this->anrExportCache[$anrId];
        }

        $ttl = (int) config('monarc.cache_ttl', 300);

        $data = Cache::remember(
            "monarc:anr-export:{$anrId}:".$this->cacheIdentity(),
            $ttl,
            function () use ($anrId) {
                return $this->postJson("/api/client-anr/{$anrId}/export", [
                    'knowledgeBase' => true,
                    'assetsLibrary' => false,
                    // Required to get scales/method/thresholds/soas populated (see class docblock);
                    // has no bearing on Mercator's OWN generated file, which always sets withEval=false.
                    'assessments' => true,
                    'methodSteps' => true,
                    'soas' => true,
                ]);
            }
        );

        return $this->anrExportCache[$anrId] = $data;
    }

    /**
     * Issues an authenticated GET, re-authenticating once and retrying on a
     * 401 (token expired server-side after ~20 minutes).
     *
     * @throws MonarcApiException
     */
    private function getJson(string $path): array
    {
        return $this->withAuthRetry(fn (PendingRequest $request) => $request->get($path));
    }

    /**
     * Issues an authenticated POST, with the same 401 retry as getJson().
     *
     * @throws MonarcApiException
     */
    private function postJson(string $path, array $body): array
    {
        return $this->withAuthRetry(fn (PendingRequest $request) => $request->post($path, $body));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws MonarcApiException
     */
    private function withAuthRetry(callable $call): array
    {
        $token = $this->authenticate();

        try {
            $response = $call($this->request($token));
        } catch (ConnectionException) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_api_unreachable'));
        }

        if ($response->status() === 401) {
            $this->token = null;
            $token = $this->authenticate();

            try {
                $response = $call($this->request($token));
            } catch (ConnectionException) {
                throw new MonarcApiException(trans('cruds.configuration.monarc.error_api_unreachable'));
            }
        }

        if ($response->failed()) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_api_unreachable'));
        }

        return $response->json() ?? [];
    }

    private function request(string $token): PendingRequest
    {
        return Http::timeout((int) config('monarc.timeout', 15))
            ->baseUrl(rtrim((string) $this->url, '/'))
            ->withHeaders(['token' => $token]);
    }

    /**
     * Distinguishes cache entries per configured target (url+uid), so
     * switching Monarc instances doesn't serve stale cached data.
     */
    private function cacheIdentity(): string
    {
        return md5(($this->url ?? '').'|'.($this->uid ?? ''));
    }
}
