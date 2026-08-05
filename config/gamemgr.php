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
    'rows_per_page' => 25,
    'metric_history_days' => 30,
    'audit_log_days' => 180,
    'default_memory' => 2048,
    'default_disk' => 10240,
    'default_cpu' => 200,
    'require_2fa' => false,
    'force_password_days' => 0,
    'allow_client_server_create' => false,
];
