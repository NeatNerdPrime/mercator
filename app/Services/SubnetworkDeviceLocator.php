<?php

namespace App\Services;

use App\Models\Cluster;
use App\Models\DhcpServer;
use App\Models\Dnsserver;
use App\Models\Gateway;
use App\Models\LogicalServer;
use App\Models\NetworkSwitch;
use App\Models\Peripheral;
use App\Models\Phone;
use App\Models\PhysicalSecurityDevice;
use App\Models\PhysicalServer;
use App\Models\Router;
use App\Models\SecurityDevice;
use App\Models\StorageDevice;
use App\Models\Subnetwork;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use Illuminate\Support\Collection;

/**
 * Localise les équipements dont l'adresse IP appartient à un ou plusieurs sous-réseaux.
 *
 * Le lien équipement -> sous-réseau ne repose sur aucune clé étrangère : il se déduit de
 * l'appartenance de l'IP de l'équipement à la plage CIDR du sous-réseau, via
 * Subnetwork::contains(). IPv4 uniquement pour l'instant (cf. Subnetwork::contains()).
 */
class SubnetworkDeviceLocator
{
    /**
     * Registre modèle => [champ IP, clé de traduction du libellé, nom de route admin.*.show].
     *
     * Mêmes 15 modèles et mêmes champs que LogicalInfrastructureSection::ipMatchesSubnetworks().
     *
     * @var array<class-string, array{field: string, trans: string, route: string}>
     */
    private const REGISTRY = [
        Cluster::class => ['field' => 'address_ip', 'trans' => 'cruds.cluster.title_singular', 'route' => 'admin.clusters.show'],
        DhcpServer::class => ['field' => 'address_ip', 'trans' => 'cruds.dhcpServer.title_singular', 'route' => 'admin.dhcp-servers.show'],
        Dnsserver::class => ['field' => 'address_ip', 'trans' => 'cruds.dnsserver.title_singular', 'route' => 'admin.dnsservers.show'],
        Gateway::class => ['field' => 'ip', 'trans' => 'cruds.gateway.title_singular', 'route' => 'admin.gateways.show'],
        LogicalServer::class => ['field' => 'address_ip', 'trans' => 'cruds.logicalServer.title_singular', 'route' => 'admin.logical-servers.show'],
        NetworkSwitch::class => ['field' => 'ip', 'trans' => 'cruds.networkSwitch.title_singular', 'route' => 'admin.network-switches.show'],
        Peripheral::class => ['field' => 'address_ip', 'trans' => 'cruds.peripheral.title_singular', 'route' => 'admin.peripherals.show'],
        Phone::class => ['field' => 'address_ip', 'trans' => 'cruds.phone.title_singular', 'route' => 'admin.phones.show'],
        PhysicalSecurityDevice::class => ['field' => 'address_ip', 'trans' => 'cruds.physicalSecurityDevice.title_singular', 'route' => 'admin.physical-security-devices.show'],
        PhysicalServer::class => ['field' => 'address_ip', 'trans' => 'cruds.physicalServer.title_singular', 'route' => 'admin.physical-servers.show'],
        Router::class => ['field' => 'ip_addresses', 'trans' => 'cruds.router.title_singular', 'route' => 'admin.routers.show'],
        SecurityDevice::class => ['field' => 'address_ip', 'trans' => 'cruds.securityDevice.title_singular', 'route' => 'admin.security-devices.show'],
        StorageDevice::class => ['field' => 'address_ip', 'trans' => 'cruds.storageDevice.title_singular', 'route' => 'admin.storage-devices.show'],
        WifiTerminal::class => ['field' => 'address_ip', 'trans' => 'cruds.wifiTerminal.title_singular', 'route' => 'admin.wifi-terminals.show'],
        Workstation::class => ['field' => 'address_ip', 'trans' => 'cruds.workstation.title_singular', 'route' => 'admin.workstations.show'],
    ];

    /**
     * Retourne une ligne par couple (équipement, IP matchée) pour les sous-réseaux fournis.
     *
     * Une seule requête par table d'équipement. Un équipement dont le champ IP contient
     * plusieurs adresses séparées par des virgules produit une ligne par IP qui matche
     * un des sous-réseaux (à la première correspondance par sous-réseau).
     *
     * @param  Subnetwork|Collection<int, Subnetwork>  $subnetworks
     * @return Collection<int, array{type: string, model: string, id: int, name: string, ip: string, route: string, subnetwork_id: int}>
     */
    public function devicesIn(Subnetwork|Collection $subnetworks): Collection
    {
        $subnetworks = $subnetworks instanceof Subnetwork ? collect([$subnetworks]) : $subnetworks->values();

        if ($subnetworks->isEmpty()) {
            return collect();
        }

        $rows = collect();

        foreach (self::REGISTRY as $modelClass => $meta) {
            $records = $modelClass::query()
                ->select(['id', 'name', $meta['field']])
                ->whereNotNull($meta['field'])
                ->get();

            foreach ($records as $record) {
                foreach (explode(',', (string) $record->getAttribute($meta['field'])) as $rawIp) {
                    $ip = trim($rawIp);
                    if ($ip === '') {
                        continue;
                    }

                    foreach ($subnetworks as $subnetwork) {
                        if (! $subnetwork->contains($ip)) {
                            continue;
                        }

                        $rows->push([
                            'type' => trans($meta['trans']),
                            'model' => class_basename($modelClass),
                            'id' => $record->id,
                            'name' => (string) $record->name,
                            'ip' => $ip,
                            'route' => route($meta['route'], $record->id),
                            'subnetwork_id' => $subnetwork->id,
                        ]);

                        break;
                    }
                }
            }
        }

        return $rows;
    }
}
