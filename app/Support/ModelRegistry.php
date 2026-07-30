<?php

namespace App\Support;

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
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Single source of truth for model metadata that used to be duplicated across
 * several hand-maintained maps (Cartographer, ShowLink, GlobalSearchController,
 * MonarcController, MonarcExportService, WordHelper, QueryEngineIntrospector):
 * route names, title translation keys, and the business whitelists/groupings
 * built on top of them.
 *
 * slug()/routeName()/titleKey()/title() are pure derivations from the class
 * name — no per-model override table — except for the single documented
 * exception in ROUTE_SLUG_OVERRIDES (see its docblock). Everything else
 * (CARTOGRAPHY_MODELS, LINKABLE_MODELS, the Monarc/report structures) is
 * explicit data because it encodes a business decision, not something
 * derivable from the class name.
 */
final class ModelRegistry
{
    protected const MODEL_NAMESPACE = 'App\\Models\\';

    /** Mirrors QueryEngineIntrospector's original exclusions. */
    private const EXCLUDED_MODELS = ['User', 'PasswordReset'];

    /**
     * The sole exception to pure route-slug derivation: Str::plural(Str::snake('Man','-'))
     * derives 'men' (correct English pluralization), but the route actually registered
     * (and the one ShowLink's original hardcoded map already relied on) is 'admin.mans.show'.
     * Scoped to routeName() only — bare slug() stays a pure function, so callers that don't
     * go through admin.*.show (e.g. QueryEngineIntrospector::modelToApiName(), which already
     * produced 'men' for Man before this refactor) keep their exact prior output.
     */
    private const ROUTE_SLUG_OVERRIDES = [
        'Man' => 'mans',
    ];

    /**
     * The ~51 models with a Cartographer-assignable route (ex-Cartographer::cartographiableRoutesMap()
     * keys). Drives AppServiceProvider's observer registration and the roles/users cartographer UI —
     * this exact set, unchanged.
     */
    public const CARTOGRAPHY_MODELS = [
        Activity::class,
        Actor::class,
        Annuaire::class,
        Application::class,
        ApplicationBlock::class,
        ApplicationFlow::class,
        ApplicationModule::class,
        ApplicationService::class,
        Backup::class,
        Bay::class,
        Building::class,
        Certificate::class,
        Cluster::class,
        Container::class,
        Database::class,
        DhcpServer::class,
        Dnsserver::class,
        Domain::class,
        Entity::class,
        ExternalConnectedEntity::class,
        ForestAd::class,
        Gateway::class,
        Information::class,
        Lan::class,
        LogicalFlow::class,
        LogicalServer::class,
        MacroProcessus::class,
        Man::class,
        Network::class,
        NetworkSwitch::class,
        Operation::class,
        Peripheral::class,
        Phone::class,
        PhysicalLink::class,
        PhysicalRouter::class,
        PhysicalSecurityDevice::class,
        PhysicalServer::class,
        PhysicalSwitch::class,
        Process::class,
        Router::class,
        SecurityDevice::class,
        Site::class,
        StorageDevice::class,
        Subnetwork::class,
        Task::class,
        Vlan::class,
        Wan::class,
        WifiTerminal::class,
        Workstation::class,
        Zone::class,
        ZoneAdmin::class,
    ];

    /**
     * CARTOGRAPHY_MODELS plus Relation — the ~52-model set ex-Cartographer::cartographiableModelsList()
     * actually used (Relation has a working title and an admin.relations.show route via ShowLink, but
     * was never added to CARTOGRAPHY_MODELS/cartographiableRoutesMap(); pre-existing asymmetry, kept
     * as-is rather than silently unified).
     */
    public const CARTOGRAPHY_TITLE_MODELS = [
        Activity::class,
        Actor::class,
        Annuaire::class,
        Application::class,
        ApplicationBlock::class,
        ApplicationFlow::class,
        ApplicationModule::class,
        ApplicationService::class,
        Backup::class,
        Bay::class,
        Building::class,
        Certificate::class,
        Cluster::class,
        Container::class,
        Database::class,
        DhcpServer::class,
        Dnsserver::class,
        Domain::class,
        Entity::class,
        ExternalConnectedEntity::class,
        ForestAd::class,
        Gateway::class,
        Information::class,
        Lan::class,
        LogicalFlow::class,
        LogicalServer::class,
        MacroProcessus::class,
        Man::class,
        Network::class,
        NetworkSwitch::class,
        Operation::class,
        Peripheral::class,
        Phone::class,
        PhysicalLink::class,
        PhysicalRouter::class,
        PhysicalSecurityDevice::class,
        PhysicalServer::class,
        PhysicalSwitch::class,
        Process::class,
        Relation::class,
        Router::class,
        SecurityDevice::class,
        Site::class,
        StorageDevice::class,
        Subnetwork::class,
        Task::class,
        Vlan::class,
        Wan::class,
        WifiTerminal::class,
        Workstation::class,
        Zone::class,
        ZoneAdmin::class,
    ];

    /**
     * The ~59 models ShowLink can render a show-link for (superset of CARTOGRAPHY_MODELS: adds
     * AdminUser, AuditLog, DataProcessing, Graph, Relation, Role, SecurityControl, User — models
     * with a show route but no cartographer assignment concept).
     */
    public const LINKABLE_MODELS = [
        Activity::class,
        Actor::class,
        AdminUser::class,
        Annuaire::class,
        Application::class,
        ApplicationBlock::class,
        ApplicationFlow::class,
        ApplicationModule::class,
        ApplicationService::class,
        AuditLog::class,
        Backup::class,
        Bay::class,
        Building::class,
        Certificate::class,
        Cluster::class,
        Container::class,
        Database::class,
        DataProcessing::class,
        DhcpServer::class,
        Dnsserver::class,
        Domain::class,
        Entity::class,
        ExternalConnectedEntity::class,
        ForestAd::class,
        Gateway::class,
        Graph::class,
        Information::class,
        Lan::class,
        LogicalFlow::class,
        LogicalServer::class,
        MacroProcessus::class,
        Man::class,
        Network::class,
        NetworkSwitch::class,
        Operation::class,
        Peripheral::class,
        Phone::class,
        PhysicalLink::class,
        PhysicalRouter::class,
        PhysicalSecurityDevice::class,
        PhysicalServer::class,
        PhysicalSwitch::class,
        Process::class,
        Relation::class,
        Role::class,
        Router::class,
        SecurityControl::class,
        SecurityDevice::class,
        Site::class,
        StorageDevice::class,
        Subnetwork::class,
        Task::class,
        User::class,
        Vlan::class,
        Wan::class,
        WifiTerminal::class,
        Workstation::class,
        Zone::class,
        ZoneAdmin::class,
    ];

    /**
     * Ex-MonarcController::SELECTABLE_MODELS — whitelist of Mercator models selectable for
     * Monarc export, keyed by the short name used throughout the selection payload/UI.
     */
    public const MONARC_SELECTABLE_MODELS = [
        'MacroProcessus' => MacroProcessus::class,
        'Process' => Process::class,
        'Information' => Information::class,
        'Actor' => Actor::class,
        'Application' => Application::class,
        'ApplicationService' => ApplicationService::class,
        'ApplicationBlock' => ApplicationBlock::class,
        'ApplicationModule' => ApplicationModule::class,
        'Database' => Database::class,
        'LogicalServer' => LogicalServer::class,
        'Cluster' => Cluster::class,
        'Container' => Container::class,
        'Backup' => Backup::class,
        'Network' => Network::class,
        'Subnetwork' => Subnetwork::class,
        'Gateway' => Gateway::class,
        'Router' => Router::class,
        'NetworkSwitch' => NetworkSwitch::class,
        'SecurityDevice' => SecurityDevice::class,
        'DhcpServer' => DhcpServer::class,
        'Dnsserver' => Dnsserver::class,
        'Vlan' => Vlan::class,
        'ExternalConnectedEntity' => ExternalConnectedEntity::class,
        'Lan' => Lan::class,
        'Man' => Man::class,
        'Wan' => Wan::class,
        'PhysicalServer' => PhysicalServer::class,
        'Workstation' => Workstation::class,
        'Site' => Site::class,
        'Building' => Building::class,
        'Bay' => Bay::class,
        'PhysicalSwitch' => PhysicalSwitch::class,
        'PhysicalRouter' => PhysicalRouter::class,
        'PhysicalSecurityDevice' => PhysicalSecurityDevice::class,
        'StorageDevice' => StorageDevice::class,
        'Peripheral' => Peripheral::class,
        'Phone' => Phone::class,
        'WifiTerminal' => WifiTerminal::class,
        'Zone' => Zone::class,
        'Entity' => Entity::class,
        'Relation' => Relation::class,
        'Domain' => Domain::class,
        'ForestAd' => ForestAd::class,
        'Annuaire' => Annuaire::class,
        'ZoneAdmin' => ZoneAdmin::class,
        'AdminUser' => AdminUser::class,
    ];

    /**
     * Ex-MonarcController::SIDEBAR_FAMILY_ORDER — every selectable family, in the exact order it
     * appears in resources/views/partials/sidebar.blade.php (submenu order, then within-submenu
     * order). Drives family display order on the Monarc selection screen.
     */
    public const MONARC_SIDEBAR_FAMILY_ORDER = [
        'Entity', 'Relation',
        'MacroProcessus', 'Process', 'Actor', 'Information',
        'ApplicationBlock', 'Application', 'ApplicationService', 'ApplicationModule', 'Database',
        'ZoneAdmin', 'Annuaire', 'ForestAd', 'Domain', 'AdminUser',
        'Network', 'Subnetwork', 'Gateway', 'ExternalConnectedEntity', 'Router', 'NetworkSwitch',
        'SecurityDevice', 'DhcpServer', 'Dnsserver', 'Cluster', 'LogicalServer', 'Backup', 'Container', 'Vlan',
        'Site', 'Building', 'Bay', 'Zone', 'PhysicalServer', 'Workstation', 'StorageDevice', 'Peripheral',
        'Phone', 'PhysicalSwitch', 'PhysicalRouter', 'WifiTerminal', 'PhysicalSecurityDevice', 'Wan', 'Man', 'Lan',
    ];

    /**
     * Ex-MonarcExportService::PRIMARY_FAMILIES — families whose objects are "primary assets",
     * always instance roots in the Monarc analysis tree.
     */
    public const MONARC_PRIMARY_FAMILIES = ['MacroProcessus', 'Process', 'Information'];

    /**
     * Ex-MonarcExportService::FAMILY_VIEWS — Mercator "views" grouping for the Monarc library/
     * selection screen.
     */
    public const MONARC_FAMILY_VIEWS = [
        'ecosystem' => ['Entity', 'Relation'],
        'information_system' => ['MacroProcessus', 'Process', 'Actor', 'Information'],
        'applications' => ['ApplicationBlock', 'Application', 'ApplicationService', 'ApplicationModule', 'Database'],
        'administration' => ['Domain', 'ForestAd', 'Annuaire', 'ZoneAdmin', 'AdminUser'],
        'logical_infrastructure' => ['Network', 'LogicalServer', 'Cluster', 'Container', 'Backup', 'Subnetwork', 'Gateway', 'Router', 'NetworkSwitch', 'SecurityDevice', 'DhcpServer', 'Dnsserver', 'Vlan', 'ExternalConnectedEntity'],
        'physical_infrastructure' => ['Site', 'Building', 'Bay', 'Zone', 'PhysicalServer', 'PhysicalSwitch', 'PhysicalRouter', 'Workstation', 'StorageDevice', 'Peripheral', 'Phone', 'WifiTerminal', 'PhysicalSecurityDevice', 'Wan', 'Man', 'Lan'],
    ];

    /**
     * Ex-WordHelper::MODEL_VUE_MAP — maps each cartographied model to the `vues[]` id of the
     * report section it belongs to. Derived directly from docs/analysis-cartography-report.md §1.3.4.
     *
     * @var array<class-string, string>
     */
    public const REPORT_VUE_MAP = [
        Entity::class => '1',
        Relation::class => '1',
        MacroProcessus::class => '2',
        Process::class => '2',
        Activity::class => '2',
        Operation::class => '2',
        Task::class => '2',
        Actor::class => '2',
        Information::class => '2',
        ApplicationBlock::class => '3',
        Application::class => '3',
        ApplicationService::class => '3',
        ApplicationModule::class => '3',
        Database::class => '3',
        ApplicationFlow::class => '3',
        ZoneAdmin::class => '4',
        Annuaire::class => '4',
        ForestAd::class => '4',
        Domain::class => '4',
        AdminUser::class => '4',
        Network::class => '5',
        Subnetwork::class => '5',
        Gateway::class => '5',
        ExternalConnectedEntity::class => '5',
        Router::class => '5',
        NetworkSwitch::class => '5',
        SecurityDevice::class => '5',
        DhcpServer::class => '5',
        Dnsserver::class => '5',
        LogicalServer::class => '5',
        Cluster::class => '5',
        Backup::class => '5',
        Container::class => '5',
        LogicalFlow::class => '5',
        Vlan::class => '5',
        Certificate::class => '5',
        Site::class => '6',
        Building::class => '6',
        Bay::class => '6',
        Zone::class => '6',
        PhysicalServer::class => '6',
        Workstation::class => '6',
        StorageDevice::class => '6',
        Peripheral::class => '6',
        Phone::class => '6',
        PhysicalSwitch::class => '6',
        PhysicalRouter::class => '6',
        WifiTerminal::class => '6',
        PhysicalSecurityDevice::class => '6',
        PhysicalLink::class => '6',
        Wan::class => '6',
        Man::class => '6',
        Lan::class => '6',
        DataProcessing::class => '7',
        SecurityControl::class => '7',
    ];

    // ─── Derivation engine (pure functions) ────────────────────────────────

    /**
     * Route-segment slug for a model, e.g. LogicalServer -> logical-servers. Pure
     * Str::plural(Str::snake()) derivation, no overrides — see ROUTE_SLUG_OVERRIDES for the one
     * place (routeName()) where a model's real admin route diverges from this.
     */
    public static function slug(string $class): string
    {
        return Str::plural(Str::snake(class_basename($class), '-'));
    }

    /** Admin route name for a model's action, e.g. routeName(LogicalServer::class) -> 'admin.logical-servers.show'. */
    public static function routeName(string $class, string $action = 'show'): string
    {
        $short = class_basename($class);
        $slug = self::ROUTE_SLUG_OVERRIDES[$short] ?? self::slug($class);

        return "admin.{$slug}.{$action}";
    }

    /** Translation key for a model's CRUD title, e.g. LogicalServer -> 'cruds.logicalServer.title'. */
    public static function titleKey(string $class): string
    {
        return 'cruds.'.Str::camel(class_basename($class)).'.title';
    }

    /** Resolved, translated title for a model. */
    public static function title(string $class): string
    {
        return trans(self::titleKey($class));
    }

    // ─── Convenience constructors ───────────────────────────────────────────

    /**
     * @param  array<int, class-string>|null  $only  Defaults to CARTOGRAPHY_MODELS.
     * @return array<class-string, string> Model class => 'admin.x.show'.
     */
    public static function routesMap(?array $only = null): array
    {
        $classes = $only ?? self::CARTOGRAPHY_MODELS;

        $map = [];
        foreach ($classes as $class) {
            $map[$class] = self::routeName($class);
        }

        return $map;
    }

    /**
     * @param  array<int, class-string>|null  $only  Defaults to CARTOGRAPHY_TITLE_MODELS.
     * @return array<class-string, string> Model class => translated title.
     */
    public static function titlesMap(?array $only = null): array
    {
        $classes = $only ?? self::CARTOGRAPHY_TITLE_MODELS;

        $map = [];
        foreach ($classes as $class) {
            $map[$class] = self::title($class);
        }

        return $map;
    }

    /**
     * Same as titlesMap() but keyed by short class name — for consumers (GlobalSearchController,
     * MonarcExportService::familyLabel()) that index their own whitelist by short name rather
     * than FQCN.
     *
     * @param  array<int, string>  $shortNames
     * @return array<string, string> Short name => translated title.
     */
    public static function titlesMapByShortName(array $shortNames): array
    {
        $map = [];
        foreach ($shortNames as $short) {
            $map[$short] = self::title(self::MODEL_NAMESPACE.$short);
        }

        return $map;
    }

    // ─── Business structure accessors ───────────────────────────────────────

    /** @return array<string, class-string> Short name => model class. */
    public static function selectableMonarc(): array
    {
        return self::MONARC_SELECTABLE_MODELS;
    }

    /** @return array<string, array<int, string>> View key => family short names. */
    public static function monarcFamilyViews(): array
    {
        return self::MONARC_FAMILY_VIEWS;
    }

    /** @return array<int, string> */
    public static function monarcPrimaryFamilies(): array
    {
        return self::MONARC_PRIMARY_FAMILIES;
    }

    /** @return array<int, string> */
    public static function monarcSidebarOrder(): array
    {
        return self::MONARC_SIDEBAR_FAMILY_ORDER;
    }

    /** @return array<class-string, string> Model class => report `vues[]` id. */
    public static function reportVueMap(): array
    {
        return self::REPORT_VUE_MAP;
    }

    // ─── Dynamic model discovery ─────────────────────────────────────────────

    /**
     * Every concrete model class under app/Models, excluding abstract classes and EXCLUDED_MODELS.
     * Ex-QueryEngineIntrospector::listModelClasses(), moved here so both the query engine and any
     * future consumer share one discovery routine.
     *
     * @return array<int, class-string>
     */
    public static function allConcreteModels(): array
    {
        $path = base_path('app/Models');
        $classes = [];

        foreach (glob("{$path}/*.php") as $file) {
            $modelName = basename($file, '.php');
            $class = self::MODEL_NAMESPACE.$modelName;

            if (! class_exists($class)) {
                continue;
            }
            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }
            if (in_array($modelName, self::EXCLUDED_MODELS, true)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
