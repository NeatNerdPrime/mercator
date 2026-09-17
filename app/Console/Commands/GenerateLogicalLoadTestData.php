<?php

namespace App\Console\Commands;

use App\Factories\ClusterFactory;
use App\Factories\LogicalServerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateLogicalLoadTestData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mercator:generate-logical-load-test-data
        {--servers=2000 : Nombre de serveurs logiques à créer}
        {--clusters=20 : Nombre de clusters à créer}
        {--min=2 : Nombre minimum de clusters par serveur}
        {--max=3 : Nombre maximum de clusters par serveur}
        {--chunk=500 : Taille des lots d\'insertion}';

    /**
     * The console command description.
     */
    protected $description = 'Génère des serveurs logiques et des clusters de test (répartis aléatoirement) pour tester la cartographie à grande échelle';

    public function handle(): int
    {
        $serverCount = (int) $this->option('servers');
        $clusterCount = (int) $this->option('clusters');
        $min = (int) $this->option('min');
        $max = (int) $this->option('max');
        $chunkSize = (int) $this->option('chunk');

        if ($min < 1 || $max < $min) {
            $this->error("Bornes invalides : min={$min}, max={$max}.");

            return self::FAILURE;
        }

        if ($max > $clusterCount) {
            $this->error("max ({$max}) ne peut pas dépasser le nombre de clusters créés ({$clusterCount}).");

            return self::FAILURE;
        }

        if (! $this->confirm("Créer {$serverCount} serveurs logiques et {$clusterCount} clusters (chaque serveur relié à {$min}-{$max} clusters) ?", true)) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        $this->info('Création des clusters...');
        $clusterIds = $this->insertClusters($clusterCount);
        $this->info(count($clusterIds).' clusters créés.');

        $this->info('Création des serveurs logiques...');
        $serverIds = $this->insertLogicalServers($serverCount, $chunkSize);
        $this->info(count($serverIds).' serveurs logiques créés.');

        $this->info('Association serveurs <-> clusters...');
        $pivotRows = $this->attachClusters($serverIds, $clusterIds, $min, $max, $chunkSize);
        $this->info("{$pivotRows} associations créées.");

        // Après une insertion en masse, les statistiques MySQL sur ces tables sont périmées :
        // le premier JOIN complexe qui suit (ex. le rapport infrastructure logique) peut alors
        // choisir un mauvais plan d'exécution et sembler anormalement lent, le temps qu'elles se
        // rafraîchissent naturellement. On les rafraîchit tout de suite pour éviter ce faux signal.
        $this->info('Rafraîchissement des statistiques MySQL...');
        DB::statement('ANALYZE TABLE logical_servers, clusters, cluster_logical_server');

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function insertClusters(int $count): array
    {
        $factory = new ClusterFactory;
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $attributes = $factory->definition();
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;
            $rows[] = $attributes;
        }

        $firstId = (int) (DB::table('clusters')->max('id')) + 1;

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('clusters')->insert($chunk);
        }

        $lastId = (int) DB::table('clusters')->max('id');

        return range($firstId, $lastId);
    }

    /**
     * @return list<int>
     */
    private function insertLogicalServers(int $count, int $chunkSize): array
    {
        $factory = new LogicalServerFactory;
        $now = now();
        $created = 0;

        $firstId = (int) (DB::table('logical_servers')->max('id')) + 1;

        $bar = $this->output->createProgressBar($count);

        while ($created < $count) {
            $batchSize = min($chunkSize, $count - $created);
            $rows = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $attributes = $factory->definition();
                $attributes['created_at'] = $now;
                $attributes['updated_at'] = $now;
                $rows[] = $attributes;
            }

            DB::table('logical_servers')->insert($rows);

            $created += $batchSize;
            $bar->advance($batchSize);
        }

        $bar->finish();
        $this->newLine();

        $lastId = (int) DB::table('logical_servers')->max('id');

        return range($firstId, $lastId);
    }

    /**
     * @param  list<int>  $serverIds
     * @param  list<int>  $clusterIds
     * @return int Number of pivot rows inserted
     */
    private function attachClusters(array $serverIds, array $clusterIds, int $min, int $max, int $chunkSize): int
    {
        $bar = $this->output->createProgressBar(count($serverIds));
        $total = 0;
        $rows = [];

        foreach ($serverIds as $serverId) {
            $howMany = random_int($min, $max);
            $chosen = (array) array_rand(array_flip($clusterIds), $howMany);

            foreach ($chosen as $clusterId) {
                $rows[] = [
                    'cluster_id' => $clusterId,
                    'logical_server_id' => $serverId,
                ];
            }

            if (count($rows) >= $chunkSize) {
                DB::table('cluster_logical_server')->insertOrIgnore($rows);
                $total += count($rows);
                $rows = [];
            }

            $bar->advance();
        }

        if (! empty($rows)) {
            DB::table('cluster_logical_server')->insertOrIgnore($rows);
            $total += count($rows);
        }

        $bar->finish();
        $this->newLine();

        return $total;
    }
}
