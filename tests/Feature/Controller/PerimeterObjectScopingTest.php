<?php

use App\Models\AdminUser;
use App\Models\Cartographer;
use App\Models\DataProcessing;
use App\Models\Entity;
use App\Models\Perimeter;
use App\Models\Role;
use App\Models\User;
use App\Support\ModelRegistry;
use App\Support\PerimeterSettings;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

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

describe('migration', function () {
    test('perimeter_id exists with default 1 on mapped-object tables', function () {
        foreach (['entities', 'data_processing', 'applications'] as $table) {
            expect(Schema::hasColumn($table, 'perimeter_id'))->toBeTrue();
        }

        $entity = Entity::factory()->create();
        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'perimeter_id' => Perimeter::DEFAULT_ID]);
    });
});

describe('HasPerimeter trait', function () {
    test('exposes a perimeter relation, mass-assignable (validated in FormRequests)', function () {
        $entity = Entity::factory()->create()->refresh();

        expect($entity->perimeter)->toBeInstanceOf(Perimeter::class)
            ->and($entity->perimeter->id)->toBe(Perimeter::DEFAULT_ID)
            ->and((new Entity)->getFillable())->toContain('perimeter_id');
    });

    test('ModelRegistry::PERIMETER_SCOPED_MODELS has 55 models including the GDPR ones and AdminUser', function () {
        expect(ModelRegistry::PERIMETER_SCOPED_MODELS)->toHaveCount(55)
            ->and(ModelRegistry::PERIMETER_SCOPED_MODELS)->toContain(DataProcessing::class)
            ->and(ModelRegistry::PERIMETER_SCOPED_MODELS)->toContain(Entity::class)
            ->and(ModelRegistry::PERIMETER_SCOPED_MODELS)->toContain(AdminUser::class);
    });
});

describe('Cartographer::scopedQuery perimeter filtering', function () {
    beforeEach(function () {
        $this->perimeterB = Perimeter::factory()->create();

        $this->entityDefault = Entity::factory()->create();
        DB::table('entities')->where('id', $this->entityDefault->id)->update(['perimeter_id' => Perimeter::DEFAULT_ID]);

        $this->entityB = Entity::factory()->create();
        DB::table('entities')->where('id', $this->entityB->id)->update(['perimeter_id' => $this->perimeterB->id]);

        // Non-admin user with full 'entity_access' role permission (User role from RolesTableSeeder).
        $this->fullAccessRole = Role::getRoleByTitle('User');
        $this->user = User::factory()->create();
        $this->user->roles()->attach($this->fullAccessRole);
    });

    test('feature disabled: sees all objects regardless of session value', function () {
        $this->actingAs($this->user);
        session(['active_perimeter' => $this->perimeterB->id]);

        $ids = Cartographer::scopedQuery(Entity::query())->pluck('id');

        expect($ids)->toContain($this->entityDefault->id)
            ->and($ids)->toContain($this->entityB->id);
    });

    test('feature enabled, specific perimeter active: only that perimeter is returned', function () {
        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->user);
        session(['active_perimeter' => $this->perimeterB->id]);

        $ids = Cartographer::scopedQuery(Entity::query())->pluck('id');

        expect($ids)->toContain($this->entityB->id)
            ->and($ids)->not->toContain($this->entityDefault->id);
    });

    test('feature enabled, "tous" (0) active: sees all objects within permission scope', function () {
        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->user);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        $ids = Cartographer::scopedQuery(Entity::query())->pluck('id');

        expect($ids)->toContain($this->entityDefault->id)
            ->and($ids)->toContain($this->entityB->id);
    });

    test('admin with a specific perimeter active is restricted to it too', function () {
        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);
        session(['active_perimeter' => $this->perimeterB->id]);

        $ids = Cartographer::scopedQuery(Entity::query())->pluck('id');

        expect($ids)->toContain($this->entityB->id)
            ->and($ids)->not->toContain($this->entityDefault->id);
    });

    test('admin with no explicit selection ("tous") sees everything', function () {
        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        $ids = Cartographer::scopedQuery(Entity::query())->pluck('id');

        expect($ids)->toContain($this->entityDefault->id)
            ->and($ids)->toContain($this->entityB->id);
    });
});

describe('Admin index controllers (global scope)', function () {
    test('EntityController::index, which never calls scopedQuery, is still perimeter-filtered', function () {
        $perimeterB = Perimeter::factory()->create();

        Entity::factory()->count(2)->create(); // land on the DB default (périmètre 1)

        $entityB = Entity::factory()->create();
        DB::table('entities')->where('id', $entityB->id)->update(['perimeter_id' => $perimeterB->id]);

        $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
        $this->admin->roles()->attach($roleB);

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $response = $this->withSession(['active_perimeter' => $perimeterB->id])
            ->get(route('admin.entities.index'));

        $response->assertOk();
        expect($response->viewData('entities')->total())->toBe(1);
    });

    test('a scoped-out object 403s on show/edit even for admin (route-model binding)', function () {
        $perimeterB = Perimeter::factory()->create();
        $entityDefault = Entity::factory()->create(); // périmètre 1 (default)

        $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
        $this->admin->roles()->attach($roleB);

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $this->withSession(['active_perimeter' => $perimeterB->id])
            ->get(route('admin.entities.show', $entityDefault))
            ->assertForbidden();
    });

    test('a genuinely non-existent object still 404s (route-model binding)', function () {
        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $this->withSession(['active_perimeter' => Perimeter::ALL_ID])
            ->get(route('admin.entities.show', 999999))
            ->assertNotFound();
    });
});

describe('HomeController integration', function () {
    test('maturity counts only include the active perimeter objects once enabled', function () {
        $perimeterB = Perimeter::factory()->create();

        // Give the admin a role in périmètre B, so EnsureActivePerimeter accepts
        // it as a valid active selection instead of resetting it back to default.
        $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
        $this->admin->roles()->attach($roleB);

        Entity::factory()->count(2)->create(); // land on the DB default (périmètre 1)

        $entityB = Entity::factory()->create();
        DB::table('entities')->where('id', $entityB->id)->update(['perimeter_id' => $perimeterB->id]);

        PerimeterSettings::setEnabled(true);
        $this->actingAs($this->admin);

        $this->withSession(['active_perimeter' => $perimeterB->id])
            ->get(route('admin.home'))
            ->assertOk();

        expect(Cartographer::scopedQuery(Entity::query())->count())->toBe(1);

        $this->withSession(['active_perimeter' => Perimeter::ALL_ID])
            ->get(route('admin.home'))
            ->assertOk();

        expect(Cartographer::scopedQuery(Entity::query())->count())->toBe(3);
    });
});
