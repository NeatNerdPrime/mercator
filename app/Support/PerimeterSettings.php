<?php

namespace App\Support;

use App\Models\Parameter;

/**
 * Drapeau d'activation de la fonctionnalité "périmètres" (cloisonnement
 * multi-établissement), persisté dans la table `parameters`, sur le motif
 * de MercatorSettings/MonarcSettings.
 */
class PerimeterSettings
{
    private const PARAM_NAME = 'perimeters_enabled';

    /**
     * Memoized per request/process: PerimeterScope calls isEnabled() on every single
     * query built for a perimeter-scoped model (i.e. every query in the app), so an
     * uncached DB round-trip here is the single most expensive line in the codebase.
     */
    private static ?bool $enabledCache = null;

    public static function isEnabled(): bool
    {
        return self::$enabledCache ??= Parameter::getValue(self::PARAM_NAME, '0') === '1';
    }

    public static function setEnabled(bool $enabled): void
    {
        Parameter::setValue(self::PARAM_NAME, $enabled ? '1' : '0');
        self::$enabledCache = $enabled;
    }

    /**
     * Called once per application boot (see AppServiceProvider::boot()) so the
     * memoization never survives into the next request or test.
     */
    public static function resetCache(): void
    {
        self::$enabledCache = null;
    }
}
