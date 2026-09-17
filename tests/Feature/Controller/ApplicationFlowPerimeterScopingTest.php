<?php

use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Perimeter;
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
 * ApplicationFlow can be visible from up to 3 different perimeters: its own
 * explicit perimeter_id, its source's perimeter, and its destination's
 * perimeter (ApplicationFlowPerimeterScope), unlike every other
 * HasPerimeter model which only compares its own column (PerimeterScope).
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

function flowIdsVisibleIn(int $perimeterId): array
{
    session(['active_perimeter' => $perimeterId]);

    return ApplicationFlow::query()->pluck('id')->toArray();
}

test('a flow is visible from the perimeter of its source even when its own perimeter_id differs', function () {
    $sourceB = Application::factory()->create();
    DB::table('applications')->where('id', $sourceB->id)->update(['perimeter_id' => $this->perimeterB->id]);
    $destDefault = Application::factory()->create(); // stays on périmètre 1 (default)

    $flow = ApplicationFlow::factory()->create([
        'application_source_id' => $sourceB->id,
        'application_dest_id' => $destDefault->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(flowIdsVisibleIn($this->perimeterB->id))->toContain($flow->id);
});

test('a flow is visible from the perimeter of its destination even when its own perimeter_id differs', function () {
    $sourceDefault = Application::factory()->create();
    $destB = Application::factory()->create();
    DB::table('applications')->where('id', $destB->id)->update(['perimeter_id' => $this->perimeterB->id]);

    $flow = ApplicationFlow::factory()->create([
        'application_source_id' => $sourceDefault->id,
        'application_dest_id' => $destB->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(flowIdsVisibleIn($this->perimeterB->id))->toContain($flow->id);
});

test('a flow is visible from its own explicit perimeter_id even when source and destination are elsewhere', function () {
    $sourceDefault = Application::factory()->create();
    $destDefault = Application::factory()->create();

    $flow = ApplicationFlow::factory()->create([
        'application_source_id' => $sourceDefault->id,
        'application_dest_id' => $destDefault->id,
        'perimeter_id' => $this->perimeterB->id,
    ]);

    expect(flowIdsVisibleIn($this->perimeterB->id))->toContain($flow->id);
});

test('a flow entirely outside the active perimeter (own, source, and destination) is hidden', function () {
    $sourceDefault = Application::factory()->create();
    $destDefault = Application::factory()->create();

    $flow = ApplicationFlow::factory()->create([
        'application_source_id' => $sourceDefault->id,
        'application_dest_id' => $destDefault->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(flowIdsVisibleIn($this->perimeterB->id))->not->toContain($flow->id);
});

test('"tous" (0) active shows flows regardless of perimeter', function () {
    $sourceDefault = Application::factory()->create();
    $destDefault = Application::factory()->create();

    $flow = ApplicationFlow::factory()->create([
        'application_source_id' => $sourceDefault->id,
        'application_dest_id' => $destDefault->id,
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    expect(flowIdsVisibleIn(Perimeter::ALL_ID))->toContain($flow->id);
});
