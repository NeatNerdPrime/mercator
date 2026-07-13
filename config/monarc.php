<?php

return [
    // Admin-toggleable via the "Monarc" tab of the parameters screen.
    // See App\Support\MonarcSettings, which overlays the `parameters` table
    // entry named "monarc" on top of these defaults at boot.
    'enabled' => false,
    'url' => env('MONARC_URL', ''),
    'uid' => env('MONARC_LOGIN', ''),
    'password' => env('MONARC_PASSWORD', ''),

    // Technical defaults, not exposed in the admin UI.
    'cache_ttl' => 300,
    'timeout' => 15,

    // Legacy MOSP fallback (kept out of the main export flow, see MospService).
    'mosp_base' => 'https://objects.monarc.lu/api/v2/object',
];
