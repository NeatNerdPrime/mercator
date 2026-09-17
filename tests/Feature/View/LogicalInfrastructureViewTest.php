<?php

use App\Models\Certificate;
use App\Models\Cluster;
use App\Models\Container;
use App\Models\LogicalServer;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Subnetwork;
use App\Models\User;
use App\Models\Vlan;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

    test('all-data view does not issue N+1 queries as the infrastructure grows', function () {
        // Incident du 2026-09-15 : sans eager loading, buildDot() (et les filtres du contrôleur)
        // font une requête par LogicalServer/Cluster/Certificate/Container/NetworkSwitch pour
        // charger leurs relations (clusters/certificates/containers/vlans), ce qui a fini par
        // dépasser les 30s d'exécution en prod sur un jeu de données réel. Ce test fait grossir
        // le jeu de données et vérifie que le nombre de requêtes ne grossit pas avec lui.
        $buildDataset = function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                $logicalServer = LogicalServer::factory()->create();
                $logicalServer->clusters()->attach(Cluster::factory()->create());
                $logicalServer->certificates()->attach(Certificate::factory()->create());
                $logicalServer->containers()->attach(Container::factory()->create());

                $networkSwitch = NetworkSwitch::factory()->create();
                $networkSwitch->vlans()->attach(Vlan::factory()->create());
            }
        };

        $buildDataset(2);

        DB::enableQueryLog();
        $response = $this->get(route('admin.report.view.logical-infrastructure'));
        $response->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::flushQueryLog();

        $buildDataset(15);
        DB::flushQueryLog();

        $response = $this->get(route('admin.report.view.logical-infrastructure'));
        $response->assertOk();
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 13 lignes de plus par type (5 types) ne doit pas coûter 13*5 requêtes de plus : une
        // faible marge fixe absorbe les variations normales, une régression N+1 la dépasserait
        // largement (65+ requêtes supplémentaires).
        expect($largeCount - $smallCount)->toBeLessThan(15);
    });

    test('denies access without permission', function () {
        // New user without the reports_access permission
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.report.view.logical-infrastructure'));

        $response->assertForbidden();
    });
});
