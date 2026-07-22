<?php

use App\Models\Application;
use App\Models\LogicalServer;
use App\Models\MacroProcessus;
use App\Models\PhysicalServer;
use App\Models\Process;
use App\Models\Workstation;
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

test('library objects are grouped into Mercator "view" categories, objects themselves always flat', function () {
    // Verified against a live Monarc 2.13.3 instance: a non-empty "children"
    // on a library OBJECT crashes the real instances/import endpoint
    // (ObjectCategoryImportProcessor::processObjectCategoryData() called
    // with a null category) — every object must be a flat category root.
    // Categories (the folders) are a different matter — see the next test.
    $export = $this->service->buildExport(
        'library', 'Test export', 'description', 'fr',
        monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $this->selection
    );

    $categories = collect($export['library']['categories'])->keyBy('label');
    expect($categories->keys()->all())->toEqualCanonicalizing([
        trans('panel.menu.information_system'),
        trans('panel.menu.applications'),
        trans('panel.menu.logical_infrastructure'),
        trans('panel.menu.physical_infrastructure'),
    ]);

    foreach ($export['library']['categories'] as $category) {
        foreach ($category['objects'] as $object) {
            expect($object['children'])->toBe([]);
        }
    }

    // Only one selected object per family here, so every view category stays
    // flat (no family sub-category is created) — see the next test for that.
    $informationSystem = $categories[trans('panel.menu.information_system')];
    expect($informationSystem['children'])->toBe([]);
    $informationSystemNames = collect($informationSystem['objects'])->pluck('name')->all();
    expect($informationSystemNames)->toEqualCanonicalizing([$this->macroProcessus->name, $this->process->name]);

    $logicalNames = collect($categories[trans('panel.menu.logical_infrastructure')]['objects'])->pluck('name')->all();
    expect($logicalNames)->toBe([$this->logicalServer->name]);

    $physicalNames = collect($categories[trans('panel.menu.physical_infrastructure')]['objects'])->pluck('name')->all();
    expect($physicalNames)->toBe([$this->physicalServer->name]);

    $applicationsNames = collect($categories[trans('panel.menu.applications')]['objects'])->pluck('name')->all();
    expect($applicationsNames)->toBe([$this->application->name]);
});

test('a view with several objects of the same family gets a named sub-category, a lone object stays direct', function () {
    $workstationA = Workstation::factory()->create();
    $workstationB = Workstation::factory()->create();

    $selection = array_merge($this->selection, [
        ['model' => 'Workstation', 'id' => $workstationA->id, 'name' => $workstationA->name, 'asset_uuid' => 'asset-srv', 'scope' => 1],
        ['model' => 'Workstation', 'id' => $workstationB->id, 'name' => $workstationB->name, 'asset_uuid' => 'asset-srv', 'scope' => 1],
    ]);

    $export = $this->service->buildExport(
        'library', 'Test export', 'description', 'fr',
        monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $selection
    );

    $physical = collect($export['library']['categories'])->firstWhere('label', trans('panel.menu.physical_infrastructure'));

    // PhysicalServer (1 object) stays directly under the view...
    expect(collect($physical['objects'])->pluck('name')->all())->toBe([$this->physicalServer->name]);

    // ...Workstation (2 objects) gets its own named sub-category instead.
    expect($physical['children'])->toHaveCount(1);
    $workstationCategory = $physical['children'][0];
    expect($workstationCategory['label'])->toBe(trans('cruds.workstation.title'));
    expect($workstationCategory['isRoot'])->toBe(0);
    expect(collect($workstationCategory['objects'])->pluck('name')->all())
        ->toEqualCanonicalizing([$workstationA->name, $workstationB->name]);
    foreach ($workstationCategory['objects'] as $object) {
        expect($object['children'])->toBe([]);
    }
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

    // Hybrid tree (option C): every selected primary (MacroProcessus AND Process)
    // is its own root — Process is never nested under MacroProcessus.
    expect($export['instances'])->toHaveCount(2);

    $byName = collect($export['instances'])->keyBy('name');
    $macroProcessusInstance = $byName[$this->macroProcessus->name];
    expect($macroProcessusInstance['confidentiality'])->toBe(-1);
    expect($macroProcessusInstance['isConfidentialityInherited'])->toBe(0);
    expect($macroProcessusInstance['instanceMetadata'])->toBe([]);
    expect($macroProcessusInstance['instanceRisks'])->toBe([]); // asset-service has 0 AMVs
    expect($macroProcessusInstance['children'])->toBe([]); // Process is not nested under it

    $processInstance = $byName[$this->process->name];
    $applicationInstance = $processInstance['children'][0];
    expect($applicationInstance['instanceRisks'])->toHaveCount(2);
    expect($applicationInstance['instanceRisks'][0])->toBe(['informationRisk' => ['uuid' => 'amv1'], 'recommendations' => []]);

    $logicalServerInstance = $applicationInstance['children'][0];
    expect($logicalServerInstance['instanceRisks'])->toHaveCount(1);
});

test('object uuids are stable (uuid v5) across two independent export generations', function () {
    // The cornerstone of MonarcSyncService's diff: the same Mercator object
    // must always produce the same library-object uuid, or nothing could
    // ever be deduplicated across two syncs.
    $export1 = $this->service->buildExport('analysis', 'Export 1', '', 'fr', monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $this->selection);
    $export2 = $this->service->buildExport('analysis', 'Export 2', '', 'fr', monarcTestKnowledgeBase(), monarcTestScalesAndMethod(), $this->selection);

    $uuidsByName1 = collect($export1['library']['categories'])->flatMap(fn ($c) => collect($c['objects']))->pluck('uuid', 'name');
    $uuidsByName2 = collect($export2['library']['categories'])->flatMap(fn ($c) => collect($c['objects']))->pluck('uuid', 'name');

    expect($uuidsByName1->all())->toBe($uuidsByName2->all());

    expect(MonarcExportService::objectUuid('Process', $this->process->id))
        ->toBe(MonarcExportService::objectUuid('Process', $this->process->id));

    expect(MonarcExportService::objectUuid('Process', $this->process->id))
        ->not->toBe(MonarcExportService::objectUuid('Application', $this->process->id));
});
