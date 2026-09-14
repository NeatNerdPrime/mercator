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

    PerimeterSettings::setEnabled(true);
    $this->actingAs($this->admin);
});

test('the perimetre column is absent for a mono-perimetre user', function () {
    Entity::factory()->create();

    $response = $this->get(route('admin.entities.index'));

    $response->assertOk();
    $response->assertDontSee('<th data-column="perimeter">', false);
});

test('the perimetre column is present, first, and hidden by default for a multi-perimetre user', function () {
    $perimeterB = Perimeter::factory()->create(['nom' => 'Site B']);
    $roleB = Role::factory()->create(['perimeter_id' => $perimeterB->id]);
    $this->admin->roles()->attach($roleB);

    $entity = Entity::factory()->create(['name' => 'My Entity']);

    $response = $this->get(route('admin.entities.index'));

    $response->assertOk();
    $response->assertSee('data-column="perimeter"', false);
    $response->assertSee('Site B');

    $content = $response->getContent();

    // The perimetre <th> is the first data column, right after the checkbox <th>.
    $checkboxPos = strpos($content, '<th width="10">');
    $perimeterThPos = strpos($content, 'data-column="perimeter"');
    $namePos = strpos($content, trans('cruds.entity.fields.name'));
    expect($checkboxPos)->toBeLessThan($perimeterThPos)
        ->and($perimeterThPos)->toBeLessThan($namePos);

    // Hidden by default via the datatable partial's hiddenColumns config
    // (rendered as a runtime table.column(...).visible(false) call, not the
    // literal @include arguments source).
    expect($content)->toContain('table.column(\'[data-column="perimeter"]\').visible(false);');
});
