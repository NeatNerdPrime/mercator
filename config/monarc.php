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

    // MOSP (MONARC Objects Sharing Platform) — the default knowledge-base
    // source for the export screen. See MospService.
    'mosp_base' => 'https://objects.monarc.lu/api/v2/object',

    // monarc_version embedded in a MOSP-sourced export (MOSP objects carry
    // no version info of their own) — see config/monarc-defaults.php for the
    // other MOSP-less defaults (scales, method, thresholds, soaScaleComments).
    'target_monarc_version' => '2.13.3',
];
