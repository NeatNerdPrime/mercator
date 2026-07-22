<?php

use App\Exceptions\MonarcApiException;
use App\Services\MospService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['monarc.mosp_base' => 'https://mosp.test/api/v2/object', 'monarc.cache_ttl' => 3600]);
    Cache::flush();
});

/**
 * Fakes a single-page, unfiltered response for a given schema, asserting
 * the request carries NO "language" query param (see getBaseAssets() etc.:
 * MOSP's own language filter drops real entries live, so these fetch
 * everything and pick the preferred language client-side instead).
 */
function fakeMospSchema(string $schema, array $items): void
{
    Http::fake(function ($request) use ($schema, $items) {
        $data = $request->data();
        expect($data['schema'] ?? null)->toBe($schema);
        expect($data)->not->toHaveKey('language');

        return Http::response(['metadata' => ['count' => count($items)], 'data' => $items], 200);
    });
}

test('getBaseAssets fetches the "Assets" schema unfiltered, unwrapping json_object', function () {
    fakeMospSchema('Assets', [
        ['id' => 1, 'json_object' => ['uuid' => 'a1', 'code' => 'OV_SERVEUR', 'label' => 'Serveur', 'type' => 'Secondary', 'language' => 'FR']],
    ]);

    $assets = (new MospService)->getBaseAssets('fr');

    expect($assets)->toBe([
        ['uuid' => 'a1', 'code' => 'OV_SERVEUR', 'label' => 'Serveur', 'type' => 'Secondary', 'language' => 'FR'],
    ]);
});

test('getBaseAssets picks the preferred language variant per uuid, ignoring MOSP duplicates', function () {
    // Same uuid published 3x (a real, observed MOSP data quirk) — only one must survive,
    // and it must be the requested language even though it isn't encountered first.
    fakeMospSchema('Assets', [
        ['id' => 1, 'json_object' => ['uuid' => 'a1', 'code' => 'OV_MOBIL', 'label' => 'Laptop', 'language' => 'EN']],
        ['id' => 1, 'json_object' => ['uuid' => 'a1', 'code' => 'OV_MOBIL', 'label' => 'Laptop', 'language' => 'EN']],
        ['id' => 2, 'json_object' => ['uuid' => 'a1', 'code' => 'OV_MOBIL', 'label' => 'Portable', 'language' => 'FR']],
    ]);

    $assets = (new MospService)->getBaseAssets('fr');

    expect($assets)->toHaveCount(1);
    expect($assets[0]['label'])->toBe('Portable');
    expect($assets[0]['language'])->toBe('FR');
});

test('getBaseAssets falls back to English when the preferred language is missing', function () {
    fakeMospSchema('Assets', [
        ['id' => 1, 'json_object' => ['uuid' => 'a1', 'code' => 'INFO', 'label' => 'Information', 'language' => 'EN']],
    ]);

    $assets = (new MospService)->getBaseAssets('fr');

    expect($assets)->toHaveCount(1);
    expect($assets[0]['language'])->toBe('EN');
});

test('getBaseAssets prefers English over a third language when neither matches the requested one', function () {
    // Neither DE nor IT is what was asked for (fr) — English must win over
    // whichever of the two happens to be encountered first.
    fakeMospSchema('Assets', [
        ['id' => 1, 'json_object' => ['uuid' => 'a1', 'code' => 'INFO', 'label' => 'Informationen', 'language' => 'DE']],
        ['id' => 2, 'json_object' => ['uuid' => 'a1', 'code' => 'INFO', 'label' => 'Informazione', 'language' => 'IT']],
        ['id' => 3, 'json_object' => ['uuid' => 'a1', 'code' => 'INFO', 'label' => 'Information', 'language' => 'EN']],
    ]);

    $assets = (new MospService)->getBaseAssets('fr');

    expect($assets)->toHaveCount(1);
    expect($assets[0]['label'])->toBe('Information');
    expect($assets[0]['language'])->toBe('EN');
});

test('getBaseThreats also fetches unfiltered', function () {
    fakeMospSchema('Threats', [['id' => 1, 'json_object' => ['uuid' => 't1', 'language' => 'FR']]]);
    expect((new MospService)->getBaseThreats('fr'))->toBe([['uuid' => 't1', 'language' => 'FR']]);
});

test('getBaseVulnerabilities also fetches unfiltered', function () {
    fakeMospSchema('Vulnerabilities', [['id' => 2, 'json_object' => ['uuid' => 'v1', 'language' => 'FR']]]);
    expect((new MospService)->getBaseVulnerabilities('fr'))->toBe([['uuid' => 'v1', 'language' => 'FR']]);
});

test('getBaseRisks fetches the "Risks" (AMV) schema without a language filter, following pagination', function () {
    $item = fn (string $uuid) => ['id' => 1, 'json_object' => ['uuid' => $uuid, 'asset' => 'a1', 'threat' => 't1', 'vulnerability' => 'v1']];

    Http::fake(function ($request) use ($item) {
        expect($request->url())->not->toContain('language=');
        $page = (int) ($request->data()['page'] ?? 1);

        return $page === 1
            ? Http::response(['metadata' => ['count' => 150], 'data' => array_fill(0, 100, $item('r1'))], 200)
            : Http::response(['metadata' => ['count' => 150], 'data' => array_fill(0, 50, $item('r2'))], 200);
    });

    $risks = (new MospService)->getBaseRisks();

    expect($risks)->toHaveCount(150);
});

test('base catalog fetches are cached across service instances', function () {
    fakeMospSchema('Assets', [['id' => 1, 'json_object' => ['uuid' => 'a1']]]);

    (new MospService)->getBaseAssets('fr');
    (new MospService)->getBaseAssets('fr');

    Http::assertSentCount(1);
});

test('getReferentials lists published "Security referentials" with organization name', function () {
    Http::fake([
        'mosp.test/api/v2/object/*' => Http::response([
            'metadata' => ['count' => 1],
            'data' => [
                ['id' => 5233, 'name' => 'ISO 27017', 'organization' => ['name' => 'MONARC']],
            ],
        ], 200),
    ]);

    $referentials = (new MospService)->getReferentials();

    expect($referentials)->toBe([
        ['id' => 5233, 'name' => 'ISO 27017', 'organization' => 'MONARC'],
    ]);
});

test('getReferentialData returns the object tree from a single-element array response', function () {
    Http::fake([
        'mosp.test/api/v2/object/5233' => Http::response([
            ['json_object' => ['uuid' => 'ref1', 'label' => 'ISO 27017', 'values' => []]],
        ], 200),
    ]);

    $data = (new MospService)->getReferentialData(5233);

    expect($data)->toBe(['uuid' => 'ref1', 'label' => 'ISO 27017', 'values' => []]);
});

test('a MOSP failure throws a translated exception without breaking the local ANR source', function () {
    Http::fake([
        'mosp.test/api/v2/object/*' => Http::response([], 500),
    ]);

    $service = new MospService;

    expect(fn () => $service->getBaseAssets('fr'))->toThrow(MonarcApiException::class);
});
