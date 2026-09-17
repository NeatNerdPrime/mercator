<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerimetersTableSeeder extends Seeder
{
    /**
     * Périmètre par défaut (id=1, cf. App\Models\Perimeter::DEFAULT_ID).
     * Normalement inséré par la migration create_perimeters_table, mais
     * celle-ci est ignorée par `migrate:fresh` quand un dump de schéma
     * existe (le dump ne contient que la structure, pas les données).
     */
    public function run(): void
    {
        if (DB::table('perimeters')->count() === 0) {
            DB::table('perimeters')->insert([
                'nom' => 'Défaut',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
