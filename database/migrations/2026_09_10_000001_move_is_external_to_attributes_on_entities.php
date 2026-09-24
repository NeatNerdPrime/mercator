<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('entities', 'is_external')) {
            DB::table('entities')->where('is_external', true)->orderBy('id')->chunkById(200, function ($entities) {
                foreach ($entities as $entity) {
                    $tags = array_filter(explode(' ', (string) $entity->attributes), fn ($tag) => $tag !== '');
                    if (! in_array('extern', $tags, true)) {
                        $tags[] = 'extern';
                        DB::table('entities')->where('id', $entity->id)->update([
                            'attributes' => implode(' ', $tags),
                        ]);
                    }
                }
            });

            // SQLite cannot drop a column that is still referenced by an index
            $indexes = collect(Schema::getIndexes('entities'))
                ->filter(fn ($index) => ! $index['primary'] && in_array('is_external', $index['columns'], true))
                ->pluck('name');

            Schema::table('entities', function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $index) {
                    $table->dropIndex($index);
                }
                $table->dropColumn('is_external');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('entities', 'is_external')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->boolean('is_external')->nullable()->default(false);
            });

            DB::table('entities')->orderBy('id')->chunkById(200, function ($entities) {
                foreach ($entities as $entity) {
                    $tags = array_filter(explode(' ', (string) $entity->attributes), fn ($tag) => $tag !== '');
                    if (in_array('extern', $tags, true)) {
                        $tags = array_values(array_diff($tags, ['extern']));
                        DB::table('entities')->where('id', $entity->id)->update([
                            'is_external' => true,
                            'attributes' => $tags === [] ? null : implode(' ', $tags),
                        ]);
                    }
                }
            });
        }
    }
};
