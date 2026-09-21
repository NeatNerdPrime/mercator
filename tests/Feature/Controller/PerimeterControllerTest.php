<?php

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

    $this->user = User::query()->where('login', 'admin@admin.com')->first();
    $this->actingAs($this->user);
});

describe('default perimeter seed', function () {
    test('the default perimeter exists with id 1 after migration', function () {
        expect(Perimeter::count())->toBe(1);

        $default = Perimeter::find(1);
        expect($default)->not->toBeNull()
            ->and($default->id)->toBe(1)
            ->and($default->isDefault())->toBeTrue();
    });
});

describe('crud', function () {
    test('can create a perimeter with an auto-incremented id', function () {
        $response = $this->post(route('admin.perimeters.store'), ['name' => 'Site B']);

        $response->assertRedirect();
        $this->assertDatabaseHas('perimeters', ['name' => 'Site B']);

        $created = Perimeter::query()->where('name', 'Site B')->first();
        expect($created->id)->toBeGreaterThan(1);
    });

    test('rejects a name shorter than 2 characters', function () {
        $response = $this->post(route('admin.perimeters.store'), ['name' => 'A']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('perimeters', ['name' => 'A']);
    });

    test('rejects a duplicate name', function () {
        Perimeter::factory()->create(['name' => 'Site B']);

        $response = $this->post(route('admin.perimeters.store'), ['name' => 'Site B']);

        $response->assertSessionHasErrors('name');
    });

    test('can update a perimeter name', function () {
        $perimeter = Perimeter::factory()->create(['name' => 'Site B']);

        $response = $this->put(route('admin.perimeters.update', $perimeter), ['name' => 'Site B Renamed']);

        $response->assertRedirect();
        $this->assertDatabaseHas('perimeters', ['id' => $perimeter->id, 'name' => 'Site B Renamed']);
    });

    test('can delete an unused, non-default perimeter', function () {
        $perimeter = Perimeter::factory()->create();

        $response = $this->delete(route('admin.perimeters.destroy', $perimeter));

        $response->assertRedirect();
        $this->assertDatabaseMissing('perimeters', ['id' => $perimeter->id]);
    });

    test('denies access without the configure permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.perimeters.store'), ['name' => 'Site B']);

        $response->assertForbidden();
    });
});

describe('default perimeter protection', function () {
    test('the default perimeter cannot be deleted', function () {
        $response = $this->delete(route('admin.perimeters.destroy', Perimeter::DEFAULT_ID));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('perimeters', ['id' => Perimeter::DEFAULT_ID]);
    });

    test('the default perimeter can be renamed', function () {
        $response = $this->put(route('admin.perimeters.update', Perimeter::DEFAULT_ID), ['name' => 'Siège']);

        $response->assertRedirect();
        $this->assertDatabaseHas('perimeters', ['id' => Perimeter::DEFAULT_ID, 'name' => 'Siège']);
    });

    test('a perimeter in use by a role cannot be deleted', function () {
        $perimeter = Perimeter::factory()->create();
        Role::factory()->create(['perimeter_id' => $perimeter->id]);

        $response = $this->delete(route('admin.perimeters.destroy', $perimeter));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('perimeters', ['id' => $perimeter->id]);
    });
});

describe('activation flag', function () {
    test('is disabled by default', function () {
        expect(PerimeterSettings::isEnabled())->toBeFalse();
    });

    test('can be enabled and disabled', function () {
        $this->put(route('admin.perimeters.activation'), ['perimeters_enabled' => '1']);
        expect(PerimeterSettings::isEnabled())->toBeTrue();

        $this->put(route('admin.perimeters.activation'), []);
        expect(PerimeterSettings::isEnabled())->toBeFalse();
    });
});

describe('role assignment', function () {
    test('a role defaults to the default perimeter', function () {
        $role = Role::factory()->create();

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'perimeter_id' => Perimeter::DEFAULT_ID]);
    });

    test('a role can be created with an explicit perimeter', function () {
        $perimeter = Perimeter::factory()->create();

        $response = $this->post(route('admin.roles.store'), [
            'title' => 'Site B role',
            'perimeter_id' => $perimeter->id,
            'permissions' => [],
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', ['title' => 'Site B role', 'perimeter_id' => $perimeter->id]);
    });

    test('role creation rejects a non-existent perimeter', function () {
        $response = $this->post(route('admin.roles.store'), [
            'title' => 'Bad role',
            'perimeter_id' => 999999,
            'permissions' => [],
        ]);

        $response->assertSessionHasErrors('perimeter_id');
        $this->assertDatabaseMissing('roles', ['title' => 'Bad role']);
    });
});

describe('User perimeter helpers', function () {
    test('perimeterIds returns an empty array without roles', function () {
        $user = User::factory()->create();

        expect($user->perimeterIds())->toBe([])
            ->and($user->hasMultiplePerimeters())->toBeFalse();
    });

    test('perimeterIds returns a single distinct value for one perimeter', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create(['perimeter_id' => Perimeter::DEFAULT_ID]);
        $user->roles()->attach($role);

        expect($user->perimeterIds())->toBe([Perimeter::DEFAULT_ID])
            ->and($user->hasMultiplePerimeters())->toBeFalse();
    });

    test('hasMultiplePerimeters is true with at least two distinct perimeters', function () {
        $perimeterA = Perimeter::factory()->create();
        $perimeterB = Perimeter::factory()->create();

        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['perimeter_id' => $perimeterA->id]));
        $user->roles()->attach(Role::factory()->create(['perimeter_id' => $perimeterB->id]));

        expect($user->hasMultiplePerimeters())->toBeTrue()
            ->and($user->perimeterIds())->toEqualCanonicalizing([$perimeterA->id, $perimeterB->id]);
    });
});
