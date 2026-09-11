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
        // Drop the redundant generic `type` column added by
        // 2026_09_09_000000_add_type_attributes_to_cartography_objects before `entities` was
        // excluded from it, on installs that already ran it.
        if (Schema::hasColumn('entities', 'type')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        if (Schema::hasColumn('entities', 'entity_type') && ! Schema::hasColumn('entities', 'type')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->renameColumn('entity_type', 'type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('entities', 'type') && ! Schema::hasColumn('entities', 'entity_type')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->renameColumn('type', 'entity_type');
            });
        }

        if (! Schema::hasColumn('entities', 'type')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->string('type')->nullable();
            });
        }
    }
};
