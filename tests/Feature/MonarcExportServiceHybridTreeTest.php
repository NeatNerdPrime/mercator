<?php

use App\Models\Application;
use App\Models\LogicalServer;
use App\Models\Process;
use App\Services\MonarcExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function hybridKnowledgeBase(): array
{
    return [
        'assets' => [
            ['uuid' => 'asset-proc', 'code' => 'PROC', 'label' => 'Processus', 'description' => '', 'type' => 1, 'status' => 1],
            ['uuid' => 'asset-app', 'code' => 'LOG_APP', 'label' => 'Application métier', 'description' => '', 'type' => 2, 'status' => 1],
            ['uuid' => 'asset-os', 'code' => 'LOG_OS', 'label' => "Système d'exploitation", 'description' => '', 'type' => 2, 'status' => 1],
        ],
        'threats' => [],
        'vulnerabilities' => [],
        'referentials' => [],
        'informationRisks' => [
            ['uuid' => 'amv-app-1', 'asset' => ['uuid' => 'asset-app'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
            ['uuid' => 'amv-app-2', 'asset' => ['uuid' => 'asset-app'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
            ['uuid' => 'amv-os-1', 'asset' => ['uuid' => 'asset-os'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
        ],
        'rolfTags' => [],
        'operationalRisks' => [],
        'recommendationSets' => [],
    ];
}

function hybridScalesAndMethod(): array
{
    return ['monarc_version' => '2.13.3', 'scales' => [], 'operationalRiskScales' => [], 'method' => [], 'thresholds' => [], 'soas' => [], 'soaScaleComments' => []];
}

beforeEach(function () {
    $this->service = new MonarcExportService;
});

test('a support shared by two selected primaries gets two instance placements when local', function () {
    $processA = Process::factory()->create(['name' => 'Process A']);
    $processB = Process::factory()->create(['name' => 'Process B']);
    $application = Application::factory()->create(['name' => 'Shared App']);
    $processA->applications()->attach($application);
    $processB->applications()->attach($application);

    $selection = [
        ['model' => 'Process', 'id' => $processA->id, 'name' => $processA->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
        ['model' => 'Process', 'id' => $processB->id, 'name' => $processB->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
        ['model' => 'Application', 'id' => $application->id, 'name' => $application->name, 'asset_uuid' => 'asset-app', 'scope' => 1], // local
    ];

    $count = $this->service->countRisks(hybridKnowledgeBase(), $selection, 'analysis');
    // Process A: 0, Process B: 0, Application (local, 2 AMVs x 2 placements): 4
    expect($count)->toBe(4);

    $export = $this->service->buildExport('analysis', 'n', 'd', 'fr', hybridKnowledgeBase(), hybridScalesAndMethod(), $selection);

    // Library: exactly one entry for the shared application, never duplicated.
    $appObjects = collect($export['library']['categories'])->flatMap(fn ($c) => $c['objects'])->where('name', 'Shared App');
    expect($appObjects)->toHaveCount(1);
    $libraryUuid = $appObjects->first()['uuid'];

    // Instances: two placements, both referencing the SAME library object uuid.
    $placements = collect($export['instances'])->flatMap(function ($root) {
        return collect($root['children'])->where('name', 'Shared App');
    });
    expect($placements)->toHaveCount(2);
    expect($placements->pluck('object.uuid')->unique()->all())->toBe([$libraryUuid]);
});

test('the same shared support counts once when global regardless of placements', function () {
    $processA = Process::factory()->create(['name' => 'Process A']);
    $processB = Process::factory()->create(['name' => 'Process B']);
    $application = Application::factory()->create(['name' => 'Shared App']);
    $processA->applications()->attach($application);
    $processB->applications()->attach($application);

    $selection = [
        ['model' => 'Process', 'id' => $processA->id, 'name' => $processA->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
        ['model' => 'Process', 'id' => $processB->id, 'name' => $processB->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
        ['model' => 'Application', 'id' => $application->id, 'name' => $application->name, 'asset_uuid' => 'asset-app', 'scope' => 2], // global
    ];

    $count = $this->service->countRisks(hybridKnowledgeBase(), $selection, 'analysis');
    // Application (global, 2 AMVs, counted once regardless of 2 placements): 2
    expect($count)->toBe(2);
});

test('library mode never multiplies, even for a shared support', function () {
    $processA = Process::factory()->create();
    $processB = Process::factory()->create();
    $application = Application::factory()->create();
    $processA->applications()->attach($application);
    $processB->applications()->attach($application);

    $selection = [
        ['model' => 'Process', 'id' => $processA->id, 'name' => $processA->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
        ['model' => 'Process', 'id' => $processB->id, 'name' => $processB->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
        ['model' => 'Application', 'id' => $application->id, 'name' => $application->name, 'asset_uuid' => 'asset-app', 'scope' => 1],
    ];

    $count = $this->service->countRisks(hybridKnowledgeBase(), $selection, 'library');
    expect($count)->toBe(2); // n=2, counted once per object regardless of scope in library mode
});

test('a support with no selected parent is promoted to its own root instance, with no synthetic container', function () {
    $logicalServer = LogicalServer::factory()->create(['name' => 'Orphan Server']);

    $selection = [
        ['model' => 'LogicalServer', 'id' => $logicalServer->id, 'name' => $logicalServer->name, 'asset_uuid' => 'asset-os', 'scope' => 1],
    ];

    $export = $this->service->buildExport('analysis', 'n', 'd', 'fr', hybridKnowledgeBase(), hybridScalesAndMethod(), $selection);

    // The orphan is a root instance in its own right — no wrapping
    // "(conteneur)" object, category, or CONT asset is ever created.
    expect($export['instances'])->toHaveCount(1);
    $instance = $export['instances'][0];
    expect($instance['name'])->toBe('Orphan Server');
    expect($instance['level'])->toBe(1);
    expect($instance['asset']['uuid'])->toBe('asset-os');
    expect($instance['instanceRisks'])->toHaveCount(1); // asset-os's own AMV
    expect($instance['children'])->toBe([]);

    expect(collect($export['knowledgeBase']['assets'])->firstWhere('code', 'CONT'))->toBeNull();
    expect(collect($export['library']['categories'])->pluck('label'))->not->toContain('Serveurs (conteneur)');
});

test('no CONT asset or "(conteneur)" category is ever added, orphans or not', function () {
    $process = Process::factory()->create();
    $selection = [
        ['model' => 'Process', 'id' => $process->id, 'name' => $process->name, 'asset_uuid' => 'asset-proc', 'scope' => 1],
    ];

    $export = $this->service->buildExport('analysis', 'n', 'd', 'fr', hybridKnowledgeBase(), hybridScalesAndMethod(), $selection);

    expect(collect($export['knowledgeBase']['assets'])->firstWhere('code', 'CONT'))->toBeNull();
    expect(collect($export['library']['categories'])->pluck('label'))->not->toContain('Serveurs (conteneur)');
});

test('buildRelationsMap exposes priority groups matching the multi-parent and fallback rules', function () {
    $processA = Process::factory()->create();
    $processB = Process::factory()->create();
    $application = Application::factory()->create();
    $processA->applications()->attach($application);
    $processB->applications()->attach($application);

    $relations = $this->service->buildRelationsMap();

    expect($relations["Application:{$application->id}"])->toHaveCount(1);
    expect($relations["Application:{$application->id}"][0])->toEqualCanonicalizing([
        "Process:{$processA->id}", "Process:{$processB->id}",
    ]);
});
