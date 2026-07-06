<?php

use App\Models\Building;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed base permissions/roles and users as in other feature tests
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    // Login as an admin (id=1 seeded by UsersTableSeeder)
    $this->user = User::query()->where('login', 'admin@admin.com')->first();
    $this->actingAs($this->user);
});

describe('Security Zone View', function () {
    test('can display security-zones view page', function () {
        $response = $this->get(route('admin.report.view.security-zones'));

        $response->assertOk();
        $response->assertViewIs('admin.reports.security_zones');
        $response->assertViewHasAll(['allZones', 'selectedIds', 'zones', 'buildings', 'adminUsers']);
    });

    test('can display filtered view for selected zones with a parent/child relationship', function () {
        $parentZone = Zone::factory()->create();
        $childZone = Zone::factory()->create();
        $parentZone->childZones()->attach($childZone->id);
        $building = Building::factory()->create();
        $parentZone->buildings()->attach($building->id);

        $response = $this->get(route('admin.report.view.security-zones', [
            'filter' => 1,
            'zones' => [$parentZone->id, $childZone->id],
        ]));

        $response->assertOk();
        $response->assertViewHas('zones', function ($zones) use ($parentZone, $childZone) {
            return $zones->contains('id', $parentZone->id) && $zones->contains('id', $childZone->id);
        });
        $response->assertViewHas('buildings', function ($buildings) use ($building) {
            return $buildings->contains('id', $building->id);
        });
    });

    test('denies access without permission', function () {
        // New user without the reports_access/zone_access permission
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.report.view.security-zones'));

        $response->assertForbidden();
    });
});
