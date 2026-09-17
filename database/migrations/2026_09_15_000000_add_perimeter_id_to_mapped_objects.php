<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'activities',
        'actors',
        'annuaires',
        'application_blocks',
        'application_flows',
        'application_modules',
        'applications',
        'application_services',
        'backups',
        'bays',
        'buildings',
        'certificates',
        'clusters',
        'containers',
        'databases',
        'data_processing',
        'dhcp_servers',
        'dnsservers',
        'domains',
        'entities',
        'external_connected_entities',
        'forest_ads',
        'gateways',
        'information',
        'lans',
        'logical_flows',
        'logical_servers',
        'macro_processuses',
        'mans',
        'networks',
        'network_switches',
        'operations',
        'peripherals',
        'phones',
        'physical_links',
        'physical_routers',
        'physical_security_devices',
        'physical_servers',
        'physical_switches',
        'processes',
        'relations',
        'routers',
        'security_controls',
        'security_devices',
        'sites',
        'storage_devices',
        'subnetworks',
        'tasks',
        'vlans',
        'wans',
        'wifi_terminals',
        'workstations',
        'zone_admins',
        'zones',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('perimeter_id')->after('id')->default(1)->index();
            });
        }

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreign('perimeter_id')->references('id')->on('perimeters')->onUpdate('NO ACTION')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['perimeter_id']);
            });
        }

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('perimeter_id');
            });
        }
    }
};
