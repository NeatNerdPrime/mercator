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

test('buildDot falls back from an out-of-scope parent subnetwork to its network, but only when that network is also in scope', function () {
    // The parent subnetwork is deliberately excluded from the $subnetworks collection passed to
    // buildDot (mirrors any scope narrower than "every subnetwork"), forcing the child into the
    // "link to the network instead" fallback branch.
    $network = Network::factory()->create();
    $parentSubnetwork = Subnetwork::factory()->create(['network_id' => $network->id]);
    $childSubnetwork = Subnetwork::factory()->create(['network_id' => $network->id, 'subnetwork_id' => $parentSubnetwork->id]);

    $builder = new LogicalInfrastructureGraphBuilder;
    $buildDot = fn (Collection $networks) => $builder->buildDot(
        networks: $networks,
        subnetworks: Subnetwork::query()->whereKey($childSubnetwork->id)->get(),
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
    );

    // Network in scope: the fallback edge is drawn, pointing at a node that really exists.
    expect($buildDot(Network::all()))->toContain('NET'.$network->id.' -> SUBNET'.$childSubnetwork->id);

    // Network NOT in scope: no NET node is declared for it, so the fallback must not draw a
    // dangling edge pointing at a node that was never written.
    $dotWithoutNetwork = $buildDot(new Collection);
    expect($dotWithoutNetwork)
        ->not->toContain('NET'.$network->id.' [shape=none label=<')
        ->not->toContain('NET'.$network->id.' -> SUBNET'.$childSubnetwork->id);
});

test('nodeWithIp appends the IP as its own label row only when show_ip is enabled', function () {
    $network = Network::factory()->create();
    $subnetwork = Subnetwork::factory()->create(['network_id' => $network->id, 'address' => '10.0.0.0/24']);

    $builder = new LogicalInfrastructureGraphBuilder;
    $buildDot = fn (bool $showIp) => $builder->buildDot(
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
        showIp: $showIp,
    );

    expect($buildDot(true))->toContain('<TR><TD>10.0.0.0/24</TD></TR>');
    expect($buildDot(false))->not->toContain('10.0.0.0/24');
});
