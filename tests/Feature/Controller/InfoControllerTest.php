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

test('displays the info page', function () {
    $response = $this->get(route('admin.doc.info'));

    $response->assertOk();
    $response->assertViewIs('doc.info');
    $response->assertSee($this->user->login);
});

test('hides the perimeters row when the feature is disabled', function () {
    $response = $this->get(route('admin.doc.info'));

    $response->assertOk();
    $response->assertViewHas('perimetersEnabled', false);
    $response->assertDontSee('id="info-perimeters"', false);
});

test('shows the user perimeters when the feature is enabled', function () {
    PerimeterSettings::setEnabled(true);
    $perimeter = Perimeter::factory()->create(['nom' => 'Site B']);
    $role = Role::factory()->create(['perimeter_id' => $perimeter->id]);
    $this->user->roles()->attach($role);

    $response = $this->get(route('admin.doc.info'));

    $response->assertOk();
    $response->assertViewHas('perimetersEnabled', true);
    $response->assertViewHas('perimeters', function (array $perimeters) {
        return in_array('Site B', $perimeters, true);
    });
    $response->assertSee('Site B');
});
