<?php

namespace App\Support;

use App\Models\Parameter;

/**
 * Persists the /admin/monarc selection screen's in-progress form state
 * (name, description, language, chosen MOSP referentials, per-row
 * global/local selections) in the `parameters` table, under its own row —
 * never mixed with MonarcSettings's connection config — so a user's
 * selection survives a page reload. Saved automatically whenever an export
 * is generated, and on demand via the page's "Save" button.
 */
class MonarcSelectionState
{
    private const PARAM_NAME = 'monarc_selection';

    /**
     * @return array{name?: string, description?: string, language?: string, mosp_referentials?: array<int, int>, rows?: array}
     */
    public static function load(): array
    {
        $stored = Parameter::getValue(self::PARAM_NAME);

        if ($stored === null) {
            return [];
        }

        return json_decode($stored, true) ?? [];
    }

    public static function save(array $state): void
    {
        Parameter::setValue(self::PARAM_NAME, json_encode($state));
    }
}
