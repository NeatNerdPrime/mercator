<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('status')->after('icon_id')->nullable();
            $table->date('prod_date')->after('install_date')->nullable();
            $table->string('hosting')->after('external')->nullable();
            $table->string('urls')->after('hosting')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['status', 'prod_date', 'hosting', 'urls']);
        });
    }
};
