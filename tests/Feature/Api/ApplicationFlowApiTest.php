<?php

use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\User;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

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
    Passport::actingAs($this->user);
});

function createApplicationFlow(): ApplicationFlow
{
    $source = Application::factory()->create(['name' => 'Source App']);
    $dest = Application::factory()->create(['name' => 'Dest App']);

    return ApplicationFlow::query()->create([
        'name' => 'RFC HR -> Logistique',
        'type' => 'RFC',
        'description' => 'Flux de transfert des employés',
        'application_source_id' => $source->id,
        'application_dest_id' => $dest->id,
        'crypted' => true,
    ]);
}

// Regression test for https://github.com/sourcentis/mercator/discussions/2191#discussioncomment-18419034
// GET /api/application-flows (index) returned the flow, but GET /api/application-flows/{id} (show)
// returned an empty resource because the controller's `show`/`update`/`destroy` methods used
// `$flow` as the route-bound parameter name while `Route::resource('application-flows', ...)`
// generates the URI wildcard `{application_flow}` — implicit route model binding silently failed
// and the container injected a brand-new, empty ApplicationFlow instead of the requested one.
it('shows a single application flow with its actual data (not empty)', function () {
    $flow = createApplicationFlow();

    $response = $this->getJson("/api/application-flows/{$flow->id}")
        ->assertOk();

    $response->assertJsonPath('data.id', $flow->id);
    $response->assertJsonPath('data.name', 'RFC HR -> Logistique');
    $response->assertJsonPath('data.application_source_id', $flow->application_source_id);
});

it('updates a single application flow identified by the route-bound model', function () {
    $flow = createApplicationFlow();

    $this->putJson("/api/application-flows/{$flow->id}", ['name' => 'Updated flow'])
        ->assertOk();

    $this->assertDatabaseHas('application_flows', [
        'id' => $flow->id,
        'name' => 'Updated flow',
    ]);
});

it('deletes the actual application flow identified by the route-bound model', function () {
    $flow = createApplicationFlow();

    $this->deleteJson("/api/application-flows/{$flow->id}")
        ->assertOk();

    $this->assertSoftDeleted('application_flows', ['id' => $flow->id]);
});
