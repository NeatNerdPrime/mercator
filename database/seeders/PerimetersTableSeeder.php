<?php

namespace Database\Seeders;

use App\Models\Perimeter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerimetersTableSeeder extends Seeder
{
    /**
     * Périmètre par défaut (id=1, cf. App\Models\Perimeter::DEFAULT_ID, référencé
     * en dur par le DEFAULT de roles.perimeter_id/admin_users.perimeter_id).
     * Normalement inséré par la migration create_perimeters_table, mais
     * celle-ci est ignorée par `migrate:fresh` quand un dump de schéma
     * existe (le dump ne contient que la structure, pas les données).
     *
     * L'id est fixé explicitement plutôt que laissé à l'auto-increment : sous
     * RefreshDatabase (rollback de transaction par test), l'AUTO_INCREMENT
     * InnoDB n'est lui-même jamais rollback, donc un id auto-généré dérive au
     * fil des tests (2, 3, ...) et ne correspond plus au DEFAULT_ID=1 codé en dur.
     */
    public function run(): void
    {
        if (! DB::table('perimeters')->where('id', Perimeter::DEFAULT_ID)->exists()) {
            DB::table('perimeters')->insert([
                'id' => Perimeter::DEFAULT_ID,
                'nom' => 'Défaut',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
