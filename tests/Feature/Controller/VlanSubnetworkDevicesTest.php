<?php

use App\Models\Router;
use App\Models\Subnetwork;
use App\Models\User;
use App\Models\Vlan;
use App\Models\Workstation;
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

test('lists a device whose IP belongs to a VLAN subnetwork', function () {
    $vlan = Vlan::factory()->create();
    Subnetwork::factory()->create([
        'address' => '10.0.0.0/24',
        'vlan_id' => $vlan->id,
    ]);
    $workstation = Workstation::factory()->create([
        'name' => 'PosteTest',
        'address_ip' => '10.0.0.5',
    ]);

    $response = $this->get(route('admin.vlans.show', $vlan->id));

    $response->assertOk();
    $response->assertSee('10.0.0.5');
    $response->assertSee('PosteTest');
    $response->assertSee(route('admin.workstations.show', $workstation->id), false);
});

test('excludes a device whose IP is outside the VLAN ranges', function () {
    $vlan = Vlan::factory()->create();
    Subnetwork::factory()->create([
        'address' => '10.0.0.0/24',
        'vlan_id' => $vlan->id,
    ]);
    Workstation::factory()->create([
        'name' => 'PosteHorsPlage',
        'address_ip' => '192.168.1.5',
    ]);

    $response = $this->get(route('admin.vlans.show', $vlan->id));

    $response->assertOk();
    $response->assertDontSee('PosteHorsPlage');
});

test('only matches the in-range IPs of a multi-IP field', function () {
    $vlan = Vlan::factory()->create();
    Subnetwork::factory()->create([
        'address' => '10.0.0.0/24',
        'vlan_id' => $vlan->id,
    ]);
    Router::factory()->create([
        'name' => 'RouteurMulti',
        'ip_addresses' => '10.0.0.9, 192.168.9.9',
    ]);

    $response = $this->get(route('admin.vlans.show', $vlan->id));

    $response->assertOk();
    $response->assertSee('10.0.0.9');
    $response->assertDontSee('192.168.9.9');
});

test('groups two objects sharing the same IP on a single row', function () {
    $vlan = Vlan::factory()->create();
    Subnetwork::factory()->create([
        'address' => '10.0.0.0/24',
        'vlan_id' => $vlan->id,
    ]);
    Workstation::factory()->create([
        'name' => 'PosteA',
        'address_ip' => '10.0.0.7',
    ]);
    Router::factory()->create([
        'name' => 'RouteurA',
        'ip_addresses' => '10.0.0.7',
    ]);

    $response = $this->get(route('admin.vlans.show', $vlan->id));

    $response->assertOk();
    $response->assertSee('PosteA');
    $response->assertSee('RouteurA');

    // Une seule ligne pour l'IP partagée : la valeur n'apparaît qu'une fois dans le HTML.
    expect(substr_count($response->getContent(), '10.0.0.7'))->toBe(1);
});

test('groups rows under the correct subnetwork with numerically ordered IPs', function () {
    $vlan = Vlan::factory()->create();
    Subnetwork::factory()->create([
        'name' => 'SousReseauA',
        'address' => '10.0.0.0/24',
        'vlan_id' => $vlan->id,
    ]);
    Subnetwork::factory()->create([
        'name' => 'SousReseauB',
        'address' => '10.0.1.0/24',
        'vlan_id' => $vlan->id,
    ]);

    Workstation::factory()->create(['name' => 'PosteHaut', 'address_ip' => '10.0.0.20']);
    Workstation::factory()->create(['name' => 'PosteBas', 'address_ip' => '10.0.0.3']);
    Workstation::factory()->create(['name' => 'PosteAutreSousReseau', 'address_ip' => '10.0.1.5']);

    $response = $this->get(route('admin.vlans.show', $vlan->id));

    $response->assertOk();
    $response->assertSeeInOrder([
        'SousReseauA',
        '10.0.0.3',
        '10.0.0.20',
        'SousReseauB',
        '10.0.1.5',
    ]);
});

test('shows an empty state for a subnetwork with no attached equipment', function () {
    $vlan = Vlan::factory()->create();
    Subnetwork::factory()->create([
        'name' => 'SousReseauVide',
        'address' => '10.0.2.0/24',
        'vlan_id' => $vlan->id,
    ]);

    $response = $this->get(route('admin.vlans.show', $vlan->id));

    $response->assertOk();
    $response->assertSee('SousReseauVide');
    $response->assertSee(trans('cruds.vlan.fields.no_devices'));
});
