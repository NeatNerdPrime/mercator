<?php

namespace App\View\Components;

use App\Models\Cartographer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

class ShowLink extends Component
{
    public string  $label;
    public ?string $url;

    private static array $routes = [
        \App\Models\Activity::class                => 'admin.activities.show',
        \App\Models\Actor::class                   => 'admin.actors.show',
        \App\Models\AdminUser::class               => 'admin.admin-users.show',
        \App\Models\Annuaire::class                => 'admin.annuaires.show',
        \App\Models\Application::class             => 'admin.applications.show',
        \App\Models\ApplicationBlock::class        => 'admin.application-blocks.show',
        \App\Models\ApplicationFlow::class         => 'admin.application-flows.show',
        \App\Models\ApplicationModule::class       => 'admin.application-modules.show',
        \App\Models\ApplicationService::class      => 'admin.application-services.show',
        \App\Models\AuditLog::class                => 'admin.audit-logs.show',
        \App\Models\Backup::class                  => 'admin.backups.show',
        \App\Models\Bay::class                     => 'admin.bays.show',
        \App\Models\Building::class                => 'admin.buildings.show',
        \App\Models\Certificate::class             => 'admin.certificates.show',
        \App\Models\Cluster::class                 => 'admin.clusters.show',
        \App\Models\Container::class               => 'admin.containers.show',
        \App\Models\Database::class                => 'admin.databases.show',
        \App\Models\DataProcessing::class          => 'admin.data-processings.show',
        \App\Models\DhcpServer::class              => 'admin.dhcp-servers.show',
        \App\Models\Dnsserver::class               => 'admin.dnsservers.show',
        \App\Models\Domain::class                  => 'admin.domains.show',
        \App\Models\Entity::class                  => 'admin.entities.show',
        \App\Models\ExternalConnectedEntity::class => 'admin.external-connected-entities.show',
        \App\Models\ForestAd::class                => 'admin.forest-ads.show',
        \App\Models\Gateway::class                 => 'admin.gateways.show',
        \App\Models\Graph::class                   => 'admin.graphs.show',
        \App\Models\Information::class             => 'admin.information.show',
        \App\Models\Lan::class                     => 'admin.lans.show',
        \App\Models\LogicalFlow::class             => 'admin.logical-flows.show',
        \App\Models\LogicalServer::class           => 'admin.logical-servers.show',
        \App\Models\MacroProcessus::class          => 'admin.macro-processuses.show',
        \App\Models\Man::class                     => 'admin.mans.show',
        \App\Models\Network::class                 => 'admin.networks.show',
        \App\Models\NetworkSwitch::class           => 'admin.network-switches.show',
        \App\Models\Operation::class               => 'admin.operations.show',
        \App\Models\Peripheral::class              => 'admin.peripherals.show',
        \App\Models\Phone::class                   => 'admin.phones.show',
        \App\Models\PhysicalLink::class            => 'admin.physical-links.show',
        \App\Models\PhysicalRouter::class          => 'admin.physical-routers.show',
        \App\Models\PhysicalSecurityDevice::class  => 'admin.physical-security-devices.show',
        \App\Models\PhysicalServer::class          => 'admin.physical-servers.show',
        \App\Models\PhysicalSwitch::class          => 'admin.physical-switches.show',
        \App\Models\Process::class                 => 'admin.processes.show',
        \App\Models\Relation::class                => 'admin.relations.show',
        \App\Models\Role::class                    => 'admin.roles.show',
        \App\Models\Router::class                  => 'admin.routers.show',
        \App\Models\SecurityControl::class         => 'admin.security-controls.show',
        \App\Models\SecurityDevice::class          => 'admin.security-devices.show',
        \App\Models\Site::class                    => 'admin.sites.show',
        \App\Models\StorageDevice::class           => 'admin.storage-devices.show',
        \App\Models\Subnetwork::class              => 'admin.subnetworks.show',
        \App\Models\Task::class                    => 'admin.tasks.show',
        \App\Models\User::class                    => 'admin.users.show',
        \App\Models\Vlan::class                    => 'admin.vlans.show',
        \App\Models\Wan::class                     => 'admin.wans.show',
        \App\Models\WifiTerminal::class            => 'admin.wifi-terminals.show',
        \App\Models\Workstation::class             => 'admin.workstations.show',
        \App\Models\Zone::class                    => 'admin.zones.show',
        \App\Models\ZoneAdmin::class               => 'admin.zone-admins.show',
    ];

    public function __construct(Model $model, ?string $label = null)
    {
        $this->label = $label ?? ($model->name ?? '');

        $routeName = self::$routes[get_class($model)] ?? null;

        $this->url = ($routeName && Gate::allows('show-object', $model))
            ? route($routeName, $model->getKey())
            : null;
    }

    public function render(): \Illuminate\View\View
    {
        return view('components.show-link');
    }
}
