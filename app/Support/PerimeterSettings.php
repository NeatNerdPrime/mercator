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

    public static function isEnabled(): bool
    {
        return Parameter::getValue(self::PARAM_NAME, '0') === '1';
    }

    public static function setEnabled(bool $enabled): void
    {
        Parameter::setValue(self::PARAM_NAME, $enabled ? '1' : '0');
    }
}
