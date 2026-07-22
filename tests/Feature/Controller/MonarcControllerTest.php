<?php

use App\Models\Application;
use App\Models\MonarcSyncItem;
use App\Models\Parameter;
use App\Models\Process;
use App\Models\User;
use App\Support\MonarcSelectionState;
use App\Support\MonarcSettings;
use App\Support\MonarcSyncState;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

/**
 * Combines fakeMospBaseCatalog()'s schema-matching closure (used for the
 * knowledgeBase reload the sync action performs) with URL-keyed fakes for
 * the Monarc FO endpoints hit by MonarcApiService (auth/create-anr/import) —
 * a plain fakeMospBaseCatalog() alone would swallow those calls too, since
 * it registers a single catch-all closure.
 */
function fakeMonarcSyncEndpoints(array $extra = []): void
{
    Http::fake(array_merge([
        'monarc.local/auth' => Http::response(['token' => 'tok', 'uid' => 1, 'language' => 1], 200),
        'monarc.local/api/client-anr' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['status' => 'ok', 'id' => 18], 200);
            }

            return Http::response(['count' => 1, 'anrs' => [['id' => 18, 'label' => 'Analysis']]], 200);
        },
        'monarc.local/api/client-anr/18/instances/import' => Http::response(['id' => [1], 'errors' => []], 200),
        'monarc.local/api/client-anr/*/export' => Http::response(['languageCode' => 'fr', 'knowledgeBase' => []], 200),
        '*' => function ($request) {
            $data = $request->data();

            return match ($data['schema'] ?? null) {
                'Assets' => Http::response(['metadata' => ['count' => 2], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'uuid-proc', 'code' => 'PROC', 'label' => 'Processus', 'description' => '', 'type' => 'Primary']],
                    ['id' => 2, 'json_object' => ['uuid' => 'uuid-app', 'code' => 'LOG_APP', 'label' => 'Application métier', 'description' => '', 'type' => 'Secondary']],
                ]], 200),
                'Risks' => Http::response(['metadata' => ['count' => 2], 'data' => [
                    ['id' => 1, 'json_object' => ['uuid' => 'amv1', 'asset' => 'uuid-app', 'threat' => 't1', 'vulnerability' => 'v1', 'measures' => []]],
                    ['id' => 2, 'json_object' => ['uuid' => 'amv2', 'asset' => 'uuid-app', 'threat' => 't1', 'vulnerability' => 'v1', 'measures' => []]],
                ]], 200),
                default => Http::response(['metadata' => ['count' => 0], 'data' => []], 200),
            };
        },
    ], $extra));
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
                    'objects' => ['Process:'.$process->id],
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

    test('a row flagged "generic" adds one placeholder object named after its Monarc asset, with the full asset risk profile', function () {
        fakeMospBaseCatalog();

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'My Export',
            'language' => 'fr',
            'rows' => [
                'uuid-app' => [
                    'asset_uuid' => 'uuid-app',
                    'generic' => '1',
                ],
            ],
        ]);

        $response->assertOk();
        $json = json_decode($response->streamedContent(), true);

        $objects = collect($json['library']['categories'])->flatMap(fn ($c) => $c['objects']);
        expect($objects)->toHaveCount(1);
        expect($objects->first()['name'])->toBe('Application métier');
        expect($objects->first()['asset']['uuid'])->toBe('uuid-app');

        // Promoted straight to its own root instance (same as any orphan
        // cartography object), carrying every AMV of its own asset type.
        expect($json['instances'])->toHaveCount(1);
        $instance = $json['instances'][0];
        expect($instance['name'])->toBe('Application métier');
        expect($instance['level'])->toBe(1);
        expect($instance['children'])->toBe([]);
        expect($instance['instanceRisks'])->toHaveCount(2);
    });

    test('a "generic" row ignores any individually selected objects for that same row', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'My Export',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'generic' => '1',
                    'objects' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response->assertOk();
        $json = json_decode($response->streamedContent(), true);

        $names = collect($json['library']['categories'])->flatMap(fn ($c) => collect($c['objects'])->pluck('name'));
        expect($names->all())->toBe(['Processus']); // the generic placeholder, not "Urgences"
    });

    test('ignores Mercator objects not placed in any row object list', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);
        Application::factory()->create(['name' => 'MediLab']); // never assigned to a row

        $response = $this->post(route('admin.monarc.export'), [
            'name' => 'My Export',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'objects' => ['Process:'.$process->id],
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
                    'objects' => ['Process:'.$process->id],
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
                    'objects' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('Saved via export', false);
        $response->assertSee('"uuid-proc":{"asset_uuid":"uuid-proc","objects":["Process:'.$process->id.'"]}', false);
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
                    'objects' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('value="Draft analysis"', false);
        $response->assertSee('value="Draft description"', false);
        $response->assertSee('<option value="en" selected>English</option>', false);
        // The saved row selection is embedded for the JS to restore into the Select2.
        $response->assertSee('"uuid-proc":{"asset_uuid":"uuid-proc","objects":["Process:'.$process->id.'"]}', false);
    });

    test('a draft saved before the global/local simplification still restores its objects', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);
        $application = Application::factory()->create(['name' => 'MediLab']);

        // Simulates a row saved by the old two-list UI, persisted directly
        // (bypassing saveSelection()'s current validation, which no longer
        // accepts "global"/"local") to reproduce a pre-existing draft.
        Parameter::setValue('monarc_selection', json_encode([
            'name' => 'Old draft',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'local' => ['Process:'.$process->id],
                ],
                'uuid-app' => [
                    'asset_uuid' => 'uuid-app',
                    'global' => ['Application:'.$application->id],
                ],
            ],
        ]));

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        // Both the old "local" and "global" rows merge into "objects".
        $response->assertSee('"uuid-proc":{"asset_uuid":"uuid-proc","objects":["Process:'.$process->id.'"]}', false);
        $response->assertSee('"uuid-app":{"asset_uuid":"uuid-app","objects":["Application:'.$application->id.'"]}', false);
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

describe('form initial display sourced solely from MonarcSelectionState', function () {
    beforeEach(function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
    });

    test('renders a blank form with no errors on first use (no saved selection at all)', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertViewIs('admin.monarc');
        expect($response->viewData('apiError'))->toBeNull();
        expect($response->viewData('initialAnrName'))->toBe('');
        expect($response->viewData('savedState'))->toBe([]);
        expect($response->viewData('savedRows'))->toBe([]);
        expect($response->viewData('selectedReferentialIds'))->toBe([]);
        $response->assertSee('<option value="fr" selected>Français</option>', false);
    });

    test('populates every field (name, description, language, referentials, rows) from MonarcSelectionState, ignoring an unrelated MonarcSyncState', function () {
        fakeMospBaseCatalog();
        $process = Process::factory()->create(['name' => 'Urgences']);

        // A linked ANR that names nothing this test's selection draft uses —
        // proves the form never leaks values from it.
        MonarcSyncState::save(['anr_id' => 999, 'anr_label' => 'Totally different ANR', 'model_id' => 5, 'last_synced_at' => now()->toIso8601String()]);
        MonarcSelectionState::save([
            'name' => 'My draft',
            'description' => 'My description',
            'language' => 'en',
            'mosp_referentials' => [],
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc', 'objects' => ['Process:'.$process->id]],
            ],
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        expect($response->viewData('initialAnrName'))->toBe('My draft');
        $response->assertSee('value="My description"', false);
        $response->assertSee('<option value="en" selected>English</option>', false);
        expect($response->viewData('selectedReferentialIds'))->toBe([]);
        expect($response->viewData('savedRows')['uuid-proc']['objects'])->toBe(['Process:'.$process->id]);
        // The unrelated linked ANR's label legitimately appears in the
        // separate status card (driven by MonarcSyncState, by design — see
        // Phase 1) — it must simply never be used as the FORM FIELD's own
        // value, which is the specific thing this test is about.
        $response->assertDontSee('value="Totally different ANR"', false);
    });
});

describe('synchronization', function () {
    beforeEach(function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
    });

    test('returns 404 when Monarc is disabled', function () {
        MonarcSettings::save(['enabled' => false, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);

        $response = $this->post(route('admin.monarc.sync'), []);

        $response->assertNotFound();
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.monarc.sync'), []);

        $response->assertForbidden();
    });

    test('rejects a submission with an empty rows array', function () {
        fakeMonarcSyncEndpoints();

        $response = $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'language' => 'fr',
            'rows' => [],
        ]);

        $response->assertStatus(422);
    });

    test('rejects a submission where no row maps any Mercator object', function () {
        fakeMonarcSyncEndpoints();

        $response = $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'language' => 'fr',
            'model_id' => 31,
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'error', 'message' => trans('cruds.monarc.errors.empty_selection')]);
    });

    test('creates the ANR and imports the current selection on first use', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'description' => 'desc',
            'language' => 'fr',
            'model_id' => 31,
            'anr_label' => 'My new analysis',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'objects' => ['Process:'.$process->id],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'synced', 'anr_id' => 18, 'created' => true, 'sent_count' => 1]);

        expect(MonarcSyncState::anrId())->toBe(18);
        expect(MonarcSyncItem::query()->where('anr_id', 18)->count())->toBe(1);
    });

    test('links directly to an existing ANR selected from the combobox, without creating a new one', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'language' => 'fr',
            'anr_id' => 18,
            'anr_label' => 'Analysis',
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc', 'objects' => ['Process:'.$process->id]],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'synced', 'anr_id' => 18, 'created' => false, 'sent_count' => 1]);
        expect(MonarcSyncState::anrId())->toBe(18);

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/client-anr') && $request->method() === 'POST');
    });

    test('switching to a different existing ANR while already linked re-targets the sync, without creating one', function () {
        fakeMonarcSyncEndpoints([
            'monarc.local/api/client-anr' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['status' => 'ok', 'id' => 99], 200);
                }

                return Http::response(['count' => 2, 'anrs' => [
                    ['id' => 18, 'label' => 'Analysis'],
                    ['id' => 25, 'label' => 'Other analysis'],
                ]], 200);
            },
            'monarc.local/api/client-anr/25/instances/import' => Http::response(['id' => [1], 'errors' => []], 200),
        ]);
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'Analysis', 'model_id' => null, 'last_synced_at' => now()->toIso8601String()]);
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'language' => 'fr',
            'anr_id' => 25,
            'anr_label' => 'Other analysis',
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc', 'objects' => ['Process:'.$process->id]],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'synced', 'anr_id' => 25, 'created' => false]);
        expect(MonarcSyncState::anrId())->toBe(25);

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/client-anr') && $request->method() === 'POST');
    });

    test('the sync selectors stay visible and the status card reflects the linked ANR', function () {
        fakeMonarcSyncEndpoints();
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'Analysis', 'model_id' => 31, 'last_synced_at' => now()->toIso8601String()]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('id="monarc_anr_select"', false);
        $response->assertSee('id="monarc_model_id"', false);
        // The status card (ANR liée) is driven by MonarcSyncState, unlike the form field itself.
        $response->assertSee(trans('cruds.monarc.sync.anr_linked', ['id' => 18, 'label' => 'Analysis']));
    });

    test('the merged name/ANR field is pre-selected from MonarcSelectionState alone, not MonarcSyncState', function () {
        fakeMonarcSyncEndpoints();
        // A linked ANR (id 18, "Analysis") that the saved selection does NOT name.
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'Analysis', 'model_id' => 31, 'last_synced_at' => now()->toIso8601String()]);
        MonarcSelectionState::save(['name' => 'Other analysis', 'rows' => []]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        // The field reflects the saved selection's own name, never the linked ANR's label.
        $response->assertSee('value="Other analysis"', false);
        $response->assertDontSee('value="18" selected', false);
    });

    test('warns when the linked ANR was deleted directly in Monarc, without waiting for the next sync attempt', function () {
        fakeMonarcSyncEndpoints([
            'monarc.local/api/client-anr' => Http::response(['count' => 0, 'anrs' => []], 200),
        ]);
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'Analysis', 'model_id' => 31, 'last_synced_at' => now()->toIso8601String()]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        expect($response->viewData('linkedAnrMissing'))->toBeTrue();
        $response->assertSee(trans('cruds.monarc.sync.errors.anr_missing'));
    });

    test('does not warn about a linked ANR that still exists but is merely absent from the anrs list', function () {
        $getCalls = 0;
        fakeMonarcSyncEndpoints([
            'monarc.local/api/client-anr' => function ($request) use (&$getCalls) {
                if ($request->method() === 'POST') {
                    return Http::response(['status' => 'ok', 'id' => 18], 200);
                }

                $getCalls++;

                // getAnrs()'s list omits the linked ANR (e.g. Monarc-side
                // pagination/visibility rules), but anrExists() — the
                // dedicated, single-ANR lookup a real sync relies on — still
                // finds it: exactly the scenario reported where a sync
                // succeeds despite this page showing a false "ANR missing" warning.
                return $getCalls === 1
                    ? Http::response(['count' => 0, 'anrs' => []], 200)
                    : Http::response(['count' => 1, 'anrs' => [['id' => 18, 'label' => 'Analysis']]], 200);
            },
        ]);
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'Analysis', 'model_id' => 31, 'last_synced_at' => now()->toIso8601String()]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        expect($response->viewData('linkedAnrMissing'))->toBeFalse();
        $response->assertDontSee(trans('cruds.monarc.sync.errors.anr_missing'));
    });

    test('does not warn about a missing ANR when the anrs list itself failed to load', function () {
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'Analysis', 'model_id' => 31, 'last_synced_at' => now()->toIso8601String()]);
        Http::fake(['*' => Http::response([], 500)]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        expect($response->viewData('linkedAnrMissing'))->toBeFalse();
    });

    test('rejects a sync with neither an existing ANR nor a new label', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'language' => 'fr',
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc', 'objects' => ['Process:'.$process->id]],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'error', 'message' => trans('cruds.monarc.sync.errors.anr_choice_required')]);
        expect(MonarcSyncState::anrId())->toBeNull();
    });

    test('a repeat sync with no cartography change reports up_to_date and sends nothing', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $payload = [
            'name' => 'Test analysis',
            'language' => 'fr',
            'model_id' => 31,
            'anr_label' => 'My new analysis',
            'rows' => [
                'uuid-proc' => [
                    'asset_uuid' => 'uuid-proc',
                    'objects' => ['Process:'.$process->id],
                ],
            ],
        ];

        $this->postJson(route('admin.monarc.sync'), $payload);
        $response = $this->postJson(route('admin.monarc.sync'), $payload);

        $response->assertOk();
        $response->assertJson(['status' => 'up_to_date', 'sent_count' => 0]);
        expect(MonarcSyncItem::query()->count())->toBe(1);
    });

    test('re-syncing a "generic" row does not duplicate its placeholder object', function () {
        fakeMonarcSyncEndpoints();

        $payload = [
            'name' => 'Test analysis',
            'language' => 'fr',
            'model_id' => 31,
            'anr_label' => 'My new analysis',
            'rows' => [
                'uuid-app' => ['asset_uuid' => 'uuid-app', 'generic' => '1'],
            ],
        ];

        $first = $this->postJson(route('admin.monarc.sync'), $payload);
        $first->assertJson(['status' => 'synced', 'sent_count' => 1]);

        $second = $this->postJson(route('admin.monarc.sync'), $payload);
        $second->assertJson(['status' => 'up_to_date', 'sent_count' => 0]);

        expect(MonarcSyncItem::query()->count())->toBe(1);
    });

    test('resets the local link and every tracked item without deleting the remote ANR', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'language' => 'fr',
            'model_id' => 31,
            'anr_label' => 'My new analysis',
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc', 'objects' => ['Process:'.$process->id]],
            ],
        ]);

        expect(MonarcSyncState::anrId())->toBe(18);

        $response = $this->post(route('admin.monarc.sync.reset'));

        $response->assertRedirect(route('admin.config.parameters').'?tab=monarc');
        $response->assertSessionHas('success');
        expect(MonarcSyncState::anrId())->toBeNull();
        expect(MonarcSyncItem::query()->count())->toBe(0);

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/client-anr/18') && $request->method() === 'DELETE');
    });

    test('resetSync returns 404 when Monarc is disabled', function () {
        MonarcSettings::save(['enabled' => false, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);

        $response = $this->post(route('admin.monarc.sync.reset'));

        $response->assertNotFound();
    });

    test('the selection screen renders the sync card with the linked ANR state', function () {
        fakeMonarcSyncEndpoints();
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'Test analysis', 'model_id' => 31, 'last_synced_at' => now()->toIso8601String()]);
        MonarcSyncItem::query()->create(['anr_id' => 18, 'model' => 'Process', 'mercator_id' => 1, 'object_uuid' => (string) Str::uuid(), 'sent_at' => now()]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee(trans('cruds.monarc.sync.title'));
        expect($response->viewData('syncState')['anr_id'])->toBe(18);
        expect($response->viewData('syncedItemsCount'))->toBe(1);
    });
});

describe('restoring row selection from an already-synced ANR', function () {
    beforeEach(function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
    });

    test('returns 404 when Monarc is disabled', function () {
        MonarcSettings::save(['enabled' => false, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);

        $response = $this->get(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertNotFound();
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertForbidden();
    });

    test('rebuilds the row selection (objects and generic placeholders) from monarc_sync_items', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);

        MonarcSyncItem::query()->create(['anr_id' => 18, 'model' => 'Process', 'mercator_id' => $process->id, 'object_uuid' => (string) Str::uuid(), 'sent_at' => now()]);
        MonarcSyncItem::query()->create(['anr_id' => 18, 'model' => 'MonarcGenericType:uuid-app', 'mercator_id' => 0, 'object_uuid' => (string) Str::uuid(), 'sent_at' => now()]);
        // A different ANR's tracked items must never leak into this one's restore.
        MonarcSyncItem::query()->create(['anr_id' => 99, 'model' => 'Process', 'mercator_id' => $process->id, 'object_uuid' => (string) Str::uuid(), 'sent_at' => now()]);

        $response = $this->getJson(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertOk();
        $response->assertJson(['status' => 'ok', 'anr_id' => 18, 'name' => 'Analysis', 'language' => 'fr']);
        $rows = $response->json('rows');

        expect($rows['uuid-proc'])->toBe([
            'asset_uuid' => 'uuid-proc',
            'objects' => ['Process:'.$process->id],
        ]);
        expect($rows['uuid-app'])->toBe([
            'asset_uuid' => 'uuid-app',
            'generic' => true,
        ]);

        // Only this ANR's 2 items count, not the other ANR's leaked-in item.
        expect($response->json('synced_count'))->toBe(2);
        expect($response->json('last_synced_at'))->not->toBeNull();
    });

    test('skips a tracked object whose Mercator record was deleted since', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);
        $deletedId = $process->id + 1000;

        MonarcSyncItem::query()->create(['anr_id' => 18, 'model' => 'Process', 'mercator_id' => $deletedId, 'object_uuid' => (string) Str::uuid(), 'sent_at' => now()]);

        $response = $this->getJson(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertOk();
        expect($response->json('rows'))->toBe([]);
    });

    test('merges the ANR label/language/rows into MonarcSelectionState, keeping description and referentials', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);
        MonarcSyncItem::query()->create(['anr_id' => 18, 'model' => 'Process', 'mercator_id' => $process->id, 'object_uuid' => (string) Str::uuid(), 'sent_at' => now()]);
        MonarcSelectionState::save([
            'name' => 'Old draft name',
            'description' => 'My description',
            'language' => 'en',
            'mosp_referentials' => [],
            'rows' => ['some-stale-row' => ['asset_uuid' => 'x', 'objects' => []]],
        ]);

        $response = $this->getJson(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertOk();
        $state = MonarcSelectionState::load();
        expect($state['name'])->toBe('Analysis'); // the ANR's own label, overwritten
        expect($state['language'])->toBe('fr'); // the ANR's own language, overwritten
        expect($state['rows'])->toHaveKey('uuid-proc'); // rebuilt from monarc_sync_items, overwritten
        expect($state['description'])->toBe('My description'); // untouched
        expect($state['mosp_referentials'])->toBe([]); // untouched
    });

    test('returns 404 and leaves MonarcSelectionState untouched when the ANR no longer exists', function () {
        fakeMonarcSyncEndpoints([
            'monarc.local/api/client-anr' => Http::response(['count' => 0, 'anrs' => []], 200),
        ]);
        MonarcSelectionState::save(['name' => 'Untouched', 'rows' => []]);

        $response = $this->getJson(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertStatus(404);
        $response->assertJson(['status' => 'error', 'message' => trans('cruds.monarc.sync.errors.anr_not_found')]);
        expect(MonarcSelectionState::load()['name'])->toBe('Untouched');
    });

    test('leaves MonarcSelectionState untouched when the Monarc API call fails', function () {
        MonarcSelectionState::save(['name' => 'Untouched', 'rows' => []]);
        Http::fake(['*' => Http::response([], 500)]);

        $response = $this->getJson(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertStatus(422);
        expect(MonarcSelectionState::load()['name'])->toBe('Untouched');
    });

    test('leaves MonarcSelectionState untouched when the language lookup fails after the ANR was found', function () {
        MonarcSelectionState::save(['name' => 'Untouched', 'rows' => []]);
        Http::fake([
            'monarc.local/auth' => Http::response(['token' => 'tok', 'uid' => 1, 'language' => 1], 200),
            'monarc.local/api/client-anr' => Http::response(['count' => 1, 'anrs' => [['id' => 18, 'label' => 'Analysis']]], 200),
            'monarc.local/api/client-anr/18/export' => Http::response(['errors' => ['boom']], 500),
        ]);

        $response = $this->getJson(route('admin.monarc.anr-rows', ['anrId' => 18]));

        $response->assertStatus(422);
        expect(MonarcSelectionState::load()['name'])->toBe('Untouched');
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

describe('reset link relocated to the parameters Monarc tab', function () {
    beforeEach(function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
    });

    test('the selection screen no longer renders a reset button', function () {
        fakeMospBaseCatalog();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertDontSee('monarc-sync-reset-btn', false);
        $response->assertDontSee(route('admin.monarc.sync.reset'), false);
    });

    test('the parameters Monarc tab renders the reset button, help text and link status', function () {
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'My analysis', 'model_id' => 31, 'last_synced_at' => now()->toIso8601String()]);
        MonarcSyncItem::query()->create(['model' => 'Process', 'mercator_id' => 1, 'object_uuid' => (string) Str::uuid(), 'anr_id' => 18, 'sent_at' => now()]);

        $response = $this->get(route('admin.config.parameters', ['tab' => 'monarc']));

        $response->assertOk();
        $response->assertSee(trans('cruds.monarc.sync.reset_button'));
        $response->assertSee(trans('cruds.configuration.monarc.link_reset_help'));
        $response->assertSee(trans('cruds.monarc.sync.anr_linked', ['id' => 18, 'label' => 'My analysis']));
        $response->assertSee(trans('cruds.monarc.sync.synced_count', ['count' => 1]));
    });

    test('the reset button is disabled when no ANR is linked', function () {
        $response = $this->get(route('admin.config.parameters', ['tab' => 'monarc']));

        $response->assertOk();
        $response->assertSee(trans('cruds.monarc.sync.anr_none'));
    });

    test('submitting the reset form redirects back to the parameters Monarc tab with a success message', function () {
        fakeMonarcSyncEndpoints();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $this->postJson(route('admin.monarc.sync'), [
            'name' => 'Test analysis',
            'language' => 'fr',
            'model_id' => 31,
            'anr_label' => 'My new analysis',
            'rows' => [
                'uuid-proc' => ['asset_uuid' => 'uuid-proc', 'objects' => ['Process:'.$process->id]],
            ],
        ]);

        expect(MonarcSyncState::anrId())->toBe(18);

        $response = $this->post(route('admin.monarc.sync.reset'));

        $response->assertRedirect(route('admin.config.parameters').'?tab=monarc');
        $response->assertSessionHas('success', trans('cruds.monarc.sync.reset_success'));
        expect(MonarcSyncState::anrId())->toBeNull();
        expect(MonarcSyncItem::query()->count())->toBe(0);
    });

    test('denies the reset action without the configure permission', function () {
        MonarcSyncState::save(['anr_id' => 18, 'anr_label' => 'My analysis', 'model_id' => 31]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.monarc.sync.reset'));

        $response->assertForbidden();
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
