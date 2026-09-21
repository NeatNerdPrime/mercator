<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'index unique est supprimé puis recréé (au lieu de renameIndex) pour rester
        // portable entre MySQL, SQLite et PostgreSQL et changer son nom (perimeters_name_unique).
        Schema::table('perimeters', function (Blueprint $table) {
            $table->dropUnique('perimeters_nom_unique');
        });

        Schema::table('perimeters', function (Blueprint $table) {
            $table->renameColumn('nom', 'name');
        });

        Schema::table('perimeters', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('perimeters', function (Blueprint $table) {
            $table->dropUnique('perimeters_name_unique');
        });

        Schema::table('perimeters', function (Blueprint $table) {
            $table->renameColumn('name', 'nom');
        });

        Schema::table('perimeters', function (Blueprint $table) {
            $table->unique('nom');
        });
    }
};
