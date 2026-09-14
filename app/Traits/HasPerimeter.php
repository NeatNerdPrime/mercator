<?php

namespace App\Traits;

use App\Models\Perimeter;
use App\Scopes\PerimeterScope;
use App\Support\PerimeterSettings;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Route-model binding normally lets PerimeterScope hide an out-of-perimeter
     * object behind a plain 404, indistinguishable from "doesn't exist". Here we
     * tell the two apart: if the object exists once PerimeterScope is lifted, the
     * user simply isn't allowed to see it from their active perimeter, so answer
     * 403 (access denied) rather than 404 (not found).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $model = parent::resolveRouteBinding($value, $field);

        if ($model !== null || ! PerimeterSettings::isEnabled()) {
            return $model;
        }

        $existsOutsideActivePerimeter = static::withoutGlobalScope(PerimeterScope::class)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->exists();

        abort_if($existsOutsideActivePerimeter, Response::HTTP_FORBIDDEN, '403 Forbidden');

        return null;
    }
}
