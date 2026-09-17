<?php

use App\Models\Cluster;
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

function maxElementsPerNode(): int
{
    return (new ReflectionClass(LogicalInfrastructureGraphBuilder::class))->getConstant('MAX_ELEMENTS_PER_NODE');
}

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

test('buildDot caps a cluster at MAX_ELEMENTS_PER_NODE logical servers and attaches a "..." node to it for the rest', function () {
    $max = maxElementsPerNode();
    $cluster = Cluster::factory()->create();
    $logicalServers = LogicalServer::factory()->count($max + 5)->create();
    $cluster->logicalServers()->attach($logicalServers->pluck('id'));

    $builder = new LogicalInfrastructureGraphBuilder;
    $dot = $builder->buildDot(
        networks: new Collection,
        subnetworks: new Collection,
        gateways: new Collection,
        externalConnectedEntities: new Collection,
        vlans: new Collection,
        networkSwitches: new Collection,
        clusters: Cluster::all(),
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

    // Un vrai serveur logique + le nœud "..." pointent tous les deux vers CLUSTER<id> : le
    // décompte global d'arêtes reste max+1, mais seule la dernière doit venir du nœud "...".
    $edgeCount = substr_count($dot, '-> CLUSTER'.$cluster->id);
    expect($edgeCount)->toBe($max + 1);
    expect($dot)->toContain('CLUSTER'.$cluster->id.'_MORE [shape=plaintext label="..."]');
    expect($dot)->toContain('CLUSTER'.$cluster->id.'_MORE -> CLUSTER'.$cluster->id);
});

test('buildDot caps a subnetwork at MAX_ELEMENTS_PER_NODE logical servers and attaches a "..." node to it for the rest', function () {
    $max = maxElementsPerNode();
    $subnetwork = Subnetwork::factory()->create(['address' => '10.0.0.0/24']);
    foreach (range(1, $max + 5) as $i) {
        LogicalServer::factory()->create(['address_ip' => "10.0.0.{$i}"]);
    }

    $builder = new LogicalInfrastructureGraphBuilder;
    $dot = $builder->buildDot(
        networks: new Collection,
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

    $edgeCount = substr_count($dot, 'SUBNET'.$subnetwork->id.' -> LOGICAL_SERVER');
    expect($edgeCount)->toBe($max);
    expect($dot)->toContain('SUBNET'.$subnetwork->id.'_MORE [shape=plaintext label="..."]');
    expect($dot)->toContain('SUBNET'.$subnetwork->id.' -> SUBNET'.$subnetwork->id.'_MORE');
});

test('buildDot caps logical servers with no cluster, subnetwork, certificate or container at MAX_ELEMENTS_PER_NODE and adds an unlinked "..." node for the rest', function () {
    $max = maxElementsPerNode();
    // No subnetwork/cluster/certificate/container passed in, and no address_ip set: every one
    // of these servers is a genuine orphan (no parent, no child) in the graph.
    LogicalServer::factory()->count($max + 5)->create(['address_ip' => null]);

    $builder = new LogicalInfrastructureGraphBuilder;
    $dot = $builder->buildDot(
        networks: new Collection,
        subnetworks: new Collection,
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

    // Each shown logical server draws exactly one DotNode table; count those instead of guessing
    // node-id text, since ids are auto-incremented and not otherwise predictable here.
    expect(substr_count($dot, '<TABLE border="0" cellborder="0" cellspacing="0">'))->toBe($max);
    expect($dot)->toContain('LOGICAL_SERVER_ORPHANS_MORE [shape=plaintext label="..."]');
    expect($dot)->not->toContain('LOGICAL_SERVER_ORPHANS_MORE ->');
    expect($dot)->not->toContain('-> LOGICAL_SERVER_ORPHANS_MORE');
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
