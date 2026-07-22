<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Mercator object already imported into a given Monarc ANR —
 * see App\Services\MonarcSyncService, which uses this table to compute the
 * "only new objects" diff on every synchronization.
 */
class MonarcSyncItem extends Model
{
    protected $fillable = ['model', 'mercator_id', 'object_uuid', 'anr_id', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
