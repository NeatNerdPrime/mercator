<?php

namespace App\Services\Report;

use App\Contracts\HasUniqueIdentifierContract;
use App\Models\Backup;
use App\Models\Cartographer;
use App\Models\Certificate;
use App\Models\Cluster;
use App\Models\Container;
use App\Models\DhcpServer;
use App\Models\Dnsserver;
use App\Models\ExternalConnectedEntity;
use App\Models\Gateway;
use App\Models\LogicalFlow;
use App\Models\LogicalServer;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Peripheral;
use App\Models\Phone;
use App\Models\PhysicalSecurityDevice;
use App\Models\Router;
use App\Models\SecurityDevice;
use App\Models\StorageDevice;
use App\Models\Subnetwork;
use App\Models\Vlan;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use App\Services\Graph\LogicalInfrastructureGraphBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;

class LogicalInfrastructureSection implements ReportSection
{
    public function build(Section $section, WordHelper $helper, array $selectedVues): void
    {
        $section->addTitle(trans('cruds.menu.logical_infrastructure.title'), 1);

        $networks = Cartographer::scopedQuery(Network::query())->with('subnetworks')->get()
            ->sortBy(fn (Network $item) => mb_strtolower((string) $item->name));
        $subnetworks = Cartographer::scopedQuery(Subnetwork::query())->with(['network', 'subnetwork', 'gateway', 'vlan'])->get()
            ->sortBy(fn (Subnetwork $item) => mb_strtolower((string) $item->name));
        $gateways = Cartographer::scopedQuery(Gateway::query())->get()
            ->sortBy(fn (Gateway $item) => mb_strtolower((string) $item->name));
        $externalConnectedEntities = Cartographer::scopedQuery(ExternalConnectedEntity::query())->with(['entity', 'network', 'subnetworks', 'documents'])->get()
            ->sortBy(fn (ExternalConnectedEntity $item) => mb_strtolower((string) $item->name));
        $routers = Cartographer::scopedQuery(Router::query())->with('physicalRouters')->get()
            ->sortBy(fn (Router $item) => mb_strtolower((string) $item->name));
        $networkSwitches = Cartographer::scopedQuery(NetworkSwitch::query())->with('physicalSwitches')->get()
            ->sortBy(fn (NetworkSwitch $item) => mb_strtolower((string) $item->name));
        $securityDevices = Cartographer::scopedQuery(SecurityDevice::query())->with(['applications', 'physicalSecurityDevices'])->get()
            ->sortBy(fn (SecurityDevice $item) => mb_strtolower((string) $item->name));
        $dhcpServers = Cartographer::scopedQuery(DhcpServer::query())->get()
            ->sortBy(fn (DhcpServer $item) => mb_strtolower((string) $item->name));
        $dnsservers = Cartographer::scopedQuery(Dnsserver::query())->get()
            ->sortBy(fn (Dnsserver $item) => mb_strtolower((string) $item->name));
        $logicalServers = Cartographer::scopedQuery(LogicalServer::query())
            ->with(['clusters', 'applications', 'databases', 'domain', 'physicalServers', 'backups.storageDevices'])
            ->get()
            ->sortBy(fn (LogicalServer $item) => mb_strtolower((string) $item->name));
        $clusters = Cartographer::scopedQuery(Cluster::query())->with(['logicalServers', 'routers', 'physicalServers'])->get()
            ->sortBy(fn (Cluster $item) => mb_strtolower((string) $item->name));
        $backups = Cartographer::scopedQuery(Backup::query())->with(['logicalServers', 'storageDevices'])->get()
            ->sortBy(fn (Backup $item) => mb_strtolower((string) $item->name));
        $containers = Cartographer::scopedQuery(Container::query())->with(['logicalServers', 'applications', 'databases'])->get()
            ->sortBy(fn (Container $item) => mb_strtolower((string) $item->name));
        $logicalFlows = Cartographer::scopedQuery(LogicalFlow::query())
            ->with([
                'logicalServerSource', 'peripheralSource', 'physicalServerSource', 'storageDeviceSource', 'workstationSource',
                'physicalSecurityDeviceSource', 'securityDeviceSource', 'subnetworkSource', 'clusterSource',
                'logicalServerDest', 'peripheralDest', 'physicalServerDest', 'storageDeviceDest', 'workstationDest',
                'physicalSecurityDeviceDest', 'securityDeviceDest', 'subnetworkDest', 'clusterDest',
                'router',
            ])
            ->get()
            ->sortBy(fn (LogicalFlow $item) => mb_strtolower((string) ($item->name ?? '')));
        $vlans = Cartographer::scopedQuery(Vlan::query())->with(['subnetworks', 'networkSwitches'])->get()
            ->sortBy(fn (Vlan $item) => mb_strtolower((string) $item->name));
        $certificates = Cartographer::scopedQuery(Certificate::query())->with(['logicalServers', 'applications'])->get()
            ->sortBy(fn (Certificate $item) => mb_strtolower((string) $item->name));

        // Vue 6 "context" objects: rendered as graph nodes only, their own content lives in the Physical Infrastructure section.
        $workstations = Cartographer::scopedQuery(Workstation::query())->get();
        $wifiTerminals = Cartographer::scopedQuery(WifiTerminal::query())->get();
        $phones = Cartographer::scopedQuery(Phone::query())->get();
        $peripherals = Cartographer::scopedQuery(Peripheral::query())->get();
        $physicalSecurityDevices = Cartographer::scopedQuery(PhysicalSecurityDevice::query())->get();
        $storageDevices = Cartographer::scopedQuery(StorageDevice::query())->get();

        // "un graphe par réseau ; si un seul réseau, un par sous-réseau à la place" — with only one
        // Network, a single network-wide graph would just repeat the vue's global content, so the
        // split moves one level down to each Subnetwork instead.
        $splitBySubnetwork = $networks->count() === 1;

        $graphContext = new LogicalInfrastructureGraphContext(
            $subnetworks, $gateways, $externalConnectedEntities, $vlans, $networkSwitches, $clusters,
            $logicalServers, $dhcpServers, $dnsservers, $certificates, $containers, $routers, $securityDevices,
            $workstations, $wifiTerminals, $phones, $peripherals, $physicalSecurityDevices, $storageDevices,
        );

        $this->addNetworks($section, $helper, $networks, $graphContext, $splitBySubnetwork, $selectedVues);
        $this->addSubnetworks($section, $helper, $subnetworks, $graphContext, $splitBySubnetwork, $selectedVues);
        $this->addGateways($section, $helper, $gateways);
        $this->addExternalConnectedEntities($section, $helper, $externalConnectedEntities, $selectedVues);
        $this->addRouters($section, $helper, $routers, $selectedVues);
        $this->addNetworkSwitches($section, $helper, $networkSwitches, $selectedVues);
        $this->addSecurityDevices($section, $helper, $securityDevices, $selectedVues);
        $this->addDhcpServers($section, $helper, $dhcpServers);
        $this->addDnsservers($section, $helper, $dnsservers);
        $this->addLogicalServers($section, $helper, $logicalServers, $selectedVues);
        $this->addClusters($section, $helper, $clusters, $selectedVues);
        $this->addBackups($section, $helper, $backups, $selectedVues);
        $this->addContainers($section, $helper, $containers, $selectedVues);
        $this->addLogicalFlows($section, $helper, $logicalFlows, $selectedVues);
        $this->addVlans($section, $helper, $vlans, $selectedVues);
        $this->addCertificates($section, $helper, $certificates, $selectedVues);
    }

    /**
     * @param  Collection<int, Network>  $networks
     * @param  array<int, string>  $selectedVues
     */
    private function addNetworks(Section $section, WordHelper $helper, Collection $networks, LogicalInfrastructureGraphContext $graphContext, bool $splitBySubnetwork, array $selectedVues): void
    {
        if ($networks->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.network.title'), 2);

        $graphBuilder = new LogicalInfrastructureGraphBuilder;
        $iconResolver = fn (?int $iconId, string $fallback) => $helper->resolveIconPath($iconId, $fallback);

        foreach ($networks as $network) {
            $helper->addBookmarkedTitle($section, $network->getUID(), (string) $network->name, 3);

            if (! $splitBySubnetwork) {
                $this->addFilteredNetworkGraph($section, $helper, $graphBuilder, $iconResolver, new Collection([$network]), $network->subnetworks, $graphContext);
            }

            $table = $helper->addTable($section, (string) $network->name);

            $helper->addTextRow($table, trans('cruds.network.fields.type'), $network->type);
            $helper->addTextRow($table, trans('cruds.network.fields.attributes'), $this->formatAttributes($network->attributes));
            $helper->addHTMLRow($table, trans('cruds.network.fields.description'), $network->description);
            $helper->addTextRow($table, trans('cruds.network.fields.protocol_type'), $network->protocol_type);
            $helper->addTextRow($table, trans('cruds.network.fields.responsible'), $network->responsible);
            $helper->addTextRow($table, trans('cruds.network.fields.responsible_sec'), $network->responsible_sec);
            $helper->addSecurityNeedRow(
                $table,
                trans('cruds.information.fields.security_need'),
                $network->security_need_c,
                $network->security_need_i,
                $network->security_need_a,
                $network->security_need_t,
                $network->security_need_auth
            );

            if ($network->subnetworks->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.network.fields.subnetworks'), $network->subnetworks, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, Subnetwork>  $subnetworks
     * @param  array<int, string>  $selectedVues
     */
    private function addSubnetworks(Section $section, WordHelper $helper, Collection $subnetworks, LogicalInfrastructureGraphContext $graphContext, bool $splitBySubnetwork, array $selectedVues): void
    {
        if ($subnetworks->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.subnetwork.title'), 2);

        $graphBuilder = new LogicalInfrastructureGraphBuilder;
        $iconResolver = fn (?int $iconId, string $fallback) => $helper->resolveIconPath($iconId, $fallback);

        foreach ($subnetworks as $subnetwork) {
            $helper->addBookmarkedTitle($section, $subnetwork->getUID(), (string) $subnetwork->name, 3);

            if ($splitBySubnetwork) {
                $this->addFilteredNetworkGraph($section, $helper, $graphBuilder, $iconResolver, new Collection, new Collection([$subnetwork]), $graphContext);
            }

            $table = $helper->addTable($section, (string) $subnetwork->name);

            $helper->addTextRow($table, trans('cruds.subnetwork.fields.type'), $subnetwork->type);
            $helper->addTextRow($table, trans('cruds.subnetwork.fields.attributes'), $this->formatAttributes($subnetwork->attributes));
            $helper->addHTMLRow($table, trans('cruds.subnetwork.fields.description'), $subnetwork->description);

            if ($subnetwork->network !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.subnetwork.fields.network'));
                $helper->linkOrText($run, $subnetwork->network, $selectedVues);
            }

            if ($subnetwork->subnetwork !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.subnetwork.fields.subnetwork'));
                $helper->linkOrText($run, $subnetwork->subnetwork, $selectedVues);
            }

            $addressLabel = $subnetwork->address;
            if ($addressLabel !== null) {
                $addressLabel .= ' ( '.$subnetwork->ipRange().' )';
            }
            $helper->addTextRow($table, trans('cruds.subnetwork.fields.address'), $addressLabel);

            if ($subnetwork->gateway !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.subnetwork.fields.gateway'));
                $helper->linkOrText($run, $subnetwork->gateway, $selectedVues);
            }

            if ($subnetwork->vlan !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.subnetwork.fields.vlan'));
                $helper->linkOrText($run, $subnetwork->vlan, $selectedVues);
            }

            $helper->addTextRow($table, trans('cruds.subnetwork.fields.ip_allocation_type'), $subnetwork->ip_allocation_type);
            $helper->addTextRow($table, trans('cruds.subnetwork.fields.zone'), $subnetwork->zone);
            $helper->addTextRow($table, trans('cruds.subnetwork.fields.dmz'), $subnetwork->dmz);
            $helper->addTextRow($table, trans('cruds.subnetwork.fields.wifi'), $subnetwork->wifi);
            $helper->addTextRow($table, trans('cruds.subnetwork.fields.responsible_exp'), $subnetwork->responsible_exp);
        }
    }

    /**
     * Filters the whole vue's device collections down to whatever is reachable from $ownSubnetworks
     * (direct FK for Gateway/Vlan/ExternalConnectedEntity, IP/CIDR-membership via
     * Subnetwork::contains() for everything else, mirroring the matching LogicalInfrastructureGraphBuilder
     * itself uses), then draws a single subgraph. Used both for the per-Network graph (one Network's
     * own subnetworks) and the per-Subnetwork fallback (a single subnetwork) when there's only one
     * Network overall. Skipped when there are no subnetworks to draw.
     *
     * @param  Collection<int, Network>  $networksForGraph
     * @param  Collection<int, Subnetwork>  $ownSubnetworks
     * @param  callable(?int, string): string  $iconResolver
     */
    private function addFilteredNetworkGraph(
        Section $section,
        WordHelper $helper,
        LogicalInfrastructureGraphBuilder $graphBuilder,
        callable $iconResolver,
        Collection $networksForGraph,
        Collection $ownSubnetworks,
        LogicalInfrastructureGraphContext $ctx
    ): void {
        if ($ownSubnetworks->isEmpty()) {
            return;
        }

        $subnetworkIds = $ownSubnetworks->pluck('id');

        $gatewayIds = $ownSubnetworks->pluck('gateway_id')->filter()->unique();
        $ownGateways = $ctx->gateways->whereIn('id', $gatewayIds);

        $vlanIds = $ownSubnetworks->pluck('vlan_id')->filter()->unique();
        $ownVlans = $ctx->vlans->whereIn('id', $vlanIds);

        $ownExternalConnectedEntities = $ctx->externalConnectedEntities->filter(
            fn (ExternalConnectedEntity $entity) => $entity->subnetworks->pluck('id')->intersect($subnetworkIds)->isNotEmpty()
                || ($entity->network_id !== null && $networksForGraph->contains('id', $entity->network_id))
        );

        $ownDhcpServers = $ctx->dhcpServers->filter(fn (DhcpServer $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownDnsservers = $ctx->dnsservers->filter(fn (Dnsserver $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownRouters = $ctx->routers->filter(fn (Router $device) => $this->ipMatchesSubnetworks($device->ip_addresses, $ownSubnetworks));
        $ownNetworkSwitches = $ctx->networkSwitches->filter(fn (NetworkSwitch $device) => $this->ipMatchesSubnetworks($device->ip, $ownSubnetworks));
        $ownSecurityDevices = $ctx->securityDevices->filter(fn (SecurityDevice $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownLogicalServers = $ctx->logicalServers->filter(fn (LogicalServer $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownClusters = $ctx->clusters->filter(fn (Cluster $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownWorkstations = $ctx->workstations->filter(fn (Workstation $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownWifiTerminals = $ctx->wifiTerminals->filter(fn (WifiTerminal $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownPhones = $ctx->phones->filter(fn (Phone $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownPeripherals = $ctx->peripherals->filter(fn (Peripheral $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownPhysicalSecurityDevices = $ctx->physicalSecurityDevices->filter(fn (PhysicalSecurityDevice $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));
        $ownStorageDevices = $ctx->storageDevices->filter(fn (StorageDevice $device) => $this->ipMatchesSubnetworks($device->address_ip, $ownSubnetworks));

        $ownLogicalServerIds = $ownLogicalServers->pluck('id');
        $ownContainers = $ctx->containers->filter(
            fn (Container $container) => $container->logicalServers->pluck('id')->intersect($ownLogicalServerIds)->isNotEmpty()
        );
        $ownCertificates = $ctx->certificates->filter(
            fn (Certificate $certificate) => $certificate->logicalServers->pluck('id')->intersect($ownLogicalServerIds)->isNotEmpty()
        );

        $dot = $graphBuilder->buildDot(
            $networksForGraph, $ownSubnetworks, $ownGateways, $ownExternalConnectedEntities, $ownVlans, $ownNetworkSwitches, $ownClusters,
            $ownLogicalServers, $ownDhcpServers, $ownDnsservers, $ownCertificates, $ownContainers, $ownRouters, $ownSecurityDevices,
            $ownWorkstations, $ownWifiTerminals, $ownPhones, $ownPeripherals, $ownPhysicalSecurityDevices, $ownStorageDevices,
            false,
            ['withHref' => false, 'iconResolver' => $iconResolver]
        );
        $helper->insertGraph($section, $dot);
    }

    /**
     * @param  Collection<int, Subnetwork>  $subnetworks
     */
    private function ipMatchesSubnetworks(?string $rawIps, Collection $subnetworks): bool
    {
        if ($rawIps === null) {
            return false;
        }

        foreach (explode(',', $rawIps) as $ip) {
            foreach ($subnetworks as $subnetwork) {
                if ($subnetwork->contains(trim($ip))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Gateway>  $gateways
     */
    private function addGateways(Section $section, WordHelper $helper, Collection $gateways): void
    {
        if ($gateways->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.gateway.title'), 2);

        foreach ($gateways as $gateway) {
            $helper->addBookmarkedTitle($section, $gateway->getUID(), (string) $gateway->name, 3);
            $table = $helper->addTable($section, (string) $gateway->name);

            $helper->addHTMLRow($table, trans('cruds.gateway.fields.description'), $gateway->description);
            $helper->addTextRow($table, trans('cruds.gateway.fields.authentification'), $gateway->authentification);
            $helper->addTextRow($table, trans('cruds.gateway.fields.ip'), $gateway->ip);
        }
    }

    /**
     * @param  Collection<int, ExternalConnectedEntity>  $externalConnectedEntities
     * @param  array<int, string>  $selectedVues
     */
    private function addExternalConnectedEntities(Section $section, WordHelper $helper, Collection $externalConnectedEntities, array $selectedVues): void
    {
        if ($externalConnectedEntities->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.externalConnectedEntity.title'), 2);

        foreach ($externalConnectedEntities as $entity) {
            $helper->addBookmarkedTitle($section, $entity->getUID(), (string) $entity->name, 3);
            $table = $helper->addTable($section, (string) $entity->name);

            $helper->addTextRow($table, trans('cruds.externalConnectedEntity.fields.type'), $entity->type);
            $helper->addHTMLRow($table, trans('cruds.externalConnectedEntity.fields.description'), $entity->description);

            if ($entity->entity !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.externalConnectedEntity.fields.entity'));
                $helper->linkOrText($run, $entity->entity, $selectedVues);
            }

            if ($entity->network !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.externalConnectedEntity.fields.network'));
                $helper->linkOrText($run, $entity->network, $selectedVues);
            }

            $helper->addTextRow($table, trans('cruds.externalConnectedEntity.fields.contacts'), $entity->contacts);

            if ($entity->subnetworks->isNotEmpty()) {
                $run = $helper->addTextRunRow($table, trans('cruds.externalConnectedEntity.fields.subnetworks'));
                $count = $entity->subnetworks->count();
                $i = 0;
                foreach ($entity->subnetworks as $subnetwork) {
                    $label = (string) $subnetwork->name.($subnetwork->address !== null ? ' ('.$subnetwork->address.')' : '');
                    $helper->linkOrText($run, $subnetwork, $selectedVues, $label);
                    if (++$i < $count) {
                        $run->addText(', ');
                    }
                }
            }

            $helper->addTextRow($table, trans('cruds.externalConnectedEntity.fields.src_desc'), $entity->src_desc);
            $helper->addTextRow($table, trans('cruds.externalConnectedEntity.fields.dest_desc'), $entity->dest_desc);
            $helper->addTextRow($table, trans('cruds.externalConnectedEntity.fields.src'), $entity->src);
            $helper->addTextRow($table, trans('cruds.externalConnectedEntity.fields.dest'), $entity->dest);
            $helper->addHTMLRow($table, trans('cruds.externalConnectedEntity.fields.security'), $entity->security);

            if ($entity->documents->isNotEmpty()) {
                $helper->addDocumentLinksRow($table, trans('cruds.externalConnectedEntity.fields.documents'), $entity->documents);
            }
        }
    }

    /**
     * @param  Collection<int, Router>  $routers
     * @param  array<int, string>  $selectedVues
     */
    private function addRouters(Section $section, WordHelper $helper, Collection $routers, array $selectedVues): void
    {
        if ($routers->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.router.title'), 2);

        foreach ($routers as $router) {
            $helper->addBookmarkedTitle($section, $router->getUID(), (string) $router->name, 3);
            $table = $helper->addTable($section, (string) $router->name);

            $helper->addTextRow($table, trans('cruds.router.fields.type'), $router->type);
            $helper->addHTMLRow($table, trans('cruds.router.fields.description'), $router->description);
            $helper->addHTMLRow($table, trans('cruds.router.fields.rules'), $router->rules);

            if ($router->physicalRouters->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.router.fields.physical_routers'), $router->physicalRouters, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, NetworkSwitch>  $networkSwitches
     * @param  array<int, string>  $selectedVues
     */
    private function addNetworkSwitches(Section $section, WordHelper $helper, Collection $networkSwitches, array $selectedVues): void
    {
        if ($networkSwitches->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.networkSwitch.title'), 2);

        foreach ($networkSwitches as $networkSwitch) {
            $helper->addBookmarkedTitle($section, $networkSwitch->getUID(), (string) $networkSwitch->name, 3);
            $table = $helper->addTable($section, (string) $networkSwitch->name);

            $helper->addHTMLRow($table, trans('cruds.networkSwitch.fields.description'), $networkSwitch->description);
            $helper->addTextRow($table, trans('cruds.networkSwitch.fields.ip'), $networkSwitch->ip);

            if ($networkSwitch->physicalSwitches->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.networkSwitch.fields.physical_switches'), $networkSwitch->physicalSwitches, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, SecurityDevice>  $securityDevices
     * @param  array<int, string>  $selectedVues
     */
    private function addSecurityDevices(Section $section, WordHelper $helper, Collection $securityDevices, array $selectedVues): void
    {
        if ($securityDevices->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.securityDevice.title'), 2);

        foreach ($securityDevices as $securityDevice) {
            $helper->addBookmarkedTitle($section, $securityDevice->getUID(), (string) $securityDevice->name, 3);
            $table = $helper->addTable($section, (string) $securityDevice->name);

            $helper->addTextRow($table, trans('cruds.securityDevice.fields.type'), $securityDevice->type);
            $helper->addTextRow($table, trans('cruds.securityDevice.fields.attributes'), $this->formatAttributes($securityDevice->attributes));
            $helper->addHTMLRow($table, trans('cruds.securityDevice.fields.description'), $securityDevice->description);
            $helper->addImageRow($table, '', $helper->resolveIconPath($securityDevice->icon_id, '/images/securitydevice.png'));
            $helper->addTextRow($table, trans('cruds.securityDevice.fields.address_ip'), $securityDevice->address_ip);

            if ($securityDevice->applications->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.securityDevice.fields.applications'), $securityDevice->applications, $selectedVues);
            }

            if ($securityDevice->physicalSecurityDevices->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.securityDevice.fields.physical_security_devices'), $securityDevice->physicalSecurityDevices, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, DhcpServer>  $dhcpServers
     */
    private function addDhcpServers(Section $section, WordHelper $helper, Collection $dhcpServers): void
    {
        if ($dhcpServers->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.dhcpServer.title'), 2);

        foreach ($dhcpServers as $dhcpServer) {
            $helper->addBookmarkedTitle($section, $dhcpServer->getUID(), (string) $dhcpServer->name, 3);
            $table = $helper->addTable($section, (string) $dhcpServer->name);

            $helper->addHTMLRow($table, trans('cruds.dhcpServer.fields.description'), $dhcpServer->description);
            $helper->addTextRow($table, trans('cruds.dhcpServer.fields.address_ip'), $dhcpServer->address_ip);
        }
    }

    /**
     * @param  Collection<int, Dnsserver>  $dnsservers
     */
    private function addDnsservers(Section $section, WordHelper $helper, Collection $dnsservers): void
    {
        if ($dnsservers->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.dnsserver.title'), 2);

        foreach ($dnsservers as $dnsserver) {
            $helper->addBookmarkedTitle($section, $dnsserver->getUID(), (string) $dnsserver->name, 3);
            $table = $helper->addTable($section, (string) $dnsserver->name);

            $helper->addHTMLRow($table, trans('cruds.dnsserver.fields.description'), $dnsserver->description);
            $helper->addTextRow($table, trans('cruds.dnsserver.fields.address_ip'), $dnsserver->address_ip);
        }
    }

    /**
     * @param  Collection<int, LogicalServer>  $logicalServers
     * @param  array<int, string>  $selectedVues
     */
    private function addLogicalServers(Section $section, WordHelper $helper, Collection $logicalServers, array $selectedVues): void
    {
        if ($logicalServers->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.logicalServer.title'), 2);

        foreach ($logicalServers as $logicalServer) {
            $helper->addBookmarkedTitle($section, $logicalServer->getUID(), (string) $logicalServer->name, 3);
            $table = $helper->addTable($section, (string) $logicalServer->name);

            $helper->addTextRow($table, trans('cruds.logicalServer.fields.type'), $logicalServer->type);
            $helper->addTextRow($table, trans('cruds.logicalServer.fields.attributes'), $this->formatAttributes($logicalServer->attributes));
            $helper->addHTMLRow($table, trans('cruds.logicalServer.fields.description'), $logicalServer->description);
            $helper->addImageRow($table, '', $helper->resolveIconPath($logicalServer->icon_id, '/images/lserver.png'));
            $helper->addTextRow($table, trans('cruds.logicalServer.fields.operating_system'), $logicalServer->operating_system);

            if ($logicalServer->install_date !== null) {
                $helper->addTextRow($table, trans('cruds.logicalServer.fields.install_date'), (string) $logicalServer->install_date);
            }

            if ($logicalServer->update_date !== null) {
                $helper->addTextRow($table, trans('cruds.logicalServer.fields.update_date'), (string) $logicalServer->update_date);
            }

            if ($logicalServer->clusters->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.logicalServer.fields.clusters'), $logicalServer->clusters, $selectedVues);
            }

            $helper->addTextRow($table, trans('cruds.logicalServer.fields.environment'), $logicalServer->environment);
            $helper->addTextRow($table, trans('cruds.logicalServer.fields.address_ip'), $logicalServer->address_ip);
            $helper->addTextRow($table, trans('cruds.logicalServer.fields.net_services'), $logicalServer->net_services);
            $helper->addTextRow($table, trans('cruds.logicalServer.fields.cpu'), $logicalServer->cpu);
            $helper->addTextRow($table, trans('cruds.logicalServer.fields.memory'), $logicalServer->memory);

            $diskUsedPercent = ($logicalServer->disk !== null && $logicalServer->disk > 0 && $logicalServer->disk_used !== null)
                ? number_format(100 * $logicalServer->disk_used / $logicalServer->disk, 2).' %'
                : 'N/A';
            $helper->addTextRow(
                $table,
                trans('cruds.logicalServer.fields.disk_used').' / '.trans('cruds.logicalServer.fields.disk'),
                $logicalServer->disk_used.' / '.$logicalServer->disk.' ( '.$diskUsedPercent.' )'
            );

            $helper->addHTMLRow($table, trans('cruds.logicalServer.fields.configuration'), $logicalServer->configuration);

            if ($logicalServer->applications->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.logicalServer.fields.applications'), $logicalServer->applications, $selectedVues);
            }

            if ($logicalServer->databases->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.logicalServer.fields.databases'), $logicalServer->databases, $selectedVues);
            }

            if ($logicalServer->domain !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.logicalServer.fields.domain'));
                $helper->linkOrText($run, $logicalServer->domain, $selectedVues);
            }

            if ($logicalServer->physicalServers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.logicalServer.fields.servers'), $logicalServer->physicalServers, $selectedVues);
            }

            if (Gate::allows('backup_show') && $logicalServer->backups->isNotEmpty()) {
                $helper->addNestedTableRow(
                    $table,
                    trans('cruds.backup.title'),
                    [
                        trans('cruds.backup.fields.name'),
                        trans('cruds.storageDevice.title_singular'),
                        trans('cruds.backup.frequency'),
                        trans('cruds.backup.cycle'),
                        trans('cruds.backup.retention'),
                    ],
                    $logicalServer->backups->map(fn (Backup $backup) => [
                        (string) $backup->name,
                        $backup->storageDevices->pluck('name')->implode(', '),
                        $backup->backup_frequency !== null ? trans('cruds.backup.frequencies.'.$backup->backup_frequency) : '',
                        $backup->backup_cycle !== null ? trans('cruds.backup.cycles.'.$backup->backup_cycle) : '',
                        $backup->backup_retention !== null ? $backup->backup_retention.' '.trans('cruds.backup.retention_unit') : '',
                    ])
                );
            }
        }
    }

    /**
     * @param  Collection<int, Cluster>  $clusters
     * @param  array<int, string>  $selectedVues
     */
    private function addClusters(Section $section, WordHelper $helper, Collection $clusters, array $selectedVues): void
    {
        if ($clusters->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.cluster.title'), 2);

        foreach ($clusters as $cluster) {
            $helper->addBookmarkedTitle($section, $cluster->getUID(), (string) $cluster->name, 3);
            $table = $helper->addTable($section, (string) $cluster->name);

            $helper->addTextRow($table, trans('cruds.cluster.fields.type'), $cluster->type);
            $helper->addTextRow($table, trans('cruds.cluster.fields.attributes'), $this->formatAttributes($cluster->attributes));
            $helper->addHTMLRow($table, trans('cruds.cluster.fields.description'), $cluster->description);
            $helper->addImageRow($table, '', $helper->resolveIconPath($cluster->icon_id, '/images/cluster.png'));
            $helper->addTextRow($table, trans('cruds.cluster.fields.address_ip'), $cluster->address_ip);

            if ($cluster->logicalServers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.cluster.fields.logical_servers'), $cluster->logicalServers, $selectedVues);
            }

            if ($cluster->routers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.router.title'), $cluster->routers, $selectedVues);
            }

            if ($cluster->physicalServers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.cluster.fields.physical_servers'), $cluster->physicalServers, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, Backup>  $backups
     * @param  array<int, string>  $selectedVues
     */
    private function addBackups(Section $section, WordHelper $helper, Collection $backups, array $selectedVues): void
    {
        if ($backups->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.backup.title'), 2);

        foreach ($backups as $backup) {
            $helper->addBookmarkedTitle($section, $backup->getUID(), (string) $backup->name, 3);
            $table = $helper->addTable($section, (string) $backup->name);

            $helper->addTextRow($table, trans('cruds.backup.fields.type'), $backup->type);
            $helper->addTextRow($table, trans('cruds.backup.fields.attributes'), $this->formatAttributes($backup->attributes));
            $helper->addHTMLRow($table, trans('cruds.backup.fields.description'), $backup->description);
            $helper->addTextRow($table, trans('cruds.backup.frequency'), $backup->backup_frequency !== null ? trans('cruds.backup.frequencies.'.$backup->backup_frequency) : null);
            $helper->addTextRow($table, trans('cruds.backup.cycle'), $backup->backup_cycle !== null ? trans('cruds.backup.cycles.'.$backup->backup_cycle) : null);
            $helper->addTextRow($table, trans('cruds.backup.retention'), $backup->backup_retention !== null ? $backup->backup_retention.' '.trans('cruds.backup.retention_unit') : null);

            if ($backup->logicalServers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.backup.fields.logical_servers'), $backup->logicalServers, $selectedVues);
            }

            if ($backup->storageDevices->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.backup.fields.storage_devices'), $backup->storageDevices, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, Container>  $containers
     * @param  array<int, string>  $selectedVues
     */
    private function addContainers(Section $section, WordHelper $helper, Collection $containers, array $selectedVues): void
    {
        if ($containers->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.container.title'), 2);

        foreach ($containers as $container) {
            $helper->addBookmarkedTitle($section, $container->getUID(), (string) $container->name, 3);
            $table = $helper->addTable($section, (string) $container->name);

            $helper->addTextRow($table, trans('cruds.container.fields.type'), $container->type);
            $helper->addHTMLRow($table, trans('cruds.container.fields.description'), $container->description);
            $helper->addImageRow($table, '', $helper->resolveIconPath($container->icon_id, '/images/container.png'));

            if ($container->logicalServers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.container.fields.logical_servers'), $container->logicalServers, $selectedVues);
            }

            if ($container->applications->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.container.fields.applications'), $container->applications, $selectedVues);
            }

            if ($container->databases->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.container.fields.databases'), $container->databases, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, LogicalFlow>  $logicalFlows
     * @param  array<int, string>  $selectedVues
     */
    private function addLogicalFlows(Section $section, WordHelper $helper, Collection $logicalFlows, array $selectedVues): void
    {
        if ($logicalFlows->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.logicalFlow.title'), 2);

        foreach ($logicalFlows as $logicalFlow) {
            $helper->addBookmarkedTitle($section, $logicalFlow->getUID(), (string) ($logicalFlow->name ?? 'NONAME'), 3);
            $table = $helper->addTable($section, (string) ($logicalFlow->name ?? 'NONAME'));

            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.type'), $logicalFlow->type);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.attributes'), $this->formatAttributes($logicalFlow->attributes));
            $helper->addHTMLRow($table, trans('cruds.logicalFlow.fields.description'), $logicalFlow->description);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.chain'), $logicalFlow->chain);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.interface'), $logicalFlow->interface);

            if ($logicalFlow->router !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.logicalFlow.fields.router'));
                $helper->linkOrText($run, $logicalFlow->router, $selectedVues);
            }

            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.priority'), $logicalFlow->priority);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.action'), $logicalFlow->action);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.protocol'), $logicalFlow->protocol);

            $sourceRun = $helper->addTextRunRow($table, trans('cruds.logicalFlow.fields.source_ip_range'));
            $this->addFlowEndpointRun($sourceRun, $helper, $logicalFlow->source_ip_range, [
                [$logicalFlow->logicalServerSource, 'address_ip'],
                [$logicalFlow->peripheralSource, 'address_ip'],
                [$logicalFlow->physicalServerSource, 'address_ip'],
                [$logicalFlow->storageDeviceSource, 'address_ip'],
                [$logicalFlow->workstationSource, 'address_ip'],
                [$logicalFlow->physicalSecurityDeviceSource, 'address_ip'],
                [$logicalFlow->securityDeviceSource, 'address_ip'],
                [$logicalFlow->subnetworkSource, 'address'],
                [$logicalFlow->clusterSource, 'address'],
            ], $selectedVues);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.source_port'), $logicalFlow->source_port ?? 'ANY');

            $destRun = $helper->addTextRunRow($table, trans('cruds.logicalFlow.fields.dest_ip_range'));
            $this->addFlowEndpointRun($destRun, $helper, $logicalFlow->dest_ip_range, [
                [$logicalFlow->logicalServerDest, 'address_ip'],
                [$logicalFlow->peripheralDest, 'address_ip'],
                [$logicalFlow->physicalServerDest, 'address_ip'],
                [$logicalFlow->storageDeviceDest, 'address_ip'],
                [$logicalFlow->workstationDest, 'address_ip'],
                [$logicalFlow->physicalSecurityDeviceDest, 'address_ip'],
                [$logicalFlow->securityDeviceDest, 'address_ip'],
                [$logicalFlow->subnetworkDest, 'address'],
                [$logicalFlow->clusterDest, 'address'],
            ], $selectedVues);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.dest_port'), $logicalFlow->dest_port ?? 'ANY');

            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.users'), $logicalFlow->users);
            $helper->addTextRow($table, trans('cruds.logicalFlow.fields.schedule'), $logicalFlow->schedule);
        }
    }

    /**
     * Renders one LogicalFlow endpoint: the raw IP range when set, otherwise the address field of
     * the first non-null polymorphic endpoint model, followed by a link/text to that model.
     *
     * @param  array<int, array{0: (Model&HasUniqueIdentifierContract)|null, 1: string}>  $candidates
     * @param  array<int, string>  $selectedVues
     */
    private function addFlowEndpointRun(TextRun $run, WordHelper $helper, ?string $ipRange, array $candidates, array $selectedVues): void
    {
        if ($ipRange !== null) {
            $run->addText($ipRange);

            return;
        }

        foreach ($candidates as [$model, $addressField]) {
            if ($model === null) {
                continue;
            }

            $run->addText((string) $model->getAttribute($addressField).' (');
            $helper->linkOrText($run, $model, $selectedVues);
            $run->addText(')');

            return;
        }
    }

    /**
     * @param  Collection<int, Vlan>  $vlans
     * @param  array<int, string>  $selectedVues
     */
    private function addVlans(Section $section, WordHelper $helper, Collection $vlans, array $selectedVues): void
    {
        if ($vlans->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.vlan.title'), 2);

        foreach ($vlans as $vlan) {
            $helper->addBookmarkedTitle($section, $vlan->getUID(), (string) $vlan->name, 3);
            $table = $helper->addTable($section, (string) $vlan->name);

            $helper->addTextRow($table, trans('cruds.vlan.fields.vlan_id'), (string) $vlan->vlan_id);
            $helper->addHTMLRow($table, trans('cruds.vlan.fields.description'), $vlan->description);

            if ($vlan->subnetworks->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.vlan.fields.subnetworks'), $vlan->subnetworks, $selectedVues);
            }

            if ($vlan->networkSwitches->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.vlan.fields.network_switches'), $vlan->networkSwitches, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, Certificate>  $certificates
     * @param  array<int, string>  $selectedVues
     */
    private function addCertificates(Section $section, WordHelper $helper, Collection $certificates, array $selectedVues): void
    {
        if ($certificates->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.certificate.title'), 2);

        foreach ($certificates as $certificate) {
            $helper->addBookmarkedTitle($section, $certificate->getUID(), (string) $certificate->name, 3);
            $table = $helper->addTable($section, (string) $certificate->name);

            $helper->addTextRow($table, trans('cruds.certificate.fields.type'), $certificate->type);
            $helper->addHTMLRow($table, trans('cruds.certificate.fields.description'), $certificate->description);
            $helper->addTextRow($table, trans('cruds.certificate.fields.start_validity'), $certificate->start_validity);
            $helper->addTextRow($table, trans('cruds.certificate.fields.end_validity'), $certificate->end_validity);
            $helper->addTextRow($table, trans('cruds.certificate.fields.last_notification'), $certificate->getAttribute('last_notification'));

            if ($certificate->logicalServers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.certificate.fields.logical_servers'), $certificate->logicalServers, $selectedVues);
            }

            if ($certificate->applications->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.certificate.fields.applications'), $certificate->applications, $selectedVues);
            }
        }
    }

    private function formatAttributes(?string $attributes): ?string
    {
        if ($attributes === null || trim($attributes) === '') {
            return $attributes;
        }

        return implode(', ', array_filter(explode(' ', $attributes)));
    }
}
