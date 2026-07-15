<?php

use App\Models\Application;
use App\Models\Parameter;
use App\Models\Process;
use App\Models\User;
use App\Support\MonarcSettings;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    $this->user = User::query()->where('login', 'admin@admin.com')->first();
    $this->actingAs($this->user);

    // Reset in-memory config between tests (each test starts from config/monarc.php defaults).
    config(['monarc' => require config_path('monarc.php')]);
});

/** Fakes MOSP's flat base catalog: PROC + LOG_APP assets, 2 AMVs on LOG_APP, no referentials. */
function fakeMospBaseCatalog(): void
{
    Http::fake(function ($request) {
        $data = $request->data();
        $schema = $data['schema'] ?? null;

        return match ($schema) {
            'Assets' => Http::response(['metadata' => ['count' => 2], 'data' => [
                ['id' => 1, 'json_object' => ['uuid' => 'uuid-proc', 'code' => 'PROC', 'label' => 'Processus', 'description' => '', 'type' => 'Primary']],
                ['id' => 2, 'json_object' => ['uuid' => 'uuid-app', 'code' => 'LOG_APP', 'label' => 'Application métier', 'description' => '', 'type' => 'Secondary']],
            ]], 200),
            'Threats' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            'Vulnerabilities' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            'Risks' => Http::response(['metadata' => ['count' => 2], 'data' => [
                ['id' => 1, 'json_object' => ['uuid' => 'amv1', 'asset' => 'uuid-app', 'threat' => 't1', 'vulnerability' => 'v1', 'measures' => []]],
                ['id' => 2, 'json_object' => ['uuid' => 'amv2', 'asset' => 'uuid-app', 'threat' => 't1', 'vulnerability' => 'v1', 'measures' => []]],
            ]], 200),
            'Security referentials' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
        };
    });
}

/** Same shape as fakeMospBaseCatalog(), plus an asset code (OV_DEVELOPPEMENT) mapping to no Mercator family. */
function fakeMospBaseCatalogWithUnmappedAsset(): void
{
    Http::fake(function ($request) {
        $data = $request->data();
        $schema = $data['schema'] ?? null;

        return match ($schema) {
            'Assets' => Http::response(['metadata' => ['count' => 3], 'data' => [
                ['id' => 1, 'json_object' => ['uuid' => 'uuid-proc', 'code' => 'PROC', 'label' => 'Processus', 'description' => '', 'type' => 'Primary']],
                ['id' => 2, 'json_object' => ['uuid' => 'uuid-org', 'code' => 'ORG_GEN', 'label' => 'Générale', 'description' => '', 'type' => 'Secondary']],
                // OV_DEVELOPPEMENT (application development project) maps to no Mercator family — must not produce a row.
                ['id' => 3, 'json_object' => ['uuid' => 'uuid-dev', 'code' => 'OV_DEVELOPPEMENT', 'label' => "Développements d'applications", 'description' => '', 'type' => 'Secondary']],
            ]], 200),
            'Threats' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            'Vulnerabilities' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            'Risks' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            'Security referentials' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
        };
    });
}

describe('activation gate', function () {
    test('GET admin/monarc returns 404 when Monarc is disabled', function () {
        $response = $this->get(route('admin.monarc'));

        $response->assertNotFound();
    });

    test('GET admin/monarc returns 200 when Monarc is enabled', function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertViewIs('admin.monarc');
    });

    test('denies access without permission even when enabled', function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => '']);
        fakeMospBaseCatalog();

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.monarc'));

        $response->assertForbidden();
    });
});

describe('navbar entry', function () {
    test('Monarc entry is absent from the Tools menu when disabled', function () {
        $response = $this->get(route('admin.home'));

        $response->assertOk();
        $response->assertDontSee(route('admin.monarc', absolute: false), false);
    });

    test('Monarc entry is present in the Tools menu when enabled', function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => '']);

        $response = $this->get(route('admin.home'));

        $response->assertOk();
        $response->assertSee('/admin/monarc', false);
    });
});

describe('selection page rendering', function () {
    beforeEach(function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
    });

    test('shows rows from the MOSP base catalog with no referential selected', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertDontSee(trans('cruds.monarc.no_rows_mosp'));
        $response->assertSee('LOG_APP', false);
        // amv count for the MOSP asset must be embedded for the JS counter.
        $response->assertSee('"uuid-app":2', false);
    });

    test('lists Mercator objects grouped by family, and the row for each asset code', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Zzz Process']);
        $application = Application::factory()->create(['name' => 'Aaa App']);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee($process->name);
        $response->assertSee($application->name);
        // One row per MOSP asset (PROC, LOG_APP), each mapping to a Mercator family via JS.
        $response->assertSee('PROC', false);
        $response->assertSee('LOG_APP', false);
    });

    test('row labels are fetched in the current user\'s language, not a hardcoded default', function () {
        Http::fake(function ($request) {
            $data = $request->data();

            return match ($data['schema'] ?? null) {
                'Assets' => Http::response(['metadata' => ['count' => 2], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'uuid-proc', 'code' => 'PROC', 'label' => 'Processus', 'description' => '', 'type' => 'Primary', 'language' => 'FR']],
                    ['id' => 2, 'json_object' => ['uuid' => 'uuid-proc', 'code' => 'PROC', 'label' => 'Process', 'description' => '', 'type' => 'Primary', 'language' => 'EN']],
                ]], 200),
                default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            };
        });

        $this->user->update(['language' => 'en']);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        // The row's label for the "uuid-proc" asset must be the English MOSP
        // variant — not just present anywhere, but as this specific row's label.
        $viewSections = collect($response->viewData('viewSections'));
        $row = $viewSections->flatMap(fn ($section) => $section['rows'])->firstWhere('asset_uuid', 'uuid-proc');
        expect($row['label'])->toBe('Process');
    });

    test('row labels fall back to English when the base catalog has no variant for the current user language', function () {
        Http::fake(function ($request) {
            $data = $request->data();

            return match ($data['schema'] ?? null) {
                'Assets' => Http::response(['metadata' => ['count' => 1], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'uuid-proc', 'code' => 'PROC', 'label' => 'Process', 'description' => '', 'type' => 'Primary', 'language' => 'EN']],
                ]], 200),
                default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            };
        });

        // Seeded admin user's language is 'fr', but only an EN variant exists.
        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('Process', false);
    });

    test('drops rows whose asset code maps to no Mercator family, and groups the rest by view', function () {
        fakeMospBaseCatalogWithUnmappedAsset();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        // PROC -> Process family ("information_system" view), ORG_GEN -> Entity ("ecosystem" view).
        $response->assertSee(trans('panel.menu.information_system'));
        $response->assertSee(trans('panel.menu.ecosystem'));
        $response->assertSee('PROC', false);
        $response->assertSee('ORG_GEN', false);
        // OV_DEVELOPPEMENT (application development project) maps to no Mercator family -> no row at all.
        $response->assertDontSee('OV_DEVELOPPEMENT', false);

        // Only 2 view sections rendered (no Network/Lan/Site/Building/Application asset present).
        $viewSections = $response->viewData('viewSections');
        expect(collect($viewSections)->pluck('key')->all())->toBe(['ecosystem', 'information_system']);
    });

    test('embeds the AMV-count-per-asset map as JSON for the client-side counter', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('"uuid-app":2', false);
    });

    test('embeds the risks (threat + vulnerability labels) per asset for the "view risks" modal', function () {
        Http::fake(function ($request) {
            $data = $request->data();

            return match ($data['schema'] ?? null) {
                'Assets' => Http::response(['metadata' => ['count' => 1], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'uuid-app', 'code' => 'LOG_APP', 'label' => 'Application métier', 'description' => '', 'type' => 'Secondary']],
                ]], 200),
                'Threats' => Http::response(['metadata' => ['count' => 1], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 't1', 'label' => "Erreur d'utilisation"]],
                ]], 200),
                'Vulnerabilities' => Http::response(['metadata' => ['count' => 1], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'v1', 'label' => 'Absence de documentation']],
                ]], 200),
                'Risks' => Http::response(['metadata' => ['count' => 1], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'amv1', 'asset' => 'uuid-app', 'threat' => 't1', 'vulnerability' => 'v1', 'measures' => []]],
                ]], 200),
                default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            };
        });

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $risksByAssetUuid = $response->viewData('risksByAssetUuid');
        expect($risksByAssetUuid['uuid-app'])->toBe([
            ['threat' => "Erreur d'utilisation", 'vulnerability' => 'Absence de documentation'],
        ]);
    });

    test('the risks list for the "view risks" modal is sorted by threat name', function () {
        Http::fake(function ($request) {
            $data = $request->data();

            return match ($data['schema'] ?? null) {
                'Assets' => Http::response(['metadata' => ['count' => 1], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'uuid-app', 'code' => 'LOG_APP', 'label' => 'Application métier', 'description' => '', 'type' => 'Secondary']],
                ]], 200),
                'Threats' => Http::response(['metadata' => ['count' => 3], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 't1', 'label' => 'Vol de matériel']],
                    ['id' => 2, 'json_object' => ['uuid' => 't2', 'label' => "Erreur d'utilisation"]],
                    ['id' => 3, 'json_object' => ['uuid' => 't3', 'label' => 'Incendie']],
                ]], 200),
                'Vulnerabilities' => Http::response(['metadata' => ['count' => 1], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'v1', 'label' => 'Absence de documentation']],
                ]], 200),
                'Risks' => Http::response(['metadata' => ['count' => 3], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'amv1', 'asset' => 'uuid-app', 'threat' => 't1', 'vulnerability' => 'v1', 'measures' => []]],
                    ['id' => 2, 'json_object' => ['uuid' => 'amv2', 'asset' => 'uuid-app', 'threat' => 't2', 'vulnerability' => 'v1', 'measures' => []]],
                    ['id' => 3, 'json_object' => ['uuid' => 'amv3', 'asset' => 'uuid-app', 'threat' => 't3', 'vulnerability' => 'v1', 'measures' => []]],
                ]], 200),
                default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            };
        });

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $risksByAssetUuid = $response->viewData('risksByAssetUuid');
        expect(collect($risksByAssetUuid['uuid-app'])->pluck('threat')->all())->toBe([
            "Erreur d'utilisation", 'Incendie', 'Vol de matériel',
        ]);
    });

    test('embeds the Mercator family catalog and cartography relations for the JS counter', function () {
        fakeMospBaseCatalog();
        $processA = Process::factory()->create();
        $processB = Process::factory()->create();
        $application = Application::factory()->create();
        $processA->applications()->attach($application);
        $processB->applications()->attach($application);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('"Process:'.$processA->id.'"', false);
        $response->assertSee('"Application:'.$application->id.'"', false);
    });

    test('a Mercator model mapped to several Monarc codes is offered under every one of its rows', function () {
        Http::fake(function ($request) {
            $data = $request->data();
            $schema = $data['schema'] ?? null;

            return match ($schema) {
                'Assets' => Http::response(['metadata' => ['count' => 2], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'uuid-bat', 'code' => 'BAT_LOC', 'label' => 'Locaux, bâtiments', 'description' => '', 'type' => 'Primary']],
                    ['id' => 2, 'json_object' => ['uuid' => 'uuid-salle', 'code' => 'OV_SALLE_IT', 'label' => 'Salle Informatique', 'description' => '', 'type' => 'Secondary']],
                ]], 200),
                'Threats', 'Vulnerabilities', 'Risks', 'Security referentials' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
                default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            };
        });

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $familiesByAssetCode = $response->viewData('familiesByAssetCode');
        expect($familiesByAssetCode['BAT_LOC'])->toContain('Building');
        expect($familiesByAssetCode['OV_SALLE_IT'])->toContain('Building');
    });

    test('the complementary multi-code mappings (Peripheral, Actor, MacroProcessus, ApplicationService, ZoneAdmin) are all offered', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $familiesByAssetCode = $response->viewData('familiesByAssetCode');
        expect($familiesByAssetCode['OV_MULTI_IMPRIMANTE'])->toContain('Peripheral');
        expect($familiesByAssetCode['MAT_PERI'])->toContain('Peripheral');
        expect($familiesByAssetCode['OV_UTIL'])->toContain('Actor');
        expect($familiesByAssetCode['PER_DEC'])->toContain('Actor');
        expect($familiesByAssetCode['PER_UTI'])->toContain('Actor');
        expect($familiesByAssetCode['SERV_ESS'])->toContain('MacroProcessus');
        expect($familiesByAssetCode['SYS_MES'])->toContain('ApplicationService');
        expect($familiesByAssetCode['SYS_ITR'])->toContain('ApplicationService');
        expect($familiesByAssetCode['SYS_WEB'])->toContain('ApplicationService');
        expect($familiesByAssetCode['OV_ORGANISATION'])->toContain('ZoneAdmin');

        $families = collect($response->viewData('families'))->pluck('model');
        expect($families)->toContain('ZoneAdmin');
    });

    test('the second round of complementary mappings (Relation, Information, Workstation, Actor/PER_DEV) are all offered', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $familiesByAssetCode = $response->viewData('familiesByAssetCode');
        expect($familiesByAssetCode['OV_MAINTENANCE'])->toContain('Relation');
        expect($familiesByAssetCode['OV_INFOPHY'])->toContain('Information');
        expect($familiesByAssetCode['MAT_MOB'])->toContain('Workstation');
        expect($familiesByAssetCode['OV_MOBIL'])->toContain('Workstation');
        expect($familiesByAssetCode['PER_DEV'])->toContain('Actor');

        $families = collect($response->viewData('families'))->pluck('model');
        expect($families)->toContain('Relation');
    });

    test('the first column lists the Mercator families matching a row\'s Monarc asset code', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $viewSections = collect($response->viewData('viewSections'));
        $row = $viewSections->flatMap(fn ($section) => $section['rows'])->firstWhere('asset_uuid', 'uuid-app');
        expect($row['families'])->toBe(['Application']);

        $familyLabelByModel = $response->viewData('familyLabelByModel');
        expect($familyLabelByModel['Application'])->not->toBeEmpty();
        $response->assertSee($familyLabelByModel['Application'], false);
    });

    test('the Mercator family catalog is ordered like the sidebar (e.g. Entity before Relation)', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $models = collect($response->viewData('families'))->pluck('model');
        $entityIndex = $models->search('Entity');
        $relationIndex = $models->search('Relation');
        expect($entityIndex)->toBeLessThan($relationIndex);

        // ApplicationBlock is listed before Application in the sidebar too.
        $applicationBlockIndex = $models->search('ApplicationBlock');
        $applicationIndex = $models->search('Application');
        expect($applicationBlockIndex)->toBeLessThan($applicationIndex);
    });

    test('rows within a view section are ordered like the sidebar, not alphabetically by MONARC label', function () {
        Http::fake(function ($request) {
            $data = $request->data();

            return match ($data['schema'] ?? null) {
                // MONARC labels deliberately sort the "wrong" way alphabetically
                // (Zzz-Maintenance before Aaa-Generale) to prove the sort key is
                // sidebar family order, not the asset label.
                'Assets' => Http::response(['metadata' => ['count' => 2], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'uuid-maint', 'code' => 'OV_MAINTENANCE', 'label' => 'Zzz-Maintenance', 'description' => '', 'type' => 'Secondary']],
                    ['id' => 2, 'json_object' => ['uuid' => 'uuid-gen', 'code' => 'ORG_GEN', 'label' => 'Aaa-Generale', 'description' => '', 'type' => 'Secondary']],
                ]], 200),
                'Threats', 'Vulnerabilities', 'Risks', 'Security referentials' => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
                default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            };
        });

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $viewSections = collect($response->viewData('viewSections'));
        $ecosystemRows = $viewSections->firstWhere('key', 'ecosystem')['rows'];
        // Entity (ORG_GEN) must come before Relation (OV_MAINTENANCE), matching
        // the sidebar's "Entity before Relation" order, despite the reverse
        // alphabetical MONARC labels.
        expect(collect($ecosystemRows)->pluck('asset_code')->all())->toBe(['ORG_GEN', 'OV_MAINTENANCE']);
    });

    test('renders without a 500 when MOSP is unreachable', function () {
        Http::fake([
            'objects.monarc.lu/*' => Http::response([], 500),
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee(trans('cruds.monarc.no_rows_mosp'));
    });
});

describe('export', function () {
    beforeEach(function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
    });

    test('returns 404 when Monarc is disabled', function () {
        MonarcSettings::save(['enabled' => false, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);

        $response = $this->post(route('admin.monarc.export'), []);

        $response->assertNotFound();
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.monarc.export'), []);

        $response->assertForbidden();
    });

    test('rejects a submission with an empty rows array', function () {
        fakeMospBaseCatalog();

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'Test export',
            'language' => 'fr',
            'rows' => [],
        ]);

        $response->assertSessionHasErrors();
    });

    test('rejects a submission where no row maps any Mercator object', function () {
        fakeMospBaseCatalog();

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'Test export',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', trans('cruds.monarc.errors.empty_selection'));
    });

    test('streams a downloadable JSON file matching the selected objects, always as a full analysis', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'My Export',
            'description' => 'desc',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'local' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertHeader('content-disposition');
        expect($response->headers->get('content-disposition'))->toContain('monarc-my-export-');

        $content = $response->streamedContent();
        $json = json_decode($content, true);

        expect($json['type'])->toBe('anr');
        expect($json['withEval'])->toBeFalse();
        // Always a full analysis: the instance tree is built, never an empty library-only export.
        expect($json['instances'])->not->toBe([]);
        $objects = $json['library']['categories'][0]['objects'];
        expect($objects)->toHaveCount(1);
        expect($objects[0]['name'])->toBe('Urgences');
        expect($objects[0]['asset']['uuid'])->toBe('uuid-proc');
    });

    test('ignores Mercator objects not placed in any row global/local list', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);
        Application::factory()->create(['name' => 'MediLab']); // never assigned to a row

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'My Export',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'local' => ['Process:'.$process->id],
                ],
                'uuid-app' => [
                    'asset_uuid' => 'uuid-app',
                ],
            ],
        ]);

        $response->assertOk();
        $json = json_decode($response->streamedContent(), true);
        $names = collect($json['library']['categories'])->flatMap(fn ($c) => collect($c['objects'])->pluck('name'));
        expect($names->all())->toBe(['Urgences']);
    });

    test('rejects a Mercator object listed as both global and local on the same row', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'My Export',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'global' => ['Process:'.$process->id],
                    'local' => ['Process:'.$process->id],
                ],
            ],
        ]);

        // Server-side exclusivity: a key flagged both ways is dropped entirely
        // rather than guessed at, so the export ends up with nothing selected.
        $response->assertRedirect();
        $response->assertSessionHas('error', trans('cruds.monarc.errors.empty_selection'));
    });

    test('redirects back with a translated error when MOSP fails', function () {
        Http::fake([
            'objects.monarc.lu/*' => Http::response([], 500),
        ]);
        $process = Process::factory()->create();

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'Test export',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'local' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    });

    test('also persists the current selection state, so a later visit pre-fills it', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $this->post(route('admin.monarc.export'), [
            'name' => 'Saved via export',
            'description' => 'desc',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'local' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('Saved via export', false);
        $response->assertSee('"uuid-proc":{"asset_uuid":"uuid-proc","local":["Process:'.$process->id.'"]}', false);
    });
});

describe('selection state persistence', function () {
    beforeEach(function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
    });

    test('returns 404 when Monarc is disabled', function () {
        MonarcSettings::save(['enabled' => false, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);

        $response = $this->post(route('admin.monarc.save'), []);

        $response->assertNotFound();
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.monarc.save'), []);

        $response->assertForbidden();
    });

    test('saves an in-progress (partial, even empty) selection without requiring a name or any rows', function () {
        fakeMospBaseCatalog();

        $response = $this->post(route('admin.monarc.save'), [
            'name' => '',
            'rows' => [],
        ]);

        $response->assertRedirect(route('admin.monarc'));
        $response->assertSessionHas('success', trans('cruds.monarc.save_success'));
    });

    test('a saved selection pre-fills name, description, language and referentials on the next visit', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $this->post(route('admin.monarc.save'), [
            'name' => 'Draft analysis',
            'description' => 'Draft description',
            'language' => 'en',
            'mosp_referentials' => [],
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'local' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('value="Draft analysis"', false);
        $response->assertSee('value="Draft description"', false);
        $response->assertSee('<option value="en" selected>English</option>', false);
        // The saved row selection is embedded for the JS to restore into the Select2.
        $response->assertSee('"uuid-proc":{"asset_uuid":"uuid-proc","local":["Process:'.$process->id.'"]}', false);
    });

    test('a GET visit always reflects the last saved/exported referentials (no separate "apply" reload)', function () {
        fakeMospBaseCatalog();

        $this->post(route('admin.monarc.save'), [
            'name' => '',
            'mosp_referentials' => [42],
            'rows' => [],
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        expect($response->viewData('selectedReferentialIds'))->toBe([42]);
    });
});

describe('parameters tab persistence', function () {
    test('saving the Monarc tab persists a single encrypted parameters row', function () {
        $response = $this->put(route('admin.config.parameters'), [
            'active_tab' => 'monarc',
            'action' => 'save',
            'enabled' => '1',
            'url' => 'http://monarc.local',
            'uid' => 'admin',
            'password' => 'sup3rSecret',
        ]);

        $response->assertRedirect();

        $rows = Parameter::query()->where('name', 'monarc')->get();
        expect($rows)->toHaveCount(1);

        $stored = json_decode($rows->first()->value, true);
        expect($stored['enabled'])->toBeTrue();
        expect($stored['url'])->toBe('http://monarc.local');
        expect($stored['uid'])->toBe('admin');
        expect($stored['password'])->not->toBe('sup3rSecret');
        expect(Crypt::decryptString(substr($stored['password'], strlen('enc:'))))->toBe('sup3rSecret');

        expect(config('monarc.enabled'))->toBeTrue();
        expect(MonarcSettings::password())->toBe('sup3rSecret');
    });

    test('submitting an empty password keeps the existing one', function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'firstPassword']);

        $this->put(route('admin.config.parameters'), [
            'active_tab' => 'monarc',
            'action' => 'save',
            'enabled' => '1',
            'url' => 'http://monarc.local',
            'uid' => 'admin',
            'password' => '',
        ]);

        expect(MonarcSettings::password())->toBe('firstPassword');
    });

    test('unchecking enabled disables the integration', function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);

        $this->put(route('admin.config.parameters'), [
            'active_tab' => 'monarc',
            'action' => 'save',
            'url' => 'http://monarc.local',
            'uid' => 'admin',
            'password' => '',
        ]);

        expect(MonarcSettings::enabled())->toBeFalse();
    });

    test('rejects an invalid URL', function () {
        $response = $this->put(route('admin.config.parameters'), [
            'active_tab' => 'monarc',
            'action' => 'save',
            'enabled' => '1',
            'url' => 'not-a-url',
            'uid' => 'admin',
            'password' => 'x',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        expect(Parameter::query()->where('name', 'monarc')->exists())->toBeFalse();
    });
});

describe('MonarcSettings::applyToConfig', function () {
    test('merges the stored row on top of config/monarc.php defaults', function () {
        Parameter::setValue('monarc', json_encode([
            'enabled' => true,
            'url' => 'http://monarc.local',
            'uid' => 'admin',
            'password' => 'enc:whatever',
        ]));

        // Simulate a fresh boot: reset in-memory config to raw defaults first.
        config(['monarc' => require config_path('monarc.php')]);
        MonarcSettings::applyToConfig();

        expect(config('monarc.enabled'))->toBeTrue();
        expect(config('monarc.url'))->toBe('http://monarc.local');
        expect(config('monarc.cache_ttl'))->toBe(300);
    });
});
