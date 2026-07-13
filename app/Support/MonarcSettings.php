<?php

namespace App\Support;

use App\Models\Parameter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Persists the admin-editable "monarc" config tree (enabled, url, uid, password)
 * in the `parameters` table, mirroring MercatorSettings.
 */
class MonarcSettings
{
    private const PARAM_NAME = 'monarc';

    /**
     * Marks a password value already encrypted by Crypt::encryptString(), so
     * applyToConfig() can tell it apart from the plaintext `.env` dev default.
     */
    private const ENCRYPTED_PREFIX = 'enc:';

    /**
     * Stored overrides, decoded, on top of the static config defaults.
     */
    public static function load(): array
    {
        $stored = Parameter::getValue(self::PARAM_NAME);

        if ($stored === null) {
            return [];
        }

        return json_decode($stored, true) ?? [];
    }

    /**
     * Persist the settings tree (encrypting the password if present) and
     * refresh the in-memory config so the rest of the current request sees
     * the new values immediately.
     */
    public static function save(array $cfg): void
    {
        if (! empty($cfg['password']) && ! str_starts_with($cfg['password'], self::ENCRYPTED_PREFIX)) {
            $cfg['password'] = self::ENCRYPTED_PREFIX.Crypt::encryptString($cfg['password']);
        }

        Parameter::setValue(self::PARAM_NAME, json_encode($cfg));

        config(['monarc' => array_replace_recursive(config('monarc', []), $cfg)]);
    }

    /**
     * Merge stored overrides on top of the static config/monarc.php defaults.
     * Called once at boot.
     */
    public static function applyToConfig(): void
    {
        $overrides = self::load();

        if ($overrides === []) {
            return;
        }

        config(['monarc' => array_replace_recursive(config('monarc', []), $overrides)]);
    }

    public static function enabled(): bool
    {
        return (bool) config('monarc.enabled', false);
    }

    public static function url(): ?string
    {
        return config('monarc.url') ?: null;
    }

    public static function uid(): ?string
    {
        return config('monarc.uid') ?: null;
    }

    /**
     * Whether a password value (encrypted or plaintext dev default) is set.
     * Used by the settings view to render the "a value is stored" placeholder
     * without ever exposing the plaintext value.
     */
    public static function hasPassword(): bool
    {
        return ! empty(config('monarc.password'));
    }

    /**
     * Decrypts the stored password. The value coming from the `parameters`
     * table is always prefixed/encrypted; the `.env`-provided development
     * default is plaintext and never encrypted, so we fall back to it as-is.
     */
    public static function password(): ?string
    {
        $value = config('monarc.password');

        if (empty($value)) {
            return null;
        }

        if (! str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::ENCRYPTED_PREFIX)));
        } catch (DecryptException) {
            return null;
        }
    }
}
