<?php

use App\Models\Application;
use App\Models\LogicalServer;
use App\Models\MacroProcessus;
use App\Models\PhysicalServer;
use App\Models\Process;
use App\Services\MonarcExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function monarcTestKnowledgeBase(): array
{
    return [
        'assets' => [
            ['uuid' => 'asset-service', 'code' => 'SERV', 'label' => 'Service', 'description' => '', 'type' => 1, 'status' => 1],
            ['uuid' => 'asset-proc', 'code' => 'PROC', 'label' => 'Processus', 'description' => '', 'type' => 1, 'status' => 1],
            ['uuid' => 'asset-app', 'code' => 'LOG_APP', 'label' => 'Application métier', 'description' => '', 'type' => 2, 'status' => 1],
            ['uuid' => 'asset-os', 'code' => 'LOG_OS', 'label' => "Système d'exploitation", 'description' => '', 'type' => 2, 'status' => 1],
            ['uuid' => 'asset-srv', 'code' => 'OV_SERVEUR', 'label' => 'Serveur', 'description' => '', 'type' => 2, 'status' => 1],
        ],
        'threats' => [
            ['uuid' => 't1', 'label' => 'Threat 1', 'description' => '', 'theme' => ['id' => 1, 'label' => 'Theme'], 'status' => 1, 'mode' => 0, 'code' => 'T1', 'confidentiality' => 1, 'integrity' => 1, 'availability' => 1, 'trend' => -1, 'comment' => '', 'qualification' => -1],
        ],
        'vulnerabilities' => [
            ['uuid' => 'v1', 'label' => 'Vuln 1', 'description' => '', 'code' => '1', 'status' => 1],
        ],
        'referentials' => [],
        'informationRisks' => [
            ['uuid' => 'amv1', 'asset' => ['uuid' => 'asset-app'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
            ['uuid' => 'amv2', 'asset' => ['uuid' => 'asset-app'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
            ['uuid' => 'amv3', 'asset' => ['uuid' => 'asset-os'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
        ],
        'rolfTags' => [],
        'operationalRisks' => [],
        'recommendationSets' => [],
    ];
}

function monarcTestScalesAndMethod(): array
{
    return [
        'monarc_version' => '2.13.3',
        'scales' => ['1' => ['min' => 0, 'max' => 4, 'type' => 1]],
        'operationalRiskScales' => ['1' => []],
        'method' => ['steps' => []],
        'thresholds' => ['seuil1' => 8, 'seuil2' => 27, 'seuilRolf1' => 2, 'seuilRolf2' => 6],
        'soas' => [],
        'soaScaleComments' => [],
    ];
}

beforeEach(function () {
    $this->macroProcessus = MacroProcessus::factory()->create();
    $this->process = Process::factory()->create(['macroprocess_id' => $this->macroProcessus->id]);
    $this->application = Application::factory()->create();
    $this->process->applications()->attach($this->application);
    $this->logicalServer = LogicalServer::factory()->create();
    $this->application->logicalServers()->attach($this->logicalServer);
    $this->physicalServer = PhysicalServer::factory()->create();
    $this->logicalServer->physicalServers()->attach($this->physicalServer);

    $this->selection = [
        ['model' => 'MacroProcessus', 'id' => $this->macroProcessus->id, 'name' => $this->macroProcessus->name, 'asset_uuid' => 'asset-service', 'scope' => 2],
        ['model' => 'Process', 'id' => $this->process->id, 'name' => $this->process->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
        ['model' => 'Application', 'id' => $this->application->id, 'name' => $this->application->name, 'asset_uuid' => 'asset-app', 'scope' => 1],
        ['model' => 'LogicalServer', 'id' => $this->logicalServer->id, 'name' => $this->logicalServer->name, 'asset_uuid' => 'asset-os', 'scope' => 1],
        ['model' => 'PhysicalServer', 'id' => $this->physicalServer->id, 'name' => $this->physicalServer->name, 'asset_uuid' => 'asset-srv', 'scope' => 2],
    ];

    $this->service = new MonarcExportService;
});

test('library mode produces the exact root shape of a real Monarc export', function () {
    $export = $this->service->buildExport(
        'library', 'Test export', 'description', 'fr',
        monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $this->selection
    );

    expect(array_keys($export))->toBe([
        'type', 'monarc_version', 'exportDatetime',
        'withEval', 'withControls', 'withRecommendations', 'withMethodSteps', 'withInterviews', 'withSoas', 'withRecords', 'withLibrary', 'withKnowledgeBase',
        'languageCode', 'languageIndex',
        'knowledgeBase', 'library', 'instances', 'anrInstanceMetadataFields',
        'scales', 'operationalRiskScales', 'soaScaleComments', 'soas', 'method', 'thresholds',
        'interviews', 'gdprRecords',
    ]);

    expect($export['type'])->toBe('anr');
    expect($export['withEval'])->toBeFalse();
    expect($export['withLibrary'])->toBeTrue();
    expect($export['withKnowledgeBase'])->toBeTrue();
    expect($export['instances'])->toBe([]);
    expect($export['languageIndex'])->toBe(1);
});

test('library objects are grouped into flat categories, never nested', function () {
    // Verified against a live Monarc 2.13.3 instance: a non-empty "children"
    // on a library object crashes the real instances/import endpoint
    // (ObjectCategoryImportProcessor::processObjectCategoryData() called
    // with a null category) — every object must be a flat category root.
    $export = $this->service->buildExport(
        'library', 'Test export', 'description', 'fr',
        monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $this->selection
    );

    $categories = collect($export['library']['categories'])->keyBy('label');
    expect($categories->keys()->all())->toBe(['Applications', 'Processus', 'Serveurs']);

    foreach ($export['library']['categories'] as $category) {
        foreach ($category['objects'] as $object) {
            expect($object['children'])->toBe([]);
        }
    }

    $processusNames = collect($categories['Processus']['objects'])->pluck('name')->all();
    expect($processusNames)->toEqualCanonicalizing([$this->macroProcessus->name, $this->process->name]);

    $serveursNames = collect($categories['Serveurs']['objects'])->pluck('name')->all();
    expect($serveursNames)->toEqualCanonicalizing([$this->logicalServer->name, $this->physicalServer->name]);

    $applicationsNames = collect($categories['Applications']['objects'])->pluck('name')->all();
    expect($applicationsNames)->toBe([$this->application->name]);
});

test('referential integrity: every asset uuid used exists in the knowledgeBase', function () {
    $export = $this->service->buildExport(
        'library', 'Test export', 'description', 'fr',
        monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $this->selection
    );

    $assetUuids = collect($export['knowledgeBase']['assets'])->pluck('uuid')->all();

    foreach ($export['knowledgeBase']['informationRisks'] as $amv) {
        expect($assetUuids)->toContain($amv['asset']['uuid']);
    }

    $walk = function (array $objects) use (&$walk, $assetUuids) {
        foreach ($objects as $object) {
            expect($assetUuids)->toContain($object['asset']['uuid']);
            $walk($object['children']);
        }
    };
    foreach ($export['library']['categories'] as $category) {
        $walk($category['objects']);
    }
});

test('countRisks sums AMVs per selected asset regardless of scope, in library mode', function () {
    $count = $this->service->countRisks(monarcTestKnowledgeBase(), $this->selection);

    // asset-service: 0, asset-proc: 0, asset-app: 2, asset-os: 1, asset-srv: 0
    expect($count)->toBe(3);
});

test('analysis mode builds an instance tree mirroring the library composition', function () {
    $export = $this->service->buildExport(
        'analysis', 'Test export', 'description', 'fr',
        monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $this->selection
    );

    expect($export['instances'])->toHaveCount(1);

    $root = $export['instances'][0];
    expect($root['confidentiality'])->toBe(-1);
    expect($root['isConfidentialityInherited'])->toBe(0);
    expect($root['instanceMetadata'])->toBe([]);
    expect($root['instanceRisks'])->toBe([]); // asset-service has 0 AMVs

    $processInstance = $root['children'][0];
    $applicationInstance = $processInstance['children'][0];
    expect($applicationInstance['instanceRisks'])->toHaveCount(2);
    expect($applicationInstance['instanceRisks'][0])->toBe(['informationRisk' => ['uuid' => 'amv1'], 'recommendations' => []]);

    $logicalServerInstance = $applicationInstance['children'][0];
    expect($logicalServerInstance['instanceRisks'])->toHaveCount(1);
});
