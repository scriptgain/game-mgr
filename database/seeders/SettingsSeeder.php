<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // The seeder creates the admin itself, so the first-run wizard has
            // nothing left to do. Without this every request redirects to /setup.
            'setup_complete' => '1',
            'brand_name' => 'GameMGR',
            'brand_tagline' => 'Free Game Server Control Panel',
            'brand_accent' => '#6d28d9',
            'timezone' => 'America/Phoenix',
            'date_format' => 'M j, Y',
            'time_format' => 'g:i A',
            'rows_per_page' => '25',
            'metric_history_days' => '30',
            'audit_log_days' => '180',
            'default_memory' => '2048',
            'default_disk' => '10240',
            'default_cpu' => '200',
            'allow_client_server_create' => '0',
            'node_offline_after' => '120',
            // The dev stack runs a stub daemon, so an unreachable node is
            // answered with synthetic data rather than an error.
            'node_fake' => '1',
            // Self-update stays off locally: applying a release over a
            // bind-mounted checkout would destroy the working tree.
            'update_auto' => '0',
            'update_requested' => '0',
            'failed_login_limit' => '10',
            'lockout_minutes' => '60',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Apply the timezone to THIS process straight away.
        //
        // AppServiceProvider reads it at boot, but on a first seed against an
        // empty database the setting did not exist yet, so the rest of the
        // seeders would write their timestamps in UTC while every later request
        // reads them back in the configured zone. Laravel does not convert
        // datetimes on the way in or out, so that is a silent seven hour skew
        // that shows up as "last seen six hours from now".
        if (! empty($defaults['timezone'])) {
            config(['app.timezone' => $defaults['timezone']]);
            date_default_timezone_set($defaults['timezone']);
        }
    }
}
