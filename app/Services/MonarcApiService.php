<?php

namespace App\Services;

use App\Exceptions\MonarcApiException;
use App\Support\MonarcSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
 * Import: the "Importer une analyse" flow is
 * POST /api/client-anr/{anrId}/instances/import, multipart with fields `mode`,
 * `idparent`, and the file as `file[]` (NOT `file` — the FO reads it as a list
 * of uploads; a plain `file` field is silently ignored: HTTP 200, empty
 * `id`/`errors`, nothing imported). NOT /api/client-duplicate-anr (which
 * duplicates an existing in-DB anr and does not accept an uploaded export file).
 * Validated end-to-end on a live 2.13.3 instance for both "library" (flat
 * objects/categories created) and "analysis" (full instance tree + risks,
 * risks-dashboard count matching MonarcExportService::countRisks() exactly)
 * modes produced by MonarcExportService. The import always ADDS to the
 * existing ANR tree — objects in the imported knowledgeBase/library are
 * matched (and merged, never duplicated) by uuid, but instances are always
 * appended: re-importing the same file twice duplicates every instance. This
 * is exactly why MonarcSyncService only ever feeds this method the "new
 * objects" diff, never a full re-export.
 *
 * ANR lifecycle, also verified live:
 *   GET    /api/models                              -> {count, models:[{id,label,...}]}
 *   POST   /api/client-anr        {model,language,label} -> {status:"ok", id}
 *   DELETE /api/client-anr/{id}                      deletes the ANR (used by tests/manual cleanup)
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
     * "link to an existing ANR" combobox on the synchronization screen.
     * Not cached — see the method body — the label is always resolved fresh
     * for the requested language, same multi-language label1..label4
     * convention as getModels().
     *
     * @return array<int, array{id: int, label: string}>
     *
     * @throws MonarcApiException
     */
    public function getAnrs(string $languageCode = 'fr'): array
    {
        // Deliberately NOT cached (unlike the rest of this class): this list
        // feeds the "link to an existing ANR" combobox, so a deleted ANR
        // must stop appearing the moment the admin reloads the sync screen,
        // not up to config('monarc.cache_ttl') later. anrExists() already
        // made this same choice for the same reason.
        $raw = $this->getJson('/api/client-anr')['anrs'] ?? [];

        $languageIndex = $languageCode === 'en' ? 2 : 1;

        return array_map(fn (array $anr) => [
            'id' => (int) $anr['id'],
            'label' => $this->resolveMultiLanguageLabel($anr, $languageIndex),
        ], $raw);
    }

    /**
     * Lists the models available for creating a new ANR, for the "analysis
     * model" select on the synchronization screen. Each entry's label is
     * resolved for the given language — Monarc models, like other Monarc
     * records (see the class docblock's note on /assets), carry one label
     * per instance language slot (label1..label4) rather than a single flat
     * "label" field, so a naive read of "label" silently falls through to
     * showing the raw numeric id instead of a name.
     *
     * @return array<int, array{id: int, label: string}>
     *
     * @throws MonarcApiException
     */
    public function getModels(string $languageCode = 'fr'): array
    {
        $body = $this->getJson('/api/models');

        $raw = $body['models'] ?? $body['data'] ?? (array_is_list($body) ? $body : []);

        if ($raw === [] && $body !== []) {
            // The envelope shape ({models:[...]} vs a bare list vs something
            // else entirely) was never confirmed against a live instance for
            // this endpoint — log the raw keys so a mismatch is diagnosable
            // instead of silently leaving the sync screen's model select empty.
            Log::warning('Monarc getModels(): unrecognized response shape, no models extracted.', [
                'keys' => array_is_list($body) ? 'list' : array_keys($body),
            ]);

            return [];
        }

        $languageIndex = $languageCode === 'en' ? 2 : 1;

        return array_map(fn (array $item) => [
            'id' => (int) $item['id'],
            'label' => $this->resolveMultiLanguageLabel($item, $languageIndex),
        ], $raw);
    }

    /**
     * Picks the model's label for the given language slot, falling back
     * through the other slots, then a flat "label"/"name", before ever
     * resorting to the raw id — see getModels().
     */
    private function resolveMultiLanguageLabel(array $item, int $languageIndex): string
    {
        if (! empty($item['label'.$languageIndex])) {
            return (string) $item['label'.$languageIndex];
        }

        foreach ([1, 2, 3, 4] as $index) {
            if (! empty($item['label'.$index])) {
                return (string) $item['label'.$index];
            }
        }

        if (! empty($item['label'])) {
            return (string) $item['label'];
        }

        if (! empty($item['name'])) {
            return (string) $item['name'];
        }

        Log::warning('Monarc getModels(): no label field found for a model, falling back to its id.', [
            'keys' => array_keys($item),
        ]);

        return 'Model #'.($item['id'] ?? '?');
    }

    /**
     * Creates a new ANR (risk analysis) in Monarc from the given model, and
     * returns its id.
     *
     * @throws MonarcApiException
     */
    public function createAnr(int $modelId, string $label, int $language = 1): int
    {
        $body = $this->postJson('/api/client-anr', [
            'model' => $modelId,
            'language' => $language,
            'label' => $label,
        ]);

        $id = $body['id'] ?? null;

        if ($id === null) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_api_unreachable'));
        }

        return (int) $id;
    }

    /**
     * Whether the given ANR still exists on the Monarc instance — always a
     * fresh, uncached lookup (never the getAnrs() cache), since this is used
     * right before a sync to catch an ANR deleted directly in Monarc.
     *
     * @throws MonarcApiException
     */
    public function anrExists(int $anrId): bool
    {
        $anrs = $this->getJson('/api/client-anr')['anrs'] ?? [];

        return collect($anrs)->contains(fn (array $anr) => (int) ($anr['id'] ?? 0) === $anrId);
    }

    /**
     * Imports an export JSON (as produced by MonarcExportService) into the
     * given ANR. The import always ADDS to the existing tree — see the class
     * docblock — so callers must never re-send an object already imported.
     *
     * @return array the FO's response body (e.g. {id:[...], errors:[...]})
     *
     * @throws MonarcApiException
     */
    public function importInstances(int $anrId, string $exportJson, int $mode = 1, int $idParent = 0): array
    {
        return $this->withAuthRetry(fn (PendingRequest $request) => $request
            ->attach('file[]', $exportJson, 'export.json')
            ->post("/api/client-anr/{$anrId}/instances/import", [
                'mode' => $mode,
                'idparent' => $idParent,
            ]));
    }

    /**
     * Deletes an ANR. Not used by the synchronization workflow itself
     * (a "reset link" only forgets the local link, it never deletes the
     * remote ANR) — kept for tests and manual cleanup.
     *
     * @throws MonarcApiException
     */
    public function deleteAnr(int $anrId): void
    {
        $this->withAuthRetry(fn (PendingRequest $request) => $request->delete("/api/client-anr/{$anrId}"));
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
