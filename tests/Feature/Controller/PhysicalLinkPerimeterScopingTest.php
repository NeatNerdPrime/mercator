<?php

use App\Models\Perimeter;
use App\Models\PhysicalLink;
use App\Models\PhysicalServer;
use App\Models\Role;
use App\Models\User;
use App\Support\PerimeterSettings;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * PhysicalLink can be visible from up to 3 different perimeters: its own
 * explicit perimeter_id, its source's perimeter, and its destination's
 * perimeter (PhysicalLinkPerimeterScope), unlike every other HasPerimeter
 * model which only compares its own column (PerimeterScope). Mirrors
 * ApplicationFlowPerimeterScopingTest.
 */
beforeEach(function () {
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    $this->admin = User::query()->where('login', 'admin@admin.com')->first();
    $this->perimeterB = Perimeter::factory()->create();

    $roleB = Role::factory()->create(['perimeter_id' => $this->perimeterB->id]);
    $this->admin->roles()->attach($roleB);

    PerimeterSettings::setEnabled(true);
    $this->actingAs($this->admin);
});

function linkIdsVisibleIn(int $perimeterId): array
{
    session(['active_perimeter' => $perimeterId]);

    return PhysicalLink::query()->pluck('id')->toArray();
}

test('a link is visible from the perimeter of its source even when its own perimeter_id differs', function () {
    $srcB = PhysicalServer::factory()->create();
    DB::table('physical_servers')->where('id', $srcB->id)->update(['perimeter_id' => $this->perimeterB->id]);
    $destDefault = PhysicalServer::factory()->create(); // stays on périmètre 1 (default)

    $link = PhysicalLink::factory()->create([
        'physical_server_src_id' => $srcB->id,
        'physical_server_dest_id' => $destDefault->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(linkIdsVisibleIn($this->perimeterB->id))->toContain($link->id);
});

test('a link is visible from the perimeter of its destination even when its own perimeter_id differs', function () {
    $srcDefault = PhysicalServer::factory()->create();
    $destB = PhysicalServer::factory()->create();
    DB::table('physical_servers')->where('id', $destB->id)->update(['perimeter_id' => $this->perimeterB->id]);

    $link = PhysicalLink::factory()->create([
        'physical_server_src_id' => $srcDefault->id,
        'physical_server_dest_id' => $destB->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(linkIdsVisibleIn($this->perimeterB->id))->toContain($link->id);
});

test('a link is visible from its own explicit perimeter_id even when source and destination are elsewhere', function () {
    $srcDefault = PhysicalServer::factory()->create();
    $destDefault = PhysicalServer::factory()->create();

    $link = PhysicalLink::factory()->create([
        'physical_server_src_id' => $srcDefault->id,
        'physical_server_dest_id' => $destDefault->id,
        'perimeter_id' => $this->perimeterB->id,
    ]);

    expect(linkIdsVisibleIn($this->perimeterB->id))->toContain($link->id);
});

test('a link entirely outside the active perimeter (own, source, and destination) is hidden', function () {
    $srcDefault = PhysicalServer::factory()->create();
    $destDefault = PhysicalServer::factory()->create();

    $link = PhysicalLink::factory()->create([
        'physical_server_src_id' => $srcDefault->id,
        'physical_server_dest_id' => $destDefault->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(linkIdsVisibleIn($this->perimeterB->id))->not->toContain($link->id);
});

test('"tous" (0) active shows links regardless of perimeter', function () {
    $srcDefault = PhysicalServer::factory()->create();
    $destDefault = PhysicalServer::factory()->create();

    $link = PhysicalLink::factory()->create([
        'physical_server_src_id' => $srcDefault->id,
        'physical_server_dest_id' => $destDefault->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(linkIdsVisibleIn(Perimeter::ALL_ID))->toContain($link->id);
});
