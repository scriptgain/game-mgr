<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Panel-wide defaults. Everything here is DB-driven rather than env-driven, so
 * an operator can change it without shell access, and AppServiceProvider
 * applies it over config at boot.
 */
class GeneralSettingsController extends Controller
{
    /** Defaults for every General setting. Keys are the Setting table keys. */
    public static function defaults(): array
    {
        return [
            // Regional and display
            'timezone' => config('app.timezone', 'UTC'),
            'date_format' => 'M j, Y',
            'time_format' => 'g:i A',
            'rows_per_page' => '25',

            // Defaults offered when creating a server
            'default_memory' => '2048',
            'default_disk' => '10240',
            'default_cpu' => '200',
            'allow_client_server_create' => '0',

            // Nodes
            'node_offline_after' => '120',
            'node_fake' => '0',

            // History and housekeeping
            'metric_history_days' => '30',
            'audit_log_days' => '180',

            // Security
            'session_timeout_minutes' => '120',
            'require_2fa' => '0',
            'force_password_days' => '0',
        ];
    }

    public function edit()
    {
        $map = Setting::map();
        $v = [];
        foreach (static::defaults() as $key => $default) {
            $v[$key] = $map[$key] ?? $default;
        }

        return view('settings.general', [
            'v' => $v,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'now' => now(),
            'info' => [
                'Product' => config('brand.name'),
                'Version' => \App\Services\UpdateService::currentVersion(),
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Database' => config('database.default'),
                'Environment' => app()->environment(),
                'Server Time' => now()->format('D, M j Y g:i A T'),
            ],
            'counts' => [
                'Nodes' => \App\Models\Node::count(),
                'Servers' => \App\Models\Server::count(),
                'Templates' => \App\Models\Template::count(),
                'Users' => \App\Models\User::count(),
                'Metric Samples' => \App\Models\ServerMetric::count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'date_format' => ['required', 'string', 'max:20'],
            'time_format' => ['required', 'string', 'max:20'],
            'rows_per_page' => ['required', 'integer', 'min:10', 'max:200'],
            'default_memory' => ['required', 'integer', 'min:128', 'max:1048576'],
            'default_disk' => ['required', 'integer', 'min:512', 'max:10485760'],
            'default_cpu' => ['required', 'integer', 'min:10', 'max:6400'],
            'node_offline_after' => ['required', 'integer', 'min:30', 'max:3600'],
            'metric_history_days' => ['required', 'integer', 'min:1', 'max:730'],
            'audit_log_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
            'force_password_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        // Toggles submit "0" or "1" through a hidden input; normalise explicitly
        // rather than relying on the checkbox being present.
        foreach (['require_2fa', 'allow_client_server_create', 'node_fake'] as $toggle) {
            $data[$toggle] = $request->boolean($toggle) ? '1' : '0';
        }

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        AuditLog::record('settings.update', 'General settings updated');

        return back()->with('status', 'General settings saved.');
    }
}
