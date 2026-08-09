<?php

namespace App\Services;

use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Support\Edition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * What this install tells scriptgain.com about itself.
 *
 * The panel is free and self-hosted, so this is the only way to know how many
 * installs exist, which runtimes are worth maintaining, and whether anybody is
 * still on a version with a bug in it. That makes it worth doing, and worth
 * doing in a way somebody can audit rather than discover.
 *
 * Four rules, all of them load-bearing:
 *
 *   Counts, never names. How many servers, never which games, never a hostname,
 *   never an address, never an email. The payload below is the whole of what is
 *   ever sent and adding to it is a deliberate act.
 *
 *   The exact JSON that was last sent is kept and shown on the settings page.
 *   Telemetry people can read is telemetry people accept.
 *
 *   Failure is silent. Whether scriptgain.com is reachable is not the
 *   operator's problem and must never surface as an error in their panel.
 *
 *   Off means off. No heartbeat, no "just the version", nothing.
 */
class Telemetry
{
    public const ENDPOINT = 'https://scriptgain.com/v1/telemetry';

    /** How often a send is worth making. Daily is plenty for counts. */
    private const EVERY_HOURS = 24;

    public static function enabled(): bool
    {
        // Defaults to on, and the first run says so plainly rather than
        // burying it. An install that has never been asked is not consenting,
        // which is why asked is tracked separately.
        return Setting::get('telemetry_enabled', '1') === '1';
    }

    public static function acknowledged(): bool
    {
        return Setting::get('telemetry_asked') === '1';
    }

    public static function acknowledge(): void
    {
        Setting::put('telemetry_asked', '1');
    }

    public static function setEnabled(bool $on): void
    {
        Setting::put('telemetry_enabled', $on ? '1' : '0');
        static::acknowledge();
    }

    /** A stable id for this install, so two sends are known to be one panel. */
    public static function installId(): string
    {
        $id = Setting::get('telemetry_id');
        if (! $id) {
            $id = (string) Str::uuid();
            Setting::put('telemetry_id', $id);
        }

        return $id;
    }

    /**
     * The whole of what is ever sent.
     *
     * Read it as the contract it is. Anything added here is something a
     * customer did not agree to when they last looked at the settings page.
     */
    public static function payload(): array
    {
        return [
            'id' => static::installId(),
            'product' => 'gamemgr',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unknown',
            'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'os' => PHP_OS_FAMILY,
            'edition' => Edition::current(),
            // Counts. Never which games, never which nodes, never who.
            'nodes' => Node::count(),
            'servers' => Server::count(),
            'runtimes' => Node::pluck('runtimes')
                ->flatten()->filter()->unique()->sort()->values()->all(),
            'at' => now()->toIso8601String(),
        ];
    }

    /** What went last time, exactly, for the settings page to show. */
    public static function lastSent(): ?array
    {
        $raw = Setting::get('telemetry_last');

        return $raw ? json_decode($raw, true) : null;
    }

    public static function lastSentAt(): ?string
    {
        return Setting::get('telemetry_last_at');
    }

    /**
     * Send, if it is on and enough time has passed.
     *
     * $force is for the button on the settings page, so somebody can see
     * exactly what goes rather than waiting a day to find out.
     */
    public static function send(bool $force = false): bool
    {
        if (! static::enabled()) {
            return false;
        }

        if (! $force && ($last = static::lastSentAt())) {
            if (now()->diffInHours(\Illuminate\Support\Carbon::parse($last)) < self::EVERY_HOURS) {
                return false;
            }
        }

        $payload = static::payload();

        try {
            $response = Http::timeout(8)->acceptJson()->asJson()->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            // Silent on purpose. Whether the vendor is reachable is none of the
            // operator's business and must not appear in their panel.
            return false;
        }

        // Recorded whatever the answer, so the settings page shows what was
        // sent rather than only what was accepted.
        Setting::put('telemetry_last', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Setting::put('telemetry_last_at', now()->toIso8601String());

        return $response->successful();
    }
}
