<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            $table->unsignedInteger('domain_id')->nullable();
        });

        Schema::table('peripherals', function (Blueprint $table) {
            $table->foreign('domain_id')->references('id')->on('domains');
        });

        DB::statement(
            'UPDATE peripherals SET domain_id = ('
            .'SELECT id FROM domains WHERE domains.name = peripherals.domain'
            .') WHERE peripherals.domain IS NOT NULL AND EXISTS ('
            .'SELECT 1 FROM domains WHERE domains.name = peripherals.domain'
            .')'
        );

        Schema::table('peripherals', function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }

    public function down(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            $table->string('domain')->nullable();
        });

        DB::statement(
            'UPDATE peripherals SET domain = ('
            .'SELECT name FROM domains WHERE domains.id = peripherals.domain_id'
            .') WHERE peripherals.domain_id IS NOT NULL'
        );

        Schema::table('peripherals', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['domain_id']);
            }
        });

        Schema::table('peripherals', function (Blueprint $table) {
            $table->dropColumn('domain_id');
        });
    }
};
