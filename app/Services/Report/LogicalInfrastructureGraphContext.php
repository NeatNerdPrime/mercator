<?php

namespace App\Services\Report;

use App\Models\Certificate;
use App\Models\Cluster;
use App\Models\Container;
use App\Models\DhcpServer;
use App\Models\Dnsserver;
use App\Models\ExternalConnectedEntity;
use App\Models\Gateway;
use App\Models\LogicalServer;
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
use Illuminate\Database\Eloquent\Collection;

/**
 * Bundles the many device collections LogicalInfrastructureGraphBuilder::buildDot() needs, so the
 * per-Network/per-Subnetwork subgraph filtering in LogicalInfrastructureSection doesn't have to pass
 * 19 parameters around by hand.
 */
class LogicalInfrastructureGraphContext
{
    /**
     * @param  Collection<int, Subnetwork>  $subnetworks
     * @param  Collection<int, Gateway>  $gateways
     * @param  Collection<int, ExternalConnectedEntity>  $externalConnectedEntities
     * @param  Collection<int, Vlan>  $vlans
     * @param  Collection<int, NetworkSwitch>  $networkSwitches
     * @param  Collection<int, Cluster>  $clusters
     * @param  Collection<int, LogicalServer>  $logicalServers
     * @param  Collection<int, DhcpServer>  $dhcpServers
     * @param  Collection<int, Dnsserver>  $dnsservers
     * @param  Collection<int, Certificate>  $certificates
     * @param  Collection<int, Container>  $containers
     * @param  Collection<int, Router>  $routers
     * @param  Collection<int, SecurityDevice>  $securityDevices
     * @param  Collection<int, Workstation>  $workstations
     * @param  Collection<int, WifiTerminal>  $wifiTerminals
     * @param  Collection<int, Phone>  $phones
     * @param  Collection<int, Peripheral>  $peripherals
     * @param  Collection<int, PhysicalSecurityDevice>  $physicalSecurityDevices
     * @param  Collection<int, StorageDevice>  $storageDevices
     */
    public function __construct(
        public Collection $subnetworks,
        public Collection $gateways,
        public Collection $externalConnectedEntities,
        public Collection $vlans,
        public Collection $networkSwitches,
        public Collection $clusters,
        public Collection $logicalServers,
        public Collection $dhcpServers,
        public Collection $dnsservers,
        public Collection $certificates,
        public Collection $containers,
        public Collection $routers,
        public Collection $securityDevices,
        public Collection $workstations,
        public Collection $wifiTerminals,
        public Collection $phones,
        public Collection $peripherals,
        public Collection $physicalSecurityDevices,
        public Collection $storageDevices,
    ) {}
}
