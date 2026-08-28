<?php

use App\Http\Controllers\Admin\ExplorerController;
use App\Models\Annuaire;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    $this->actingAs(User::query()->where('login', 'admin@admin.com')->first());
});

describe('administrative view', function () {
    test('links an annuaire to its application', function () {
        $application = Application::factory()->create();
        $annuaire = Annuaire::factory()->create(['application_id' => $application->id]);

        [$nodes, $edges] = (new ExplorerController)->getData();

        $edge = collect($edges)->first(
            fn ($e) => $e['from'] === Annuaire::$prefix.$annuaire->id && $e['to'] === Application::$prefix.$application->id
        );

        expect($edge)->not->toBeNull();
    });

    test('does not link an annuaire without an application', function () {
        $annuaire = Annuaire::factory()->create(['application_id' => null]);

        [$nodes, $edges] = (new ExplorerController)->getData();

        $edge = collect($edges)->first(
            fn ($e) => $e['from'] === Annuaire::$prefix.$annuaire->id && str_starts_with((string) $e['to'], Application::$prefix)
        );

        expect($edge)->toBeNull();
    });
});
