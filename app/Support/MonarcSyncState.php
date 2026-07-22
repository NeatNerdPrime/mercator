<?php

namespace App\Support;

use App\Models\Parameter;

/**
 * Persists the /admin/monarc "Create/Synchronize ANR" workflow's linked-ANR
 * state (id, label, model used at creation, last successful sync date) in
 * the `parameters` table, under its own row — mirrors MonarcSelectionState,
 * never mixed with MonarcSettings's connection config (url/uid/password) or
 * the selection screen's own draft state.
 */
class MonarcSyncState
{
    private const PARAM_NAME = 'monarc_sync';

    /**
     * @return array{anr_id?: int, anr_label?: string, model_id?: int, last_synced_at?: string}
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

    public static function anrId(): ?int
    {
        $id = self::load()['anr_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /**
     * Forgets the local link entirely (the remote ANR itself is left
     * untouched) — used by the "reset link" action, alongside clearing the
     * monarc_sync_items table.
     */
    public static function reset(): void
    {
        self::save([]);
    }
}
