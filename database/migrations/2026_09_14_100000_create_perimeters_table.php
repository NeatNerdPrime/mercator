<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perimeters', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 32)->unique();
            $table->timestamps();
        });

        // Périmètre par défaut : première ligne -> id = 1. Nom provisoire,
        // renommé lors de l'activation de la fonctionnalité.
        DB::table('perimeters')->insert([
            'nom' => 'Défaut',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('perimeters');
    }
};
