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
        if (Schema::hasColumn('application_flows', 'nature') && ! Schema::hasColumn('application_flows', 'type')) {
            Schema::table('application_flows', function (Blueprint $table) {
                $table->renameColumn('nature', 'type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('application_flows', 'type') && ! Schema::hasColumn('application_flows', 'nature')) {
            Schema::table('application_flows', function (Blueprint $table) {
                $table->renameColumn('type', 'nature');
            });
        }
    }
};
