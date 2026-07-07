<?php

use App\Models\AdminUser;
use App\Models\Building;
use App\Models\Domain;
use App\Models\User;
use App\Models\Zone;
use App\Services\Graph\SecurityZoneGraphBuilder;
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

    $this->actingAs(User::query()->where('login', 'admin@admin.com')->first());
});

test('buildDot draws parent-child zone edges plus building and admin user membership', function () {
    $parentZone = Zone::factory()->create();
    $childZone = Zone::factory()->create();
    $parentZone->childZones()->attach($childZone->id);
    $building = Building::factory()->create();
    $parentZone->buildings()->attach($building->id);
    $domain = Domain::factory()->create();
    $adminUser = AdminUser::factory()->create(['domain_id' => $domain->id, 'user_id' => 'jdoe']);
    $parentZone->adminUsers()->attach($adminUser->id);

    $zones = Zone::with('parentZones', 'childZones', 'buildings', 'adminUsers')->whereIn('id', [$parentZone->id, $childZone->id])->get();

    $dot = (new SecurityZoneGraphBuilder)->buildDot($zones, collect([$building]), collect([$adminUser]));

    expect($dot)
        ->toContain('ZONE'.$parentZone->id.' -> ZONE'.$childZone->id)
        ->toContain('ZONE'.$parentZone->id.' -> BUILD'.$building->id)
        ->toContain('ZONE'.$parentZone->id.' -> AU'.$adminUser->id)
        ->toContain('AU'.$adminUser->id.' [shape=none label=<')
        ->toContain('jdoe');
});
