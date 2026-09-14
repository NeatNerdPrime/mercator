<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedInteger('perimetre_id')->after('id')->default(1)->index();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('perimetre_id')->references('id')->on('perimetres')->onUpdate('NO ACTION')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['perimetre_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('perimetre_id');
        });
    }
};
