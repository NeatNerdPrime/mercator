<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'unicité du nom devient applicative, scopée par périmètre
     * (Rule::unique(...)->where('perimeter_id', ...) dans les FormRequests) —
     * pas de contrainte unique composite en base (décidé). Ces deux index
     * uniques physiques sur (name, deleted_at) empêcheraient sinon le même
     * nom dans deux périmètres différents. L'index sur perimeter_id existe
     * déjà (posé par la migration add_perimeter_id_to_mapped_objects).
     */
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropUnique('container_name_unique');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropUnique('zones_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->unique(['name', 'deleted_at'], 'container_name_unique');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->unique(['name', 'deleted_at'], 'zones_name_unique');
        });
    }
};
