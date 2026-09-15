<?php

use App\Models\LogicalServer;
use App\Models\Network;
use App\Models\Subnetwork;
use App\Models\User;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed base permissions/roles and users as in other feature tests
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    // Login as an admin (id=1 seeded by UsersTableSeeder)
    $this->user = User::query()->where('login', 'admin@admin.com')->first();
    $this->actingAs($this->user);
});

describe('Logical Infrastructure View', function () {
    test('can display logical-infrastructure view page', function () {
        $response = $this->get(route('admin.report.view.logical-infrastructure'));

        $response->assertOk();
        $response->assertViewIs('admin.reports.logical_infrastructure');
        $response->assertViewHasAll([
            'all_networks',
            'all_subnetworks',
            'networks',
            'subnetworks',
            'gateways',
            'externalConnectedEntities',
            'networkSwitches',
            'workstations',
            'phones',
            'physicalSecurityDevices',
            'peripherals',
            'wifiTerminals',
            'routers',
            'securityDevices',
            'storageDevices',
            'dhcpServers',
            'dnsservers',
            'clusters',
            'logicalServers',
            'certificates',
            'containers',
            'vlans',
        ]);
    });

    test('can display filtered view for a selected network with show_ip enabled', function () {
        $network = Network::factory()->create();
        $subnetwork = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '10.0.0.0/24']);
        LogicalServer::factory()->create(['address_ip' => '10.0.0.5']);

        $response = $this->get(route('admin.report.view.logical-infrastructure', [
            'network' => $network->id,
            'show_ip' => 1,
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.reports.logical_infrastructure');
        $response->assertViewHas('subnetworks', function ($subnetworks) use ($subnetwork) {
            return $subnetworks->contains('id', $subnetwork->id);
        });
    });

    test('can display filtered view for a selected subnetwork including its descendants', function () {
        $network = Network::factory()->create();
        $root = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '10.0.0.0/16']);
        $child = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '10.0.1.0/24', 'subnetwork_id' => $root->id]);
        $grandchild = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '10.0.1.128/25', 'subnetwork_id' => $child->id]);
        $unrelated = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '192.168.0.0/24']);

        $response = $this->get(route('admin.report.view.logical-infrastructure', [
            'network' => $network->id,
            'subnetwork' => $root->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('subnetworks', function ($subnetworks) use ($root, $child, $grandchild, $unrelated) {
            return $subnetworks->contains('id', $root->id)
                && $subnetworks->contains('id', $child->id)
                && $subnetworks->contains('id', $grandchild->id)
                && ! $subnetworks->contains('id', $unrelated->id);
        });
    });

    test('denies access without permission', function () {
        // New user without the reports_access permission
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.report.view.logical-infrastructure'));

        $response->assertForbidden();
    });
});
