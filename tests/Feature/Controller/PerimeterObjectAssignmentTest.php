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
});

describe('creation defaults (observer)', function () {
    test('without an explicit perimeter_id, a mono-perimetre user gets the observer default', function () {
        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.entities.store'), ['name' => 'No Picker Entity']);

        $response->assertRedirect();
        $this->assertDatabaseHas('entities', ['name' => 'No Picker Entity', 'perimeter_id' => Perimeter::DEFAULT_ID]);
    });

    test('an explicit valid perimeter_id is honored', function () {
        $perimeterB = Perimeter::factory()->create();
        $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
        $this->admin->roles()->attach($roleB);

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.entities.store'), [
            'name' => 'Explicit Perimeter Entity',
            'perimeter_id' => $perimeterB->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('entities', ['name' => 'Explicit Perimeter Entity', 'perimeter_id' => $perimeterB->id]);
    });

    test('a perimeter_id outside the user own perimetres is rejected', function () {
        $foreignPerimeter = Perimeter::factory()->create();

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.entities.store'), [
            'name' => 'Should Not Be Created',
            'perimeter_id' => $foreignPerimeter->id,
        ]);

        $response->assertSessionHasErrors('perimeter_id');
        $this->assertDatabaseMissing('entities', ['name' => 'Should Not Be Created']);
    });

    test('feature disabled: perimeter_id stays at the DB default regardless of session', function () {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.entities.store'), ['name' => 'Disabled Feature Entity']);

        $response->assertRedirect();
        $this->assertDatabaseHas('entities', ['name' => 'Disabled Feature Entity', 'perimeter_id' => Perimeter::DEFAULT_ID]);
    });
});

describe('edit moves the object', function () {
    test('changing perimeter_id on edit moves the object to another of the user own perimetres', function () {
        $perimeterB = Perimeter::factory()->create();
        $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
        $this->admin->roles()->attach($roleB);

        $entity = Entity::factory()->create();
        DB::table('entities')->where('id', $entity->id)->update(['perimeter_id' => Perimeter::DEFAULT_ID]);

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $response = $this->put(route('admin.entities.update', $entity), [
            'name' => $entity->name,
            'perimeter_id' => $perimeterB->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'perimeter_id' => $perimeterB->id]);
    });
});

describe('picker visibility', function () {
    test('the picker is absent for a mono-perimetre user', function () {
        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $this->get(route('admin.entities.create'))->assertDontSee('id="perimeter_id"', false);
    });

    test('the picker is present for a multi-perimetre user', function () {
        $perimeterB = Perimeter::factory()->create();
        $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
        $this->admin->roles()->attach($roleB);

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $this->get(route('admin.entities.create'))->assertSee('id="perimeter_id"', false);
    });
});

describe('show page label', function () {
    test('shows "Périmètre / Nom" only for a multi-perimetre user', function () {
        $entity = Entity::factory()->create(['name' => 'Labelled Entity']);

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $this->get(route('admin.entities.show', $entity))
            ->assertDontSee(trans('cruds.perimeter.title_short').' / ', false);

        $perimeterB = Perimeter::factory()->create();
        $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
        $this->admin->roles()->attach($roleB);

        $this->get(route('admin.entities.show', $entity))
            ->assertSee(trans('cruds.perimeter.title_short').' / ', false);
    });
});
