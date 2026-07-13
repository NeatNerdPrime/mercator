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

function fakeMonarcSelectionPage(): void
{
    Http::fake([
        '*/auth' => Http::response(['token' => 'tok', 'uid' => 1, 'language' => 1], 200),
        '*/api/client-anr' => Http::response(['count' => 1, 'anrs' => [['id' => 3, 'label' => 'HIS']]], 200),
        '*/api/client-anr/*/export' => Http::response([
            'knowledgeBase' => [
                'assets' => [
                    ['uuid' => 'uuid-proc', 'code' => 'PROC', 'label' => 'Processus', 'description' => '', 'type' => 1, 'status' => 1],
                    ['uuid' => 'uuid-app', 'code' => 'LOG_APP', 'label' => 'Application métier', 'description' => '', 'type' => 2, 'status' => 1],
                ],
                'threats' => [],
                'vulnerabilities' => [],
                'referentials' => [],
                'informationRisks' => [
                    ['uuid' => 'amv1', 'asset' => ['uuid' => 'uuid-app'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
                    ['uuid' => 'amv2', 'asset' => ['uuid' => 'uuid-app'], 'threat' => ['uuid' => 't1'], 'vulnerability' => ['uuid' => 'v1'], 'measures' => [], 'status' => 1],
                ],
                'rolfTags' => [],
                'operationalRisks' => [],
                'recommendationSets' => [],
            ],
            'scales' => [],
            'operationalRiskScales' => [],
            'method' => [],
            'thresholds' => [],
            'soas' => [],
            'soaScaleComments' => [],
            'monarc_version' => '2.13.3',
            'languageCode' => 'fr',
        ], 200),
    ]);
}

describe('activation gate', function () {
    test('GET admin/monarc returns 404 when Monarc is disabled', function () {
        $response = $this->get(route('admin.monarc'));

        $response->assertNotFound();
    });

    test('GET admin/monarc returns 200 when Monarc is enabled', function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => 'x']);
        fakeMonarcSelectionPage();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertViewIs('admin.monarc');
    });

    test('denies access without permission even when enabled', function () {
        MonarcSettings::save(['enabled' => true, 'url' => 'http://monarc.local', 'uid' => 'admin', 'password' => '']);

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

    test('lists Mercator objects grouped by family with the default asset pre-selected', function () {
        fakeMonarcSelectionPage();
        $process = Process::factory()->create(['name' => 'Zzz Process']);
        $application = Application::factory()->create(['name' => 'Aaa App']);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee($process->name);
        $response->assertSee($application->name);
        // Process's default asset code PROC -> uuid-proc must be pre-selected.
        expect($response->getContent())->toMatch('/value="uuid-proc"\s+selected/');
    });

    test('embeds the AMV-count-per-asset map as JSON for the client-side counter', function () {
        fakeMonarcSelectionPage();

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee('"uuid-app":2', false);
    });

    test('renders without a 500 when the Monarc API is unreachable', function () {
        Http::fake([
            '*/auth' => Http::response([], 401),
        ]);

        $response = $this->get(route('admin.monarc'));

        $response->assertOk();
        $response->assertSee(trans('cruds.configuration.monarc.error_auth_failed'));
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

    test('rejects a submission with no selected object', function () {
        fakeMonarcSelectionPage();

        $response = $this->post(route('admin.monarc.export'), [
            'anr_id' => 3,
            'name' => 'Test export',
            'language' => 'fr',
            'mode' => 'library',
            'selection' => [],
        ]);

        $response->assertSessionHasErrors();
    });

    test('streams a downloadable JSON file matching the selected library objects', function () {
        fakeMonarcSelectionPage();
        $process = Process::factory()->create(['name' => 'Urgences']);

        $response = $this->post(route('admin.monarc.export'), [
            'anr_id' => 3,
            'name' => 'My Export',
            'description' => 'desc',
            'language' => 'fr',
            'mode' => 'library',
            'selection' => [
                'Process:'.$process->id => [
                    'checked' => '1',
                    'model' => 'Process',
                    'id' => $process->id,
                    'asset_uuid' => 'uuid-proc',
                    'scope' => '1',
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
        expect($json['instances'])->toBe([]);
        $objects = $json['library']['categories'][0]['objects'];
        expect($objects)->toHaveCount(1);
        expect($objects[0]['name'])->toBe('Urgences');
        expect($objects[0]['asset']['uuid'])->toBe('uuid-proc');
    });

    test('ignores unchecked rows even if they carry asset/scope data', function () {
        fakeMonarcSelectionPage();
        $process = Process::factory()->create(['name' => 'Urgences']);
        $application = Application::factory()->create(['name' => 'MediLab']);

        $response = $this->post(route('admin.monarc.export'), [
            'anr_id' => 3,
            'name' => 'My Export',
            'language' => 'fr',
            'mode' => 'library',
            'selection' => [
                'Process:'.$process->id => [
                    'checked' => '1',
                    'model' => 'Process',
                    'id' => $process->id,
                    'asset_uuid' => 'uuid-proc',
                    'scope' => '1',
                ],
                'Application:'.$application->id => [
                    'model' => 'Application',
                    'id' => $application->id,
                    'asset_uuid' => 'uuid-app',
                    'scope' => '1',
                ],
            ],
        ]);

        $response->assertOk();
        $json = json_decode($response->streamedContent(), true);
        $names = collect($json['library']['categories'])->flatMap(fn ($c) => collect($c['objects'])->pluck('name'));
        expect($names->all())->toBe(['Urgences']);
    });

    test('redirects back with a translated error when the Monarc API fails', function () {
        Http::fake([
            '*/auth' => Http::response([], 401),
        ]);
        $process = Process::factory()->create();

        $response = $this->post(route('admin.monarc.export'), [
            'anr_id' => 3,
            'name' => 'Test export',
            'language' => 'fr',
            'mode' => 'library',
            'selection' => [
                'Process:'.$process->id => [
                    'checked' => '1',
                    'model' => 'Process',
                    'id' => $process->id,
                    'asset_uuid' => 'uuid-proc',
                    'scope' => '1',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
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
