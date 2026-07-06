<?php

use App\Models\Bay;
use App\Models\Building;
use App\Models\Peripheral;
use App\Models\PhysicalLink;
use App\Models\PhysicalServer;
use App\Models\Site;
use App\Models\StorageDevice;
use App\Models\User;
use App\Models\Workstation;
use App\Services\Graph\PhysicalInfrastructureGraphBuilder;
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

test('buildLocationDot draws the site-building-bay-server containment chain', function () {
    $site = Site::factory()->create();
    $building = Building::factory()->create(['site_id' => $site->id]);
    $bay = Bay::factory()->create(['building_id' => $building->id, 'site_id' => null]);
    $server = PhysicalServer::factory()->create(['bay_id' => $bay->id, 'building_id' => null, 'site_id' => null]);

    $builder = new PhysicalInfrastructureGraphBuilder;
    $dot = $builder->buildLocationDot(
        sites: Site::all(),
        buildings: Building::with('bays')->get(),
        bays: Bay::all(),
        physicalServers: PhysicalServer::with('bay', 'building', 'site')->get(),
        workstations: new Collection,
        storageDevices: new Collection,
        peripherals: new Collection,
        phones: new Collection,
        physicalSwitches: new Collection,
        physicalRouters: new Collection,
        wifiTerminals: new Collection,
        physicalSecurityDevices: new Collection,
    );

    expect($dot)
        ->toContain('S'.$site->id.' -> B'.$building->id)
        ->toContain('B'.$building->id.' -> BAY'.$bay->id)
        ->toContain('BAY'.$bay->id.' -> PSERVER'.$server->id);
});

test('buildLocationDot groups 5 or more workstations in a building into a single node', function () {
    $building = Building::factory()->create();
    $workstations = Workstation::factory()->count(5)->create(['building_id' => $building->id, 'site_id' => null]);

    $builder = new PhysicalInfrastructureGraphBuilder;
    $dot = $builder->buildLocationDot(
        sites: new Collection,
        buildings: Building::with('bays')->get(),
        bays: new Collection,
        physicalServers: new Collection,
        workstations: Workstation::all(),
        storageDevices: new Collection,
        peripherals: new Collection,
        phones: new Collection,
        physicalSwitches: new Collection,
        physicalRouters: new Collection,
        wifiTerminals: new Collection,
        physicalSecurityDevices: new Collection,
    );

    // The builder groups by $building->workstations()->first(), whose relation is ordered by name.
    $firstId = $building->workstations()->first()->id;

    expect($dot)
        ->toContain('WG'.$firstId.' [label="5 ')
        ->toContain('B'.$building->id.' -> WG'.$firstId);

    foreach ($workstations as $workstation) {
        expect($dot)->not->toContain('W'.$workstation->id.' [label="');
    }
});

test('buildConnectivityDot draws a physical link between a server and a peripheral', function () {
    $site = Site::factory()->create();
    $building = Building::factory()->create(['site_id' => $site->id]);
    $bay = Bay::factory()->create(['building_id' => $building->id, 'site_id' => null]);
    $server = PhysicalServer::factory()->create(['bay_id' => $bay->id, 'building_id' => null, 'site_id' => null]);
    $link = PhysicalLink::factory()->create(['physical_server_src_id' => $server->id]);
    $peripheral = Peripheral::factory()->create(['bay_id' => $bay->id, 'building_id' => null, 'site_id' => null]);
    $link->update(['peripheral_dest_id' => $peripheral->id]);

    $builder = new PhysicalInfrastructureGraphBuilder;
    $dot = $builder->buildConnectivityDot(
        sites: Site::with('buildings')->get(),
        buildings: Building::with('phones', 'workstations', 'wifiTerminals', 'physicalSwitches', 'physicalRouters', 'peripherals', 'bays')->get(),
        bays: Bay::all(),
        physicalServers: PhysicalServer::all(),
        workstations: new Collection,
        storageDevices: new Collection,
        peripherals: Peripheral::all(),
        phones: new Collection,
        physicalSwitches: new Collection,
        physicalRouters: new Collection,
        wifiTerminals: new Collection,
        physicalSecurityDevices: new Collection,
        physicalLinks: PhysicalLink::all(),
    );

    expect($dot)
        ->toContain('subgraph SITE_'.$site->id)
        ->toContain('subgraph ROOM_'.$building->id)
        ->toContain('subgraph BAY_'.$bay->id)
        ->toContain('PSERVER'.$server->id.' -> PER'.$peripheral->id);
});

test('buildConnectivityDot draws a physical server and a storage device attached directly to a building without a bay', function () {
    $site = Site::factory()->create();
    $building = Building::factory()->create(['site_id' => $site->id]);
    $server = PhysicalServer::factory()->create(['bay_id' => null, 'building_id' => $building->id, 'site_id' => null]);
    $storageDevice = StorageDevice::factory()->create(['bay_id' => null, 'building_id' => $building->id, 'site_id' => null]);

    $builder = new PhysicalInfrastructureGraphBuilder;
    $dot = $builder->buildConnectivityDot(
        sites: Site::with('buildings')->get(),
        buildings: Building::with('phones', 'workstations', 'wifiTerminals', 'physicalSwitches', 'physicalRouters', 'peripherals', 'physicalServers', 'storageDevices', 'bays')->get(),
        bays: new Collection,
        physicalServers: PhysicalServer::all(),
        workstations: new Collection,
        storageDevices: StorageDevice::all(),
        peripherals: new Collection,
        phones: new Collection,
        physicalSwitches: new Collection,
        physicalRouters: new Collection,
        wifiTerminals: new Collection,
        physicalSecurityDevices: new Collection,
        physicalLinks: new Collection,
    );

    expect($dot)
        ->toContain('PSERVER'.$server->id.' [label="')
        ->toContain('SD'.$storageDevice->id.' [label="');
});
