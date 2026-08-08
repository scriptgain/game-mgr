<?php

namespace App\Services\Dns;

use App\Models\Setting;

/**
 * Where the domains feature gets its settings, and the one place a driver is
 * chosen.
 *
 * Everything except the token is overlaid onto config at boot by
 * AppServiceProvider, the same way every other DB-driven setting works. The
 * token is not: it is decrypted only when a call is about to be made, so it
 * never sits in the config array for a dump, a debug page or a stack trace to
 * pick up.
 */
final class DnsConfig
{
    public const PROVIDERS = [
        'null' => 'None (Show Me The Records)',
        'cloudflare' => 'Cloudflare',
    ];

    public static function enabled(): bool
    {
        return (bool) config('domains.enabled', false);
    }

    public static function providerName(): string
    {
        $name = (string) config('domains.provider', 'null');

        return array_key_exists($name, self::PROVIDERS) ? $name : 'null';
    }

    /** The suffix names are built under, for example play.scriptgain.com. */
    public static function zone(): string
    {
        return mb_strtolower(trim((string) config('domains.zone', ''), " \t\n\r\0\x0B."));
    }

    public static function token(): ?string
    {
        $stored = Setting::secret('domains_api_token');
        if (filled($stored)) {
            return $stored;
        }

        $fallback = (string) config('domains.api_token', '');

        return $fallback !== '' ? $fallback : null;
    }

    public static function ttl(): int
    {
        return max(30, (int) config('domains.ttl', 120));
    }

    /** Does a name exist for servers to be given? */
    public static function active(): bool
    {
        return self::enabled() && self::zone() !== '';
    }

    /** Is there enough here to actually make a call? */
    public static function ready(): bool
    {
        if (! self::active()) {
            return false;
        }

        return self::providerName() !== 'cloudflare' || filled(self::token());
    }

    public static function provider(): Provider
    {
        return match (self::providerName()) {
            'cloudflare' => new CloudflareProvider(
                self::token() ?? throw new DnsException('Cloudflare is selected but no API token is saved.'),
                (float) config('domains.timeout', 5),
                (float) config('domains.connect_timeout', 3),
            ),
            default => new NullProvider(),
        };
    }
}
