<?php

namespace App\Providers;

use App\Models\Server;
use App\Models\Setting;
use App\Policies\ServerPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The mod catalogues, in the order they are offered on screen. Modrinth
        // first because it indexes both plugins and mods and is the widest net;
        // Hangar second because it is Paper's own and its answers are the most
        // trustworthy for a Paper server.
        //
        // Registering them here rather than discovering them means adding a
        // source is a deliberate line in a diff, which is the right amount of
        // ceremony for something that downloads code onto a customer's machine.
        $this->app->singleton(\App\Services\Mods\ModSourceRegistry::class, fn ($app) => new \App\Services\Mods\ModSourceRegistry([
            $app->make(\App\Services\Mods\Sources\ModrinthSource::class),
            $app->make(\App\Services\Mods\Sources\HangarSource::class),
            $app->make(\App\Services\Mods\Sources\SpigetSource::class),
            $app->make(\App\Services\Mods\Sources\CurseForgeSource::class),
        ]));
    }

    /**
     * Apply DB-backed settings over config at boot (DB-driven config pattern),
     * and register the one policy the whole client area leans on.
     * Guarded so the app still boots before migrations run.
     */
    public function boot(): void
    {
        // A missing $fillable entry silently drops the attribute, which is how
        // the entire live-state pipeline came to be a no-op: three call sites
        // wrote power_state and none of them took. Off in production, where
        // throwing would turn a dropped column into a 500 for the customer.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(Server::class, ServerPolicy::class);

        // Convenience gate so Blade can ask @can('admin') without repeating the
        // role check in thirty templates.
        Gate::define('admin', fn ($user) => $user->isAdmin());

        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
            $s = Setting::map();

            // DB-driven timezone: makes schedules fire and times display in the
            // configured zone instead of UTC.
            if (! empty($s['timezone'])) {
                config(['app.timezone' => $s['timezone']]);
                date_default_timezone_set($s['timezone']);
            }
            if (! empty($s['brand_name'])) {
                config(['brand.name' => $s['brand_name'], 'app.name' => $s['brand_name']]);
            }
            if (! empty($s['brand_tagline'])) {
                config(['brand.tagline' => $s['brand_tagline']]);
            }
            if (! empty($s['brand_accent'])) {
                config(['brand.accent' => $s['brand_accent']]);
            }
            if (! empty($s['session_timeout_minutes'])) {
                config(['session.lifetime' => (int) $s['session_timeout_minutes']]);
            }

            // Panel-wide defaults every screen reads rather than hardcoding.
            config([
                'gamemgr.date_format' => $s['date_format'] ?? 'M j, Y',
                'gamemgr.time_format' => $s['time_format'] ?? 'g:i A',
                'gamemgr.rows_per_page' => (int) ($s['rows_per_page'] ?? 10),
                'gamemgr.require_2fa' => ($s['require_2fa'] ?? '0') === '1',
                'gamemgr.force_password_days' => (int) ($s['force_password_days'] ?? 0),
                'gamemgr.metric_history_days' => (int) ($s['metric_history_days'] ?? 30),
                'gamemgr.audit_log_days' => (int) ($s['audit_log_days'] ?? 180),
                'gamemgr.default_memory' => (int) ($s['default_memory'] ?? 2048),
                'gamemgr.default_disk' => (int) ($s['default_disk'] ?? 10240),
                'gamemgr.default_cpu' => (int) ($s['default_cpu'] ?? 100),
                'gamemgr.allow_client_server_create' => ($s['allow_client_server_create'] ?? '0') === '1',
                // Node transport, overridable without touching .env.
                'node.offline_after' => (int) ($s['node_offline_after'] ?? config('node.offline_after', 120)),
            ]);

            // Connection names. The API token is deliberately NOT overlaid: it
            // is decrypted on demand by DnsConfig, so the plaintext never sits
            // in the config array waiting for a dump or a stack trace.
            config([
                'domains.enabled' => ($s['domains_enabled'] ?? null) === null
                    ? (bool) config('domains.enabled', false)
                    : $s['domains_enabled'] === '1',
                'domains.provider' => $s['domains_provider'] ?? config('domains.provider', 'null'),
                'domains.zone' => $s['domains_zone'] ?? config('domains.zone', ''),
            ]);

            // A node daemon that is unreachable can be answered with synthetic
            // data instead of an error. Useful for demos, dangerous in
            // production, so it is a deliberate switch rather than a fallback.
            if (isset($s['node_fake'])) {
                config(['node.fake' => $s['node_fake'] === '1']);
            }

            // DB-driven SMTP for notifications.
            if (! empty($s['smtp_host'])) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $s['smtp_host'],
                    'mail.mailers.smtp.port' => (int) ($s['smtp_port'] ?: 587),
                    'mail.mailers.smtp.username' => $s['smtp_username'] ?? null,
                    'mail.mailers.smtp.password' => $s['smtp_password'] ?? null,
                    'mail.from.address' => $s['mail_from'] ?: ('gamemgr@'.parse_url((string) config('app.url'), PHP_URL_HOST)),
                    'mail.from.name' => $s['brand_name'] ?? config('brand.name'),
                ]);
            }
        } catch (\Throwable $e) {
            // DB not ready (e.g. during first install); fall back to config.
        }
    }
}
