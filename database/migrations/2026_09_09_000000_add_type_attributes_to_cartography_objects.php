<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables getting a `type` column (Phase 0 analysis: 23 cartography objects missing it). */
    private const array TYPE_TABLES = [
        'activities',
        'annuaires',
        'application_blocks',
        'application_modules',
        'application_services',
        'bays',
        'dhcp_servers',
        'dnsservers',
        'domains',
        'forest_ads',
        'gateways',
        'lans',
        'macro_processuses',
        'mans',
        'network_switches',
        'operations',
        'processes',
        'tasks',
        'vlans',
        'wans',
        'zone_admins',
    ];

    /**
     * `application_flows` is excluded from TYPE_TABLES above: it already has a `nature` column
     * serving the exact same purpose as `type`. Renamed in place (see the migration further down)
     * instead of adding a redundant second column.
     *
     * `entities` is excluded too: it already has an `entity_type` column serving the exact same
     * purpose as `type`. Renamed in place (see 2026_09_10_000000_rename_entity_type_to_type_on_entities)
     * instead of adding a redundant second column.
     */

    /**
     * Tables getting an `attributes` column (Phase 0 analysis: 36 cartography objects missing it).
     * `entities` is excluded: its `attributes` column already exists (added by an unrelated
     * earlier migration, 2024_03_19_195927_contracts.php) — only the application-layer wiring
     * (fillable/controller/forms) is missing for it, handled in a later step, not here.
     */
    private const array ATTRIBUTES_TABLES = [
        'activities',
        'actors',
        'annuaires',
        'application_blocks',
        'application_modules',
        'application_services',
        'bays',
        'certificates',
        'containers',
        'databases',
        'dhcp_servers',
        'dnsservers',
        'domains',
        'external_connected_entities',
        'forest_ads',
        'gateways',
        'lans',
        'macro_processuses',
        'mans',
        'network_switches',
        'operations',
        'peripherals',
        'phones',
        'physical_routers',
        'physical_servers',
        'physical_switches',
        'processes',
        'routers',
        'storage_devices',
        'tasks',
        'vlans',
        'wans',
        'wifi_terminals',
        'workstations',
        'zone_admins',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TYPE_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'type')) {
                    $table->string('type')->after('name')->nullable();
                }
            });
        }

        foreach (self::ATTRIBUTES_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'attributes')) {
                    $table->string('attributes')->after('type')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::ATTRIBUTES_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'attributes')) {
                    $table->dropColumn('attributes');
                }
            });
        }

        foreach (self::TYPE_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'type')) {
                    $table->dropColumn('type');
                }
            });
        }
    }
};
