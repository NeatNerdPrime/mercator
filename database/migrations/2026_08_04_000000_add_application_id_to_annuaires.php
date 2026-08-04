<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute une référence facultative vers l'Application qui met en œuvre / gère
     * le service d'annuaire d'administration (cf. discussion #2506).
     */
    public function up(): void
    {
        Schema::table('annuaires', function (Blueprint $table) {
            $table->unsignedInteger('application_id')->nullable();
        });

        Schema::table('annuaires', function (Blueprint $table) {
            $table->foreign('application_id')->references('id')->on('applications');
        });
    }

    public function down(): void
    {
        Schema::table('annuaires', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['application_id']);
            }
        });

        Schema::table('annuaires', function (Blueprint $table) {
            $table->dropColumn('application_id');
        });
    }
};
