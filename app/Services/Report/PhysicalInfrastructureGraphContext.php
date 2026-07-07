<?php

namespace App\Services\Report;

use App\Models\Bay;
use App\Models\Building;
use App\Models\Peripheral;
use App\Models\Phone;
use App\Models\PhysicalLink;
use App\Models\PhysicalRouter;
use App\Models\PhysicalSecurityDevice;
use App\Models\PhysicalServer;
use App\Models\PhysicalSwitch;
use App\Models\StorageDevice;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bundles the many device collections PhysicalInfrastructureGraphBuilder's buildLocationDot()/
 * buildConnectivityDot() need, so the per-Building/per-Site subgraph filtering in
 * PhysicalInfrastructureSection doesn't have to pass a dozen parameters around by hand.
 */
class PhysicalInfrastructureGraphContext
{
    /**
     * @param  Collection<int, Building>  $buildings
     * @param  Collection<int, Bay>  $bays
     * @param  Collection<int, PhysicalServer>  $physicalServers
     * @param  Collection<int, Workstation>  $workstations
     * @param  Collection<int, StorageDevice>  $storageDevices
     * @param  Collection<int, Peripheral>  $peripherals
     * @param  Collection<int, Phone>  $phones
     * @param  Collection<int, PhysicalSwitch>  $physicalSwitches
     * @param  Collection<int, PhysicalRouter>  $physicalRouters
     * @param  Collection<int, WifiTerminal>  $wifiTerminals
     * @param  Collection<int, PhysicalSecurityDevice>  $physicalSecurityDevices
     * @param  Collection<int, PhysicalLink>  $physicalLinks
     */
    public function __construct(
        public Collection $buildings,
        public Collection $bays,
        public Collection $physicalServers,
        public Collection $workstations,
        public Collection $storageDevices,
        public Collection $peripherals,
        public Collection $phones,
        public Collection $physicalSwitches,
        public Collection $physicalRouters,
        public Collection $wifiTerminals,
        public Collection $physicalSecurityDevices,
        public Collection $physicalLinks,
    ) {}
}
