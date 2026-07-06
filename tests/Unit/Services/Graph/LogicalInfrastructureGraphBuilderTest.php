<?php

use App\Models\LogicalServer;
use App\Models\Network;
use App\Models\Subnetwork;
use App\Models\User;
use App\Services\Graph\LogicalInfrastructureGraphBuilder;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    $this->actingAs(User::query()->where('login', 'admin@admin.com')->first());
});

test('buildDot links a network to its subnetwork and a logical server found by IP containment', function () {
    $network = Network::factory()->create();
    $subnetwork = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '10.0.0.0/24']);
    $logicalServer = LogicalServer::factory()->create(['address_ip' => '10.0.0.5']);

    $builder = new LogicalInfrastructureGraphBuilder;
    $dot = $builder->buildDot(
        networks: Network::all(),
        subnetworks: Subnetwork::all(),
        gateways: new Collection,
        externalConnectedEntities: new Collection,
        vlans: new Collection,
        networkSwitches: new Collection,
        clusters: new Collection,
        logicalServers: LogicalServer::all(),
        dhcpServers: new Collection,
        dnsservers: new Collection,
        certificates: new Collection,
        containers: new Collection,
        routers: new Collection,
        securityDevices: new Collection,
        workstations: new Collection,
        wifiTerminals: new Collection,
        phones: new Collection,
        peripherals: new Collection,
        physicalSecurityDevices: new Collection,
        storageDevices: new Collection,
    );

    expect($dot)
        ->toContain('NET'.$network->id.' -> SUBNET'.$subnetwork->id)
        ->toContain('SUBNET'.$subnetwork->id.' -> LOGICAL_SERVER'.$logicalServer->id);
});

test('nodeWithIp appends the IP and grows the height only when show_ip is enabled', function () {
    $network = Network::factory()->create();
    $subnetwork = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '10.0.0.0/24']);

    $builder = new LogicalInfrastructureGraphBuilder;
    $dotWithIp = $builder->buildDot(
        networks: new Collection,
        subnetworks: Subnetwork::all(),
        gateways: new Collection,
        externalConnectedEntities: new Collection,
        vlans: new Collection,
        networkSwitches: new Collection,
        clusters: new Collection,
        logicalServers: new Collection,
        dhcpServers: new Collection,
        dnsservers: new Collection,
        certificates: new Collection,
        containers: new Collection,
        routers: new Collection,
        securityDevices: new Collection,
        workstations: new Collection,
        wifiTerminals: new Collection,
        phones: new Collection,
        peripherals: new Collection,
        physicalSecurityDevices: new Collection,
        storageDevices: new Collection,
        showIp: true,
    );

    expect($dotWithIp)
        ->toContain('height=1.7')
        ->toContain(chr(13).'10.0.0.0/24');
});
