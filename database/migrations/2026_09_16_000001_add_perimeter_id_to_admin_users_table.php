<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->unsignedInteger('perimeter_id')->after('id')->default(1)->index();
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->foreign('perimeter_id')->references('id')->on('perimeters')->onUpdate('NO ACTION')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropForeign(['perimeter_id']);
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('perimeter_id');
        });
    }
};
