<?php

use App\Models\Perimeter;
use App\Models\Permission;
use App\Models\Role;
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

// ============================================================
// index
// ============================================================

it('forbids listing perimeters without permission', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/perimeters')->assertForbidden();
});

it('lists perimeters including the default one', function () {
    Perimeter::factory()->create(['name' => 'Site B']);

    $response = $this->getJson('/api/perimeters')->assertOk();

    $data = $response->json();
    $data = isset($data['data']) ? $data['data'] : $data;

    expect(collect($data)->pluck('name')->all())->toContain('Site B')
        ->and($data)->toHaveCount(2);
});

it('lists perimeters for a non-admin holding the configure permission', function () {
    Perimeter::factory()->create(['name' => 'Site B']);

    $user = User::factory()->create();
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::query()->where('title', 'configure')->value('id'));
    $user->roles()->attach($role->id);
    Passport::actingAs($user);

    $data = $this->getJson('/api/perimeters')->assertOk()->json();
    $data = isset($data['data']) ? $data['data'] : $data;

    expect($data)->toHaveCount(2);
});

it('filters perimeters by name', function () {
    Perimeter::factory()->create(['name' => 'Site B']);

    $data = $this->getJson('/api/perimeters?filter[name]=Site B')->assertOk()->json();
    $data = isset($data['data']) ? $data['data'] : $data;

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Site B');
});

// ============================================================
// store
// ============================================================

it('forbids creating a perimeter without permission', function () {
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/perimeters', ['name' => 'Site B'])->assertForbidden();
    $this->assertDatabaseMissing('perimeters', ['name' => 'Site B']);
});

it('creates a perimeter', function () {
    $this->postJson('/api/perimeters', ['name' => 'Site B'])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Site B']);

    $this->assertDatabaseHas('perimeters', ['name' => 'Site B']);
});

it('requires a name to create a perimeter', function () {
    $this->postJson('/api/perimeters', [])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rejects a name shorter than 2 characters', function () {
    $this->postJson('/api/perimeters', ['name' => 'A'])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rejects a name longer than 32 characters', function () {
    $this->postJson('/api/perimeters', ['name' => str_repeat('a', 33)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('rejects a duplicate name', function () {
    Perimeter::factory()->create(['name' => 'Site B']);

    $this->postJson('/api/perimeters', ['name' => 'Site B'])->assertUnprocessable()->assertJsonValidationErrors('name');
});

// ============================================================
// show
// ============================================================

it('forbids showing a perimeter without permission', function () {
    $perimeter = Perimeter::factory()->create();
    Passport::actingAs(User::factory()->create());

    $this->getJson("/api/perimeters/{$perimeter->id}")->assertForbidden();
});

it('shows a perimeter with the ids of its roles', function () {
    $perimeter = Perimeter::factory()->create(['name' => 'Site B']);
    $role = Role::factory()->create(['perimeter_id' => $perimeter->id]);

    $this->getJson("/api/perimeters/{$perimeter->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Site B')
        ->assertJsonPath('data.roles', [$role->id]);
});

it('returns 404 for an unknown perimeter', function () {
    $this->getJson('/api/perimeters/999999')->assertNotFound();
});

// ============================================================
// update
// ============================================================

it('forbids updating a perimeter without permission', function () {
    $perimeter = Perimeter::factory()->create(['name' => 'Site B']);
    Passport::actingAs(User::factory()->create());

    $this->putJson("/api/perimeters/{$perimeter->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->assertDatabaseHas('perimeters', ['id' => $perimeter->id, 'name' => 'Site B']);
});

it('updates a perimeter', function () {
    $perimeter = Perimeter::factory()->create(['name' => 'Site B']);

    $this->putJson("/api/perimeters/{$perimeter->id}", ['name' => 'Site C'])->assertOk();

    $this->assertDatabaseHas('perimeters', ['id' => $perimeter->id, 'name' => 'Site C']);
});

it('allows updating a perimeter keeping its own name', function () {
    $perimeter = Perimeter::factory()->create(['name' => 'Site B']);

    $this->putJson("/api/perimeters/{$perimeter->id}", ['name' => 'Site B'])->assertOk();
});

it('rejects renaming a perimeter to an existing name', function () {
    Perimeter::factory()->create(['name' => 'Site B']);
    $other = Perimeter::factory()->create(['name' => 'Site C']);

    $this->putJson("/api/perimeters/{$other->id}", ['name' => 'Site B'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

// ============================================================
// destroy
// ============================================================

it('forbids deleting a perimeter without permission', function () {
    $perimeter = Perimeter::factory()->create();
    Passport::actingAs(User::factory()->create());

    $this->deleteJson("/api/perimeters/{$perimeter->id}")->assertForbidden();
    $this->assertDatabaseHas('perimeters', ['id' => $perimeter->id]);
});

it('deletes an unused perimeter', function () {
    $perimeter = Perimeter::factory()->create();

    $this->deleteJson("/api/perimeters/{$perimeter->id}")->assertOk();

    $this->assertDatabaseMissing('perimeters', ['id' => $perimeter->id]);
});

it('refuses to delete the default perimeter', function () {
    $this->deleteJson('/api/perimeters/'.Perimeter::DEFAULT_ID)
        ->assertUnprocessable()
        ->assertJsonPath('message', trans('cruds.perimeter.errors.default_not_deletable'));

    $this->assertDatabaseHas('perimeters', ['id' => Perimeter::DEFAULT_ID]);
});

it('refuses to delete a perimeter used by a role', function () {
    $perimeter = Perimeter::factory()->create();
    Role::factory()->create(['perimeter_id' => $perimeter->id]);

    $this->deleteJson("/api/perimeters/{$perimeter->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', trans('cruds.perimeter.errors.in_use'));

    $this->assertDatabaseHas('perimeters', ['id' => $perimeter->id]);
});
