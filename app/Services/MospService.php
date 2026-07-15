<?php

namespace App\Services;

use App\Exceptions\MonarcApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client for the public MOSP (MONARC Objects Sharing Platform) API,
 * https://objects.monarc.lu — read-only, no authentication.
 *
 * Verified against the live API and its own published JSON Schema
 * definitions (not guessed):
 *
 *   GET /api/v2/object/?schema=<name>&language=<FR|EN|...>&page=N&per_page=M
 *       -> {metadata:{count,offset,limit}, data:[{id,name,description,
 *          schema_id,org_id,organization:{id,name,...},json_object:{...}}]}
 *   GET /api/v2/object/{id}
 *       -> a single-element JSON array: [{...same shape as one list item...}]
 *          (NOT a bare object — a common trap).
 *
 * The knowledge base is sourced from MOSP's flat, individually-published
 * base catalog — NOT from "Library objects" (schema 21), which only has a
 * couple of sparse example object trees. The real, production-scale catalog
 * lives in these schemas (confirmed live: FR counts match
 * tests/fixtures/templates/monarc.json's knowledgeBase exactly — 42 assets,
 * 30 threats, 706 vulnerabilities — same uuids too):
 *   "Assets"          (schema id 1)  : {uuid, code, label, description, type: "Primary"|"Secondary", language, version}
 *   "Threats"         (schema id 15) : {uuid, code, label, description, theme (plain string), c, i, a (bool), language}
 *   "Vulnerabilities" (schema id 14) : {uuid, code, label, description, language}
 *   "Risks"           (schema id 16) : {uuid, asset(uuid), threat(uuid), vulnerability(uuid), measures:[uuid,...]}
 *       — this IS the AMV list (1938 entries, matching the fixture exactly),
 *         language-independent (asset/threat/vulnerability are referenced by
 *         uuid, not embedded).
 *   "Security referentials" (schema id 12) : {uuid, label, values:[{uuid, code, label, category, referential, referential_label}]}
 *       — measure catalogs (ISO 27002, ISO 27017, ...), the only thing the
 *         user actually picks from MOSP; NOT threats/AMVs.
 *
 * NOTE: asset "type" is "Primary"/"Secondary" — the original task spec
 * guessed "Support", which is wrong; always trust the schema over assumptions.
 */
class MospService
{
    private const PER_PAGE = 100;

    /**
     * The base catalog (Assets/Threats/Vulnerabilities/Risks) is a public,
     * community-maintained reference that changes rarely — cache it far
     * longer than the live-ANR TTL (config('monarc.cache_ttl')) to avoid a
     * ~20-page pagination sweep (1938 Risks) on every page load.
     */
    private const BASE_CATALOG_TTL = 86400;

    public function __construct(private ?string $base = null)
    {
        $this->base = $this->base ?? config('monarc.mosp_base', 'https://objects.monarc.lu/api/v2/object');
    }

    /**
     * @return array<int, array> raw MOSP "Assets" objects, one per unique uuid, in the given language when available
     *
     * @throws MonarcApiException
     */
    public function getBaseAssets(string $languageCode): array
    {
        return $this->fetchPreferredLanguage('Assets', $this->mospLanguage($languageCode));
    }

    /**
     * @return array<int, array> raw MOSP "Threats" objects, one per unique uuid, in the given language when available
     *
     * @throws MonarcApiException
     */
    public function getBaseThreats(string $languageCode): array
    {
        return $this->fetchPreferredLanguage('Threats', $this->mospLanguage($languageCode));
    }

    /**
     * @return array<int, array> raw MOSP "Vulnerabilities" objects, one per unique uuid, in the given language when available
     *
     * @throws MonarcApiException
     */
    public function getBaseVulnerabilities(string $languageCode): array
    {
        return $this->fetchPreferredLanguage('Vulnerabilities', $this->mospLanguage($languageCode));
    }

    /**
     * @return array<int, array> raw MOSP "Risks" objects (the AMV catalog) — language-independent
     *
     * @throws MonarcApiException
     */
    public function getBaseRisks(): array
    {
        return $this->fetchAllPaginated('Risks', null);
    }

    /**
     * Lists all published "Security referentials" (measure catalogs, e.g. ISO
     * 27002, ISO 27017) — the only thing a MOSP-sourced export actually asks
     * the user to pick.
     *
     * @return array<int, array{id: int, name: string, organization: string}>
     *
     * @throws MonarcApiException
     */
    public function getReferentials(): array
    {
        $ttl = (int) config('monarc.cache_ttl', 300);

        return Cache::remember('mosp:referentials', $ttl, function () {
            $referentials = [];

            foreach ($this->paginate('Security referentials', null) as $item) {
                $referentials[] = [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'organization' => $item['organization']['name'] ?? '',
                ];
            }

            return $referentials;
        });
    }

    /**
     * Full referential data (uuid, label, values: the measure list) for one
     * selected "Security referentials" object.
     *
     * @throws MonarcApiException
     */
    public function getReferentialData(int $mospId): array
    {
        $ttl = (int) config('monarc.cache_ttl', 300);

        return Cache::remember("mosp:referential:{$mospId}", $ttl, function () use ($mospId) {
            $body = $this->get("/{$mospId}");
            $item = $body[0] ?? null;

            if ($item === null) {
                throw new MonarcApiException(trans('cruds.configuration.monarc.error_mosp_unreachable'));
            }

            return $item['json_object'] ?? [];
        });
    }

    /**
     * Fully paginates a MOSP object schema collection, cached as a whole.
     *
     * @return array<int, array>
     *
     * @throws MonarcApiException
     */
    private function fetchAllPaginated(string $schema, ?string $language): array
    {
        $cacheKey = 'mosp:catalog:'.$schema.':'.($language ?? 'any');

        return Cache::remember($cacheKey, self::BASE_CATALOG_TTL, function () use ($schema, $language) {
            $items = [];
            foreach ($this->paginate($schema, $language) as $item) {
                $items[] = $item['json_object'];
            }

            return $items;
        });
    }

    /**
     * Fetches a language-bearing schema (Assets/Threats/Vulnerabilities)
     * WITHOUT the API's own `language` query filter, then picks the
     * best-language variant per unique uuid: the requested language if
     * present, else English, else whichever variant is encountered first —
     * so a label is never shown in an arbitrary third language the user
     * didn't ask for (e.g. DE/IT/ES) as long as either the requested
     * language or an English variant exists.
     *
     * Verified live against objects.monarc.lu: querying with `language=FR`
     * directly is unreliable — e.g. "Assets" reports metadata.count=42 for
     * language=FR, but the 42 rows returned only cover 27 distinct uuids
     * (some duplicated 2-3x), silently dropping 15 real base assets that
     * exist in other languages. Fetching unfiltered (168 rows across all
     * languages, count matches 42 uuids × 4 languages) and choosing the
     * wanted language ourselves recovers the full base set every time.
     *
     * @return array<int, array> one entry per unique uuid
     *
     * @throws MonarcApiException
     */
    private function fetchPreferredLanguage(string $schema, string $preferredLanguage): array
    {
        $cacheKey = 'mosp:catalog:'.$schema.':preferred:'.$preferredLanguage;

        return Cache::remember($cacheKey, self::BASE_CATALOG_TTL, function () use ($schema, $preferredLanguage) {
            $byUuid = [];

            foreach ($this->paginate($schema, null) as $item) {
                $object = $item['json_object'];
                $uuid = $object['uuid'] ?? null;
                if ($uuid === null) {
                    continue;
                }

                if (! isset($byUuid[$uuid])
                    || $this->languageRank($object, $preferredLanguage) < $this->languageRank($byUuid[$uuid], $preferredLanguage)) {
                    $byUuid[$uuid] = $object;
                }
            }

            return array_values($byUuid);
        });
    }

    /** 0 = requested language, 1 = English fallback, 2 = anything else. */
    private function languageRank(array $object, string $preferredLanguage): int
    {
        $language = $object['language'] ?? null;

        return match (true) {
            $language === $preferredLanguage => 0,
            $language === 'EN' => 1,
            default => 2,
        };
    }

    /**
     * @return \Generator<int, array>
     *
     * @throws MonarcApiException
     */
    private function paginate(string $schema, ?string $language): \Generator
    {
        $page = 1;
        $count = null;

        do {
            $query = ['schema' => $schema, 'page' => $page, 'per_page' => self::PER_PAGE];
            if ($language !== null) {
                $query['language'] = $language;
            }

            $body = $this->get('/', $query);

            foreach ($body['data'] ?? [] as $item) {
                yield $item;
            }

            $count ??= (int) ($body['metadata']['count'] ?? 0);
            $page++;
        } while (($page - 1) * self::PER_PAGE < $count);
    }

    /** Mercator's "fr"/"en" -> MOSP's "FR"/"EN" language codes. */
    private function mospLanguage(string $languageCode): string
    {
        return strtoupper($languageCode);
    }

    /**
     * @throws MonarcApiException
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->request()->get($this->base.$path, $query);
        } catch (ConnectionException) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_mosp_unreachable'));
        }

        if ($response->failed()) {
            throw new MonarcApiException(trans('cruds.configuration.monarc.error_mosp_unreachable'));
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::timeout((int) config('monarc.timeout', 15));
    }
}
