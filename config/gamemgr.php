<?php

// Panel-wide layout and defaults.
//
// max_width is the ONE place the container width is set. Every page reads it
// through the layout's $maxWidth, so nothing can drift: a page that hardcodes
// its own max-w-* is a bug, because clicking between two screens then jumps the
// whole page width.
//
// The other keys here are defaults only. AppServiceProvider overrides them from
// the settings table at boot, so an operator changes them in the UI rather than
// in a file.
return [
    'max_width' => env('GAMEMGR_MAX_WIDTH', 'max-w-7xl'),

    'date_format' => 'M j, Y',
    'time_format' => 'g:i A',
    'rows_per_page' => 10,
    'metric_history_days' => 30,
    'audit_log_days' => 180,
    'default_memory' => 2048,
    'default_disk' => 10240,
    'default_cpu' => 200,
    'require_2fa' => false,
    'force_password_days' => 0,
    'allow_client_server_create' => false,

    // MCJars, the catalogue of published Minecraft server builds, used to fill
    // the type and version pickers on templates that declare a `mcjars`
    // document. See App\Services\Minecraft\McJars.
    //
    // The timeout is short on purpose. This is a nicety on a form: a page that
    // hangs for ten seconds because a third party is slow is worse than one
    // that quietly shows the free text box it always used to.
    'mcjars' => [
        'enabled' => env('GAMEMGR_MCJARS', true),
        'base' => env('GAMEMGR_MCJARS_URL', 'https://mcjars.app'),
        'timeout' => (float) env('GAMEMGR_MCJARS_TIMEOUT', 4),
        // Seconds a fresh answer is served for. Builds move fastest, because
        // Paper publishes most days; the list of types barely moves at all.
        'ttl' => [
            'types' => 21600,
            'versions' => 10800,
            'builds' => 1800,
        ],
    ],
];
