<?php

use App\Models\Activity;
use App\Models\Actor;
use App\Models\AdminUser;
use App\Models\Annuaire;
use App\Models\Application;
use App\Models\ApplicationBlock;
use App\Models\ApplicationFlow;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Bay;
use App\Models\Building;
use App\Models\Cartographer;
use App\Models\Certificate;
use App\Models\Cluster;
use App\Models\Container;
use App\Models\Database;
use App\Models\DataProcessing;
use App\Models\DhcpServer;
use App\Models\Dnsserver;
use App\Models\Domain;
use App\Models\Entity;
use App\Models\ExternalConnectedEntity;
use App\Models\ForestAd;
use App\Models\Gateway;
use App\Models\Graph;
use App\Models\Information;
use App\Models\Lan;
use App\Models\LogicalFlow;
use App\Models\LogicalServer;
use App\Models\MacroProcessus;
use App\Models\Man;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Operation;
use App\Models\Peripheral;
use App\Models\Phone;
use App\Models\PhysicalLink;
use App\Models\PhysicalRouter;
use App\Models\PhysicalSecurityDevice;
use App\Models\PhysicalServer;
use App\Models\PhysicalSwitch;
use App\Models\Process;
use App\Models\Relation;
use App\Models\Role;
use App\Models\Router;
use App\Models\SecurityControl;
use App\Models\SecurityDevice;
use App\Models\Site;
use App\Models\StorageDevice;
use App\Models\Subnetwork;
use App\Models\Task;
use App\Models\User;
use App\Models\Vlan;
use App\Models\Wan;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use App\Models\Zone;
use App\Models\ZoneAdmin;
use App\Support\ModelRegistry;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;

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

    app()->setLocale('en');
});

// ─── routesMap(CARTOGRAPHY_MODELS) vs the current Cartographer::cartographiableRoutesMap()
//     literal (52 entries, incl. Relation) ──────────────────────────────────

it('routesMap(CARTOGRAPHY_MODELS) matches cartographiableRoutesMap()', function () {
    $expected = [
        Activity::class => 'admin.activities.show',
        Actor::class => 'admin.actors.show',
        Annuaire::class => 'admin.annuaires.show',
        Application::class => 'admin.applications.show',
        ApplicationBlock::class => 'admin.application-blocks.show',
        ApplicationFlow::class => 'admin.application-flows.show',
        ApplicationModule::class => 'admin.application-modules.show',
        ApplicationService::class => 'admin.application-services.show',
        Backup::class => 'admin.backups.show',
        Bay::class => 'admin.bays.show',
        Building::class => 'admin.buildings.show',
        Certificate::class => 'admin.certificates.show',
        Cluster::class => 'admin.clusters.show',
        Container::class => 'admin.containers.show',
        Database::class => 'admin.databases.show',
        DhcpServer::class => 'admin.dhcp-servers.show',
        Dnsserver::class => 'admin.dnsservers.show',
        Domain::class => 'admin.domains.show',
        Entity::class => 'admin.entities.show',
        ExternalConnectedEntity::class => 'admin.external-connected-entities.show',
        ForestAd::class => 'admin.forest-ads.show',
        Gateway::class => 'admin.gateways.show',
        Information::class => 'admin.information.show',
        Lan::class => 'admin.lans.show',
        LogicalFlow::class => 'admin.logical-flows.show',
        LogicalServer::class => 'admin.logical-servers.show',
        MacroProcessus::class => 'admin.macro-processuses.show',
        Man::class => 'admin.mans.show',
        Network::class => 'admin.networks.show',
        NetworkSwitch::class => 'admin.network-switches.show',
        Operation::class => 'admin.operations.show',
        Peripheral::class => 'admin.peripherals.show',
        Phone::class => 'admin.phones.show',
        PhysicalLink::class => 'admin.physical-links.show',
        PhysicalRouter::class => 'admin.physical-routers.show',
        PhysicalSecurityDevice::class => 'admin.physical-security-devices.show',
        PhysicalServer::class => 'admin.physical-servers.show',
        PhysicalSwitch::class => 'admin.physical-switches.show',
        Process::class => 'admin.processes.show',
        Relation::class => 'admin.relations.show',
        Router::class => 'admin.routers.show',
        SecurityDevice::class => 'admin.security-devices.show',
        Site::class => 'admin.sites.show',
        StorageDevice::class => 'admin.storage-devices.show',
        Subnetwork::class => 'admin.subnetworks.show',
        Task::class => 'admin.tasks.show',
        Vlan::class => 'admin.vlans.show',
        Wan::class => 'admin.wans.show',
        WifiTerminal::class => 'admin.wifi-terminals.show',
        Workstation::class => 'admin.workstations.show',
        Zone::class => 'admin.zones.show',
        ZoneAdmin::class => 'admin.zone-admins.show',
    ];

    expect(ModelRegistry::CARTOGRAPHY_MODELS)->toHaveCount(52);
    expect(ModelRegistry::routesMap(ModelRegistry::CARTOGRAPHY_MODELS))->toBe($expected);
    expect(Cartographer::cartographiableRoutesMap())->toBe($expected);
});

// ─── titlesMap(CARTOGRAPHY_MODELS) vs cartographiableModelsList()
//     (52 entries, incl. Relation) ────────────────────────────────────────

it('titlesMap(CARTOGRAPHY_MODELS) matches cartographiableModelsList()', function () {
    $expected = [
        Activity::class => 'Activities',
        Actor::class => 'Actors',
        Annuaire::class => 'Administration Directory Services',
        Application::class => 'Applications',
        ApplicationBlock::class => 'Application blocks',
        ApplicationFlow::class => 'Application Flows',
        ApplicationModule::class => 'Application modules',
        ApplicationService::class => 'Application Services',
        Backup::class => 'Backups',
        Bay::class => 'Bays',
        Building::class => 'Buildings / Rooms',
        Certificate::class => 'Certificates',
        Cluster::class => 'Logical server cluster',
        Container::class => 'Containers',
        Database::class => 'Database',
        DhcpServer::class => 'DHCP servers',
        Dnsserver::class => 'Dns servers',
        Domain::class => 'Active Directory / LDAP domains',
        Entity::class => 'Entities',
        ExternalConnectedEntity::class => 'Connected external entities',
        ForestAd::class => 'Active Directory / LDAP forests',
        Gateway::class => 'Entrance gateways from the outside',
        Information::class => 'Information',
        Lan::class => 'LAN - Local Area Network',
        LogicalFlow::class => 'Logical flows',
        LogicalServer::class => 'Logical Servers',
        MacroProcessus::class => 'Macro-process',
        Man::class => 'MAN - Middle Area Network',
        Network::class => 'Networks',
        NetworkSwitch::class => 'Network switches',
        Operation::class => 'Operations',
        Peripheral::class => 'Peripheral devices',
        Phone::class => 'Phones',
        PhysicalLink::class => 'Physical links',
        PhysicalRouter::class => 'Physical routers',
        PhysicalSecurityDevice::class => 'Physical security equipment',
        PhysicalServer::class => 'Physical servers',
        PhysicalSwitch::class => 'Physical switches',
        Process::class => 'Process',
        Relation::class => 'Relationships',
        Router::class => 'Routers',
        SecurityDevice::class => 'Logical security devices',
        Site::class => 'Sites',
        StorageDevice::class => 'Storage infrastructure',
        Subnetwork::class => 'Subnets',
        Task::class => 'Tasks',
        Vlan::class => 'VLAN - Virtual Local Area Network',
        Wan::class => 'WAN - Wide Area Network',
        WifiTerminal::class => 'Wifi terminals',
        Workstation::class => 'Workstations',
        Zone::class => 'Security Zones',
        ZoneAdmin::class => 'Administration areas',
    ];

    expect(ModelRegistry::CARTOGRAPHY_MODELS)->toHaveCount(52);
    expect(ModelRegistry::titlesMap(ModelRegistry::CARTOGRAPHY_MODELS))->toBe($expected);
    expect(Cartographer::cartographiableModelsList())->toBe($expected);
});

// ─── Iso-behavior: routesMap(LINKABLE_MODELS) vs the frozen pre-refactor
//     ShowLink::$routes literal (59 entries, superset of CARTOGRAPHY_MODELS) ─

it('routesMap(LINKABLE_MODELS) matches the frozen pre-refactor ShowLink::$routes', function () {
    $expected = [
        Activity::class => 'admin.activities.show',
        Actor::class => 'admin.actors.show',
        AdminUser::class => 'admin.admin-users.show',
        Annuaire::class => 'admin.annuaires.show',
        Application::class => 'admin.applications.show',
        ApplicationBlock::class => 'admin.application-blocks.show',
        ApplicationFlow::class => 'admin.application-flows.show',
        ApplicationModule::class => 'admin.application-modules.show',
        ApplicationService::class => 'admin.application-services.show',
        AuditLog::class => 'admin.audit-logs.show',
        Backup::class => 'admin.backups.show',
        Bay::class => 'admin.bays.show',
        Building::class => 'admin.buildings.show',
        Certificate::class => 'admin.certificates.show',
        Cluster::class => 'admin.clusters.show',
        Container::class => 'admin.containers.show',
        Database::class => 'admin.databases.show',
        DataProcessing::class => 'admin.data-processings.show',
        DhcpServer::class => 'admin.dhcp-servers.show',
        Dnsserver::class => 'admin.dnsservers.show',
        Domain::class => 'admin.domains.show',
        Entity::class => 'admin.entities.show',
        ExternalConnectedEntity::class => 'admin.external-connected-entities.show',
        ForestAd::class => 'admin.forest-ads.show',
        Gateway::class => 'admin.gateways.show',
        Graph::class => 'admin.graphs.show',
        Information::class => 'admin.information.show',
        Lan::class => 'admin.lans.show',
        LogicalFlow::class => 'admin.logical-flows.show',
        LogicalServer::class => 'admin.logical-servers.show',
        MacroProcessus::class => 'admin.macro-processuses.show',
        Man::class => 'admin.mans.show',
        Network::class => 'admin.networks.show',
        NetworkSwitch::class => 'admin.network-switches.show',
        Operation::class => 'admin.operations.show',
        Peripheral::class => 'admin.peripherals.show',
        Phone::class => 'admin.phones.show',
        PhysicalLink::class => 'admin.physical-links.show',
        PhysicalRouter::class => 'admin.physical-routers.show',
        PhysicalSecurityDevice::class => 'admin.physical-security-devices.show',
        PhysicalServer::class => 'admin.physical-servers.show',
        PhysicalSwitch::class => 'admin.physical-switches.show',
        Process::class => 'admin.processes.show',
        Relation::class => 'admin.relations.show',
        Role::class => 'admin.roles.show',
        Router::class => 'admin.routers.show',
        SecurityControl::class => 'admin.security-controls.show',
        SecurityDevice::class => 'admin.security-devices.show',
        Site::class => 'admin.sites.show',
        StorageDevice::class => 'admin.storage-devices.show',
        Subnetwork::class => 'admin.subnetworks.show',
        Task::class => 'admin.tasks.show',
        User::class => 'admin.users.show',
        Vlan::class => 'admin.vlans.show',
        Wan::class => 'admin.wans.show',
        WifiTerminal::class => 'admin.wifi-terminals.show',
        Workstation::class => 'admin.workstations.show',
        Zone::class => 'admin.zones.show',
        ZoneAdmin::class => 'admin.zone-admins.show',
    ];

    expect(ModelRegistry::LINKABLE_MODELS)->toHaveCount(59);
    expect(ModelRegistry::routesMap(ModelRegistry::LINKABLE_MODELS))->toBe($expected);
});

it('LINKABLE_MODELS is a superset of CARTOGRAPHY_MODELS, adding exactly the known 7 non-cartographiable models', function () {
    $extra = array_values(array_diff(ModelRegistry::LINKABLE_MODELS, ModelRegistry::CARTOGRAPHY_MODELS));
    sort($extra);

    $expectedExtra = [
        AdminUser::class,
        AuditLog::class,
        DataProcessing::class,
        Graph::class,
        Role::class,
        SecurityControl::class,
        User::class,
    ];
    sort($expectedExtra);

    expect(array_diff(ModelRegistry::CARTOGRAPHY_MODELS, ModelRegistry::LINKABLE_MODELS))->toBe([]);
    expect($extra)->toBe($expectedExtra);
});

// ─── Zero-exception invariant: every LINKABLE_MODELS route actually exists,
//     every CARTOGRAPHY_MODELS title key exists in EN and FR ────────────────

it('every LINKABLE_MODELS class has a real registered admin.*.show route', function () {
    foreach (ModelRegistry::LINKABLE_MODELS as $class) {
        $routeName = ModelRegistry::routeName($class);
        expect(Route::has($routeName))->toBeTrue("Missing route [{$routeName}] derived for {$class}");
    }
});

it('every CARTOGRAPHY_MODELS class has a title key present in EN and FR', function () {
    foreach (ModelRegistry::CARTOGRAPHY_MODELS as $class) {
        $key = ModelRegistry::titleKey($class);
        expect(Lang::hasForLocale($key, 'en'))->toBeTrue("Missing EN translation [{$key}] for {$class}");
        expect(Lang::hasForLocale($key, 'fr'))->toBeTrue("Missing FR translation [{$key}] for {$class}");
    }
});

// ─── Business structures: content preserved exactly ────────────────────────

it('reportVueMap() preserves every model -> vue id mapping unchanged', function () {
    $map = ModelRegistry::reportVueMap();

    expect($map)->toHaveCount(55);
    expect($map[Entity::class])->toBe('1');
    expect($map[Relation::class])->toBe('1');
    expect($map[DataProcessing::class])->toBe('7');
    expect($map[SecurityControl::class])->toBe('7');
    expect($map[Man::class])->toBe('6');
    expect($map[PhysicalLink::class])->toBe('6');
});

// ─── Guard-rail: every CARTOGRAPHY_MODELS entry has a non-null route and title ─

it('every CARTOGRAPHY_MODELS class has a non-empty route and title', function () {
    foreach (ModelRegistry::CARTOGRAPHY_MODELS as $class) {
        expect(ModelRegistry::routeName($class))->not->toBeEmpty();
        expect(ModelRegistry::title($class))->not->toBeEmpty();
    }
});

// ─── Derivation engine: the one documented Man exception is scoped to routeName(),
//     never to bare slug() (used by QueryEngineIntrospector::modelToApiName()) ──

it('slug() stays a pure derivation for Man (no override), while routeName() applies the one documented exception', function () {
    expect(ModelRegistry::slug(Man::class))->toBe('men');
    expect(ModelRegistry::routeName(Man::class))->toBe('admin.mans.show');
});
