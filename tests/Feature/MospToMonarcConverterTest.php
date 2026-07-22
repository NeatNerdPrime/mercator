<?php

use App\Services\MospToMonarcConverter;
use Tests\TestCase;

uses(TestCase::class);

function mospAsset(array $overrides = []): array
{
    return array_replace(['uuid' => 'a1', 'code' => 'OV_SRV', 'label' => 'Serveur', 'description' => '', 'type' => 'Secondary', 'version' => 1, 'language' => 'FR'], $overrides);
}

function mospThreat(array $overrides = []): array
{
    return array_replace(['uuid' => 't1', 'code' => 'T1', 'label' => 'Threat', 'description' => '', 'theme' => 'Theme A', 'c' => true, 'i' => false, 'a' => false, 'language' => 'FR'], $overrides);
}

function mospVulnerability(array $overrides = []): array
{
    return array_replace(['uuid' => 'v1', 'code' => 'V1', 'label' => 'Vuln', 'description' => '', 'language' => 'FR'], $overrides);
}

function mospRisk(array $overrides = []): array
{
    return array_replace(['uuid' => 'amv1', 'asset' => 'a1', 'threat' => 't1', 'vulnerability' => 'v1', 'measures' => ['m1']], $overrides);
}

function mospReferential(array $overrides = []): array
{
    return array_replace([
        'uuid' => 'r1',
        'label' => 'ISO 27002',
        'values' => [
            ['uuid' => 'm1', 'code' => '5.1', 'label' => 'Policy', 'category' => 'Org', 'referential' => 'r1', 'referential_label' => 'ISO 27002'],
        ],
    ], $overrides);
}

test('converts flat MOSP catalogs into the fixture knowledgeBase shape', function () {
    $kb = (new MospToMonarcConverter)->convert(
        [mospAsset()], [mospThreat()], [mospVulnerability()], [mospRisk()], [mospReferential()]
    );

    $fixtureKb = json_decode(file_get_contents(base_path('tests/fixtures/templates/monarc.json')), true)['knowledgeBase'];

    expect(array_keys($kb))->toBe(['assets', 'threats', 'vulnerabilities', 'informationRisks', 'referentials', 'rolfTags', 'operationalRisks', 'recommendationSets']);
    expect(array_keys($kb['assets'][0]))->toBe(array_keys($fixtureKb['assets'][0]));
    expect(array_keys($kb['threats'][0]))->toBe(array_keys($fixtureKb['threats'][0]));
    expect(array_keys($kb['vulnerabilities'][0]))->toBe(array_keys($fixtureKb['vulnerabilities'][0]));
    expect(array_keys($kb['informationRisks'][0]))->toBe(array_keys($fixtureKb['informationRisks'][0]));
    expect(array_keys($kb['referentials'][0]))->toBe(array_keys($fixtureKb['referentials'][0]));
    expect(array_keys($kb['referentials'][0]['measures'][0]))->toBe(array_keys($fixtureKb['referentials'][0]['measures'][0]));
});

test('maps asset type name to the ANR integer convention (Primary=1, Secondary=2)', function () {
    $kb = (new MospToMonarcConverter)->convert(
        [mospAsset(['uuid' => 'p1', 'type' => 'Primary']), mospAsset(['uuid' => 's1', 'type' => 'Secondary'])],
        [], [], [], []
    );

    $byUuid = collect($kb['assets'])->keyBy('uuid');
    expect($byUuid['p1']['type'])->toBe(1);
    expect($byUuid['s1']['type'])->toBe(2);
});

test('converts threat booleans and theme label into the ANR shape', function () {
    $kb = (new MospToMonarcConverter)->convert([], [mospThreat()], [], [], []);

    $threat = $kb['threats'][0];
    expect($threat['confidentiality'])->toBe(1);
    expect($threat['integrity'])->toBe(0);
    expect($threat['availability'])->toBe(0);
    expect($threat['theme'])->toBe(['id' => 1, 'label' => 'Theme A']);
    expect($threat['trend'])->toBe(-1);
    expect($threat['qualification'])->toBe(-1);
});

test('deduplicates entries by uuid', function () {
    $kb = (new MospToMonarcConverter)->convert(
        [mospAsset(), mospAsset()],
        [mospThreat(), mospThreat()],
        [mospVulnerability(), mospVulnerability()],
        [mospRisk(), mospRisk()],
        []
    );

    expect($kb['assets'])->toHaveCount(1);
    expect($kb['threats'])->toHaveCount(1);
    expect($kb['vulnerabilities'])->toHaveCount(1);
    expect($kb['informationRisks'])->toHaveCount(1);
});

test('regroups referential values into measures, deduplicated by measure uuid', function () {
    $referential = mospReferential([
        'values' => [
            ['uuid' => 'm1', 'code' => '5.1', 'label' => 'Policy', 'category' => 'Org', 'referential' => 'r1', 'referential_label' => 'ISO 27002'],
            ['uuid' => 'm2', 'code' => '5.2', 'label' => 'Roles', 'category' => 'Org', 'referential' => 'r1', 'referential_label' => 'ISO 27002'],
        ],
    ]);

    $kb = (new MospToMonarcConverter)->convert([], [], [], [], [$referential]);

    expect($kb['referentials'])->toHaveCount(1);
    expect($kb['referentials'][0]['uuid'])->toBe('r1');
    expect($kb['referentials'][0]['measures'])->toHaveCount(2);
    expect($kb['referentials'][0]['measures'][0]['referential'])->toBe(['uuid' => 'r1', 'label' => 'ISO 27002']);
});

test('drops AMV measure references that fall outside the selected referentials', function () {
    // amv1 references m1 (selected referential) and m2 (not selected) — m2 must be dropped.
    $risk = mospRisk(['measures' => ['m1', 'm2']]);

    $kb = (new MospToMonarcConverter)->convert([], [], [], [$risk], [mospReferential()]);

    expect($kb['informationRisks'][0]['measures'])->toBe([['uuid' => 'm1']]);
});

test('AMVs keep no measures at all when no referential was selected', function () {
    $kb = (new MospToMonarcConverter)->convert([], [], [], [mospRisk()], []);

    expect($kb['informationRisks'][0]['measures'])->toBe([]);
    expect($kb['referentials'])->toBe([]);
});
