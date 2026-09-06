<?php

use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Database;
use App\Models\Entity;
use App\Models\User;
use App\Services\Cairn\CairnApplicationDiagramService;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;

beforeEach(function () {
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    $this->admin = User::query()->where('login', 'admin@admin.com')->first();
});

describe('authorization', function () {
    test('search is forbidden without cairn_access', function () {
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/cairn/search?type=application&search=a')
            ->assertForbidden();
    });

    test('generate is forbidden without cairn_access', function () {
        $this->actingAs(User::factory()->create())
            ->postJson('/admin/cairn/generate', ['selection' => []])
            ->assertForbidden();
    });

    test('search is forbidden for a guest', function () {
        $this->getJson('/admin/cairn/search?type=application&search=a')
            ->assertUnauthorized();
    });
});

describe('search', function () {
    test('finds applications by name', function () {
        $match = Application::factory()->create(['name' => 'Findable App', 'external' => null]);
        Application::factory()->create(['name' => 'Nope', 'external' => null]);

        $this->actingAs($this->admin)
            ->getJson('/admin/cairn/search?type=application&search=findable')
            ->assertOk()
            ->assertExactJson([['id' => $match->id, 'text' => 'Findable App']]);
    });

    test('finds entities by name', function () {
        $entity = Entity::factory()->create(['name' => 'Acme Corp']);

        $this->actingAs($this->admin)
            ->getJson('/admin/cairn/search?type=entity&search=acme')
            ->assertOk()
            ->assertExactJson([['id' => $entity->id, 'text' => 'Acme Corp']]);
    });

    test('finds services, modules, databases and flows by type', function () {
        $service = ApplicationService::factory()->create(['name' => 'Svc Findme']);
        $module = ApplicationModule::factory()->create(['name' => 'Mod Findme']);
        $database = Database::factory()->create(['name' => 'Db Findme', 'external' => null]);
        $flow = ApplicationFlow::factory()->create(['name' => 'Flow Findme']);

        $this->actingAs($this->admin);

        $this->getJson('/admin/cairn/search?type=service&search=findme')
            ->assertOk()->assertExactJson([['id' => $service->id, 'text' => 'Svc Findme']]);

        $this->getJson('/admin/cairn/search?type=module&search=findme')
            ->assertOk()->assertExactJson([['id' => $module->id, 'text' => 'Mod Findme']]);

        $this->getJson('/admin/cairn/search?type=database&search=findme')
            ->assertOk()->assertExactJson([['id' => $database->id, 'text' => 'Db Findme']]);

        $this->getJson('/admin/cairn/search?type=flux&search=findme')
            ->assertOk()->assertExactJson([['id' => $flow->id, 'text' => 'Flow Findme']]);
    });

    test('returns an empty array for an unknown type', function () {
        $this->actingAs($this->admin)
            ->getJson('/admin/cairn/search?type=bogus&search=a')
            ->assertOk()
            ->assertExactJson([]);
    });
});

describe('generate', function () {
    test('returns empty for an empty selection', function () {
        $this->actingAs($this->admin)
            ->postJson('/admin/cairn/generate', ['selection' => []])
            ->assertOk()
            ->assertExactJson(['empty' => true]);
    });

    test('rejects an invalid type', function () {
        $this->actingAs($this->admin)
            ->postJson('/admin/cairn/generate', ['selection' => [['type' => 'bogus', 'id' => 1]]])
            ->assertStatus(422);
    });

    test('returns the exact DSL produced by the service for a given selection', function () {
        $app = Application::factory()->create(['name' => 'Direct App', 'external' => null]);
        $service = ApplicationService::factory()->create(['name' => 'Direct Service']);
        $app->services()->attach($service);

        $response = $this->actingAs($this->admin)
            ->postJson('/admin/cairn/generate', ['selection' => [['type' => 'application', 'id' => $app->id]]])
            ->assertOk();

        $expected = (new CairnApplicationDiagramService)
            ->build([['type' => 'application', 'id' => $app->id]]);

        expect($response->json('dsl'))->toBe($expected)
            ->and($response->json('dsl'))
            ->toContain('application APP_'.$app->id.' "Direct App"')
            ->toContain('module APPSERV_'.$service->id.' "Direct Service"');
    });
});
