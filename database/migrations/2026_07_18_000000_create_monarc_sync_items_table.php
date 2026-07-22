<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local record of every Mercator object already imported into a Monarc
     * ANR — the source of truth for MonarcSyncService's diff (Monarc's own
     * import always adds, never dedupes, so re-sending an already-imported
     * object would duplicate its instance).
     */
    public function up(): void
    {
        Schema::create('monarc_sync_items', function (Blueprint $table) {
            $table->id();
            $table->string('model');
            $table->unsignedInteger('mercator_id');
            $table->uuid('object_uuid');
            $table->unsignedInteger('anr_id');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['anr_id', 'model', 'mercator_id'], 'monarc_sync_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monarc_sync_items');
    }
};
