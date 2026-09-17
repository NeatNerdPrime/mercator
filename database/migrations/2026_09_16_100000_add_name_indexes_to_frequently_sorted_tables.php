<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Toutes les tables de la cartographie ayant une colonne `name` (varchar(255)) mais aucun
     * index dessus — trouvées par un scan de information_schema, en excluant les tables
     * techniques hors périmètre cartographie (oauth_*, personal_access_tokens, media, users).
     *
     * Ces tables sont triées par `name` presque partout dans l'admin (Cartographer::scopedQuery
     * (...)->orderBy('name')) mais sans index dessus. Invisible à petite échelle ; passé quelques
     * milliers de lignes, MySQL retombe sur un filesort à chaque requête — c'est ce qui a rendu
     * le rapport "infrastructure logique" toujours lent après la correction du N+1 : même un
     * simple `SELECT * ORDER BY name` sur 2000 serveurs logiques prenait 10s.
     */
    private array $tables = [
        // Tables du rapport "infrastructure logique"
        'logical_servers',
        'clusters',
        'certificates',
        'network_switches',
        'vlans',
        'routers',
        'security_devices',
        'physical_security_devices',
        'storage_devices',
        'workstations',
        'phones',
        'wifi_terminals',
        'peripherals',
        'dhcp_servers',
        'dnsservers',
        'networks',
        'subnetworks',
        'gateways',
        'external_connected_entities',
        // Reste de la cartographie (écosystème, processus métier, applicatif, infra physique)
        'activities',
        'actors',
        'annuaires',
        'application_blocks',
        'application_flows',
        'application_modules',
        'application_services',
        'applications',
        'backups',
        'bays',
        'buildings',
        'data_processing',
        'databases',
        'domains',
        'entities',
        'forest_ads',
        'graphs',
        'information',
        'lans',
        'logical_flows',
        'macro_processuses',
        'mans',
        'operations',
        'physical_routers',
        'physical_servers',
        'physical_switches',
        'processes',
        'relations',
        'saved_queries',
        'security_controls',
        'sites',
        'tasks',
        'wans',
        'zone_admins',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('name');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['name']);
            });
        }
    }
};
