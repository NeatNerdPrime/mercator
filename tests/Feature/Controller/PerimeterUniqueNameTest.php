<?php

use App\Models\Entity;
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

test('the same name is allowed in two different perimetres', function () {
    $this->post(route('admin.entities.store'), [
        'name' => 'Shared Name',
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ])->assertRedirect();

    $this->post(route('admin.entities.store'), [
        'name' => 'Shared Name',
        'perimeter_id' => $this->perimeterB->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('entities', ['name' => 'Shared Name', 'perimeter_id' => Perimeter::DEFAULT_ID]);
    $this->assertDatabaseHas('entities', ['name' => 'Shared Name', 'perimeter_id' => $this->perimeterB->id]);
});

test('the same name is rejected within the same perimetre', function () {
    Entity::factory()->create(['name' => 'Duplicate Name']);

    $response = $this->post(route('admin.entities.store'), [
        'name' => 'Duplicate Name',
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    $response->assertSessionHasErrors('name');
});

test('editing an object and keeping its own name does not trigger a false duplicate', function () {
    $entity = Entity::factory()->create(['name' => 'Keep My Name']);

    $response = $this->put(route('admin.entities.update', $entity), [
        'name' => 'Keep My Name',
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('name');
});

test('a soft-deleted record does not block reusing its name in the same perimetre', function () {
    $entity = Entity::factory()->create(['name' => 'Reusable Name']);
    $entity->delete();

    $response = $this->post(route('admin.entities.store'), [
        'name' => 'Reusable Name',
        'perimeter_id' => Perimeter::DEFAULT_ID,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('name');
});

test('moving an object to another perimetre on edit is blocked by a name clash there', function () {
    Entity::factory()->create(['name' => 'Clashing Name']);
    DB::table('entities')->where('name', 'Clashing Name')->update(['perimeter_id' => $this->perimeterB->id]);

    $entityToMove = Entity::factory()->create(['name' => 'Clashing Name']);

    $response = $this->put(route('admin.entities.update', $entityToMove), [
        'name' => 'Clashing Name',
        'perimeter_id' => $this->perimeterB->id,
    ]);

    $response->assertSessionHasErrors('name');
});
