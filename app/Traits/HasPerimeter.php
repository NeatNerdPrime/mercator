<?php

namespace App\Traits;

use App\Models\Perimeter;
use App\Scopes\PerimeterScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasPerimeter
{
    protected static function bootHasPerimeter(): void
    {
        static::addGlobalScope(new PerimeterScope);
    }

    public function perimeter(): BelongsTo
    {
        return $this->belongsTo(Perimeter::class);
    }
}
