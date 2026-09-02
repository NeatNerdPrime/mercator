<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Préférence utilisateur : libellé des flux applicatifs dans la vue Exploration.
            // Valeurs : 'name' ou 'nature'. NULL => valeur par défaut appliquée côté code ('nature').
            $table->string('flow_label', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('flow_label');
        });
    }
};
