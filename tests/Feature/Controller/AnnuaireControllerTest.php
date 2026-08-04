<?php

use App\Models\Annuaire;
use App\Models\Application;
use App\Models\User;
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

describe('index', function () {
    test('can display annuaires index page', function () {
        Annuaire::factory()->count(3)->create();

        $response = $this->get(route('admin.annuaires.index'));

        $response->assertOk();
        $response->assertViewIs('admin.annuaires.index');
        $response->assertViewHas('annuaires');
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.annuaires.index'));

        $response->assertForbidden();
    });

    test('displays the linked application name', function () {
        $application = Application::factory()->create(['name' => 'LDAP Administration']);
        Annuaire::factory()->create(['application_id' => $application->id]);

        $response = $this->get(route('admin.annuaires.index'));

        $response->assertOk();
        $response->assertSee('LDAP Administration');
    });

});

describe('create', function () {
    test('can display create form', function () {
        $response = $this->get(route('admin.annuaires.create'));

        $response->assertOk();
        $response->assertViewIs('admin.annuaires.create');
        $response->assertViewHas(['zone_admins', 'applications']);
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.annuaires.create'));

        $response->assertForbidden();
    });

});

describe('show', function () {

    test('can display object', function () {
        $name = fake()->word();
        $annuaire = Annuaire::factory()->create(['name' => $name]);

        $response = $this->get(route('admin.annuaires.show', $annuaire->id));

        $response->assertOk();
        $response->assertViewIs('admin.annuaires.show');
        $response->assertSee($name);
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $name = fake()->word();
        $annuaire = Annuaire::factory()->create(['name' => $name]);

        $response = $this->get(route('admin.annuaires.show', $annuaire->id));

        $response->assertForbidden();
    });

});

describe('edit', function () {
    test('can display edit form', function () {
        $name = fake()->word();
        $annuaire = Annuaire::factory()->create(['name' => $name]);

        $response = $this->get(route('admin.annuaires.edit', $annuaire));

        $response->assertOk();
        $response->assertViewIs('admin.annuaires.edit');
        $response->assertViewHas('annuaire');
        $response->assertSee($name);
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $annuaire = Annuaire::factory()->create();

        $response = $this->get(route('admin.annuaires.edit', $annuaire));

        $response->assertForbidden();
    });
});

describe('update', function () {
    test('can update annuaire', function () {
        $name = fake()->word();
        $annuaire = Annuaire::factory()->create(['name' => $name]);

        $data = [
            'name' => 'Updated Name',
            'description' => fake()->sentence(),
        ];

        $response = $this->put(route('admin.annuaires.update', $annuaire), $data);

        $response->assertRedirect(route('admin.annuaires.index'));
        $this->assertDatabaseHas('annuaires', ['name' => 'Updated Name']);
    });
});

describe('destroy', function () {
    test('can delete annuaire', function () {
        $annuaire = Annuaire::factory()->create();

        $response = $this->delete(route('admin.annuaires.destroy', $annuaire->id));
        $response->assertRedirect(route('admin.annuaires.index'));

        $this->assertSoftDeleted('annuaires', ['id' => $annuaire->id]);

        $annuaire->refresh();
        expect($annuaire->deleted_at)->not->toBeNull()
            ->and($annuaire->trashed())->toBeTrue();

    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $annuaire = Annuaire::factory()->create();

        $response = $this->delete(route('admin.annuaires.destroy', $annuaire));

        $response->assertForbidden();
    });
});

describe('application link', function () {
    test('can associate an application on update', function () {
        $application = Application::factory()->create();
        $annuaire = Annuaire::factory()->create();

        $response = $this->put(route('admin.annuaires.update', $annuaire), [
            'name' => 'Directory with app',
            'application_id' => $application->id,
        ]);

        $response->assertRedirect(route('admin.annuaires.index'));
        $this->assertDatabaseHas('annuaires', [
            'id' => $annuaire->id,
            'application_id' => $application->id,
        ]);
    });

    test('rejects a non existing application', function () {
        $annuaire = Annuaire::factory()->create();

        $response = $this->put(route('admin.annuaires.update', $annuaire), [
            'name' => 'Directory bad app',
            'application_id' => 999999,
        ]);

        $response->assertSessionHasErrors('application_id');
    });

    test('application relation resolves to the application model', function () {
        $application = Application::factory()->create();
        $annuaire = Annuaire::factory()->create(['application_id' => $application->id]);

        expect($annuaire->application->is($application))->toBeTrue();
    });

    test('show displays the linked application name', function () {
        $application = Application::factory()->create(['name' => 'LDAP Administration']);
        $annuaire = Annuaire::factory()->create(['application_id' => $application->id]);

        $response = $this->get(route('admin.annuaires.show', $annuaire->id));

        $response->assertOk();
        $response->assertSee('LDAP Administration');
    });
});

describe('maturity', function () {
    test('level 1 does not require an application', function () {
        $annuaire = Annuaire::factory()->create(['application_id' => null]);

        expect(Annuaire::maturityLevel1()->whereKey($annuaire->id)->exists())->toBeTrue();
    });

    test('level 2 requires an application', function () {
        $withoutApp = Annuaire::factory()->create(['application_id' => null]);
        $application = Application::factory()->create();
        $withApp = Annuaire::factory()->create(['application_id' => $application->id]);

        expect(Annuaire::maturityLevel2()->whereKey($withoutApp->id)->exists())->toBeFalse()
            ->and(Annuaire::maturityLevel2()->whereKey($withApp->id)->exists())->toBeTrue();
    });
});

describe('massDestroy', function () {
    test('can delete multiple annuaires', function () {
        $annuaires = Annuaire::factory()->count(3)->create();
        $ids = $annuaires->pluck('id')->toArray();

        $response = $this->delete(route('admin.annuaires.massDestroy'), ['ids' => $ids]);
        $response->assertNoContent();

        foreach ($ids as $id) {
            $this->assertSoftDeleted('annuaires', ['id' => $id]);
        }
    });

    test('returns no content status', function () {
        $annuaire = Annuaire::factory()->create();

        $response = $this->delete(route('admin.annuaires.massDestroy'), [
            'ids' => [$annuaire->id],
        ]);

        $response->assertStatus(204);
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $annuaire = Annuaire::factory()->create();

        $response = $this->delete(route('admin.annuaires.massDestroy'), [
            'ids' => [$annuaire->id],
        ]);

        $response->assertForbidden();
    });

});
