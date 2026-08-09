<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Validates this install against scriptgain.com.
 *
 * Same wire contract as the other -MGR products, deliberately: one vendor
 * endpoint serves all of them and there is one signing key to rotate rather
 * than one per product.
 *
 * States returned by status():
 *   valid       licence active and the response signature verified
 *   invalid     the vendor says no: expired, suspended, revoked, unknown key
 *   grace       vendor unreachable, but the last good check is inside the window
 *   unverified  unreachable past the grace window, or a signature that failed
 *   unlicensed  no key entered, which is a free install and perfectly fine
 *
 * This never throws to callers and never hard locks the panel. GameMGR is free
 * to run; a licensing problem lowers the ceilings and shows a banner. It does
 * not stop a server, block a login, or prevent a backup, because a licensing
 * problem must never become an outage for somebody's players.
 */
class LicenceClient
{
    /** Cached, throttled status for the whole app. */
    public static function status(): array
    {
        // Cached rather than checked per request: this is consulted by every
        // gate on every page, and an HTTP call to the vendor in that path would
        // put scriptgain.com in the critical path of the panel rendering.
        return Cache::remember(
            'licence.status',
            now()->addMinutes((int) config('editions.check_every_minutes', 720)),
            fn () => static::check()
        );
    }

    /** Force a fresh online check, for the Re-check button. */
    public static function refresh(): array
    {
        Cache::forget('licence.status');
        $status = static::check();
        Cache::put('licence.status', $status, now()->addMinutes((int) config('editions.check_every_minutes', 720)));

        return $status;
    }

    public static function key(): ?string
    {
        return Setting::secret('licence_key');
    }

    public static function setKey(?string $key): void
    {
        Setting::putSecret('licence_key', $key ?: null);
        Cache::forget('licence.status');
    }

    /** A stable per-install fingerprint, so the vendor can count activations. */
    public static function deviceId(): string
    {
        $id = Setting::get('licence_device_id');
        if (! $id) {
            $id = (string) Str::uuid();
            Setting::put('licence_device_id', $id);
        }

        return $id;
    }

    protected static function check(): array
    {
        $key = static::key();
        if (! $key) {
            // Not an error. GameMGR is free, and an install with no key is a
            // free install rather than a broken one.
            return static::result('unlicensed', null, 'No licence key, so this install is on the free edition.');
        }

        $endpoint = rtrim((string) config('editions.endpoint'), '/');
        $nonce = bin2hex(random_bytes(16));

        try {
            $response = Http::timeout(8)->acceptJson()->asJson()->post($endpoint.'/validate', [
                'key' => $key,
                'product' => config('editions.product'),
                'device' => static::deviceId(),
                'hostname' => gethostname() ?: parse_url((string) config('app.url'), PHP_URL_HOST),
                // Single-use challenge, signed back into the response. This is
                // what stops a captured "valid" being replayed from a static
                // file or reused on a second install.
                'nonce' => $nonce,
            ]);
        } catch (\Throwable $e) {
            return static::offlineFallback('Could not reach the licence server: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return static::offlineFallback('The licence server answered HTTP '.$response->status().'.');
        }

        $body = $response->json();
        $payload = $body['response'] ?? null;
        $signature = $body['signature'] ?? null;

        if (! is_array($payload) || ! is_string($signature)) {
            return static::offlineFallback('The licence server sent something this install could not read.');
        }

        if (! static::verifySignature($payload, $signature)) {
            // A response that will not verify is untrusted, not valid.
            return static::result('unverified', $payload, 'The licence response failed signature verification.');
        }

        // A correctly signed response is not enough: it also has to be THIS
        // response. Without these checks one captured payload validates forever.
        if ($why = static::freshnessProblem($payload, $nonce)) {
            return static::result('unverified', $payload, $why);
        }

        // The vendor already refuses an expired key, but a payload this install
        // did not re-check itself is a payload it is taking on trust.
        if (! empty($payload['expires_at'])) {
            try {
                if (Carbon::parse($payload['expires_at'])->isPast()) {
                    return static::result('invalid', $payload, 'That licence expired on '.$payload['expires_at'].'.');
                }
            } catch (\Throwable $e) {
                return static::result('unverified', $payload, 'That licence carries an unreadable expiry date.');
            }
        }

        if (! empty($payload['valid'])) {
            Setting::put('licence_last_valid_at', now()->toIso8601String());
            Setting::put('licence_last_response', json_encode($payload));

            $message = 'Licence active.';
            if (empty($payload['edition'])) {
                $message = 'Licence active, but it does not say which edition it is for, so this install is running as '
                    .config('editions.licensed_default', 'basic').'.';
            }

            return static::result('valid', $payload, $message);
        }

        return static::result('invalid', $payload, 'That licence is not valid: '.($payload['reason'] ?? 'no reason given').'.');
    }

    /**
     * Returns why a verified payload is not a fresh answer to the challenge just
     * sent, or null when it checks out.
     *
     * Two guards, because they fail differently. The nonce proves the vendor
     * minted this response for this request; the issue time bounds how long any
     * single response stays usable, which also covers a replay captured inside
     * the same window.
     */
    protected static function freshnessProblem(array $payload, string $nonce): ?string
    {
        $echoed = (string) ($payload['nonce'] ?? '');
        if ($echoed === '' || ! hash_equals($nonce, $echoed)) {
            return 'The licence response did not answer this check. That is what a replayed or cached response looks like.';
        }

        $issued = $payload['issued_at'] ?? null;
        if (! $issued) {
            return 'The licence response carries no issue time.';
        }

        try {
            $at = Carbon::parse($issued);
        } catch (\Throwable $e) {
            return 'The licence response carries an unreadable issue time.';
        }

        $maxAge = (int) config('editions.max_age_minutes', 10);
        $skew = (int) config('editions.clock_skew_minutes', 5);

        if ($at->lt(now()->subMinutes($maxAge))) {
            return 'The licence response is stale, issued '.$issued.'.';
        }
        if ($at->gt(now()->addMinutes($skew))) {
            return 'The licence response is dated in the future, issued '.$issued.'.';
        }

        return null;
    }

    /**
     * Fall back to the last known good result while inside the grace window.
     *
     * Generous on purpose. The customer has already paid, and an outage at the
     * vendor must not turn into a downgrade for them.
     */
    protected static function offlineFallback(string $why): array
    {
        $lastAt = Setting::get('licence_last_valid_at');
        $graceDays = (int) config('editions.grace_days', 14);

        if ($lastAt && Carbon::parse($lastAt)->addDays($graceDays)->isFuture()) {
            $payload = json_decode((string) Setting::get('licence_last_response'), true) ?: null;

            return static::result('grace', $payload, 'Cannot reach the licence server, so this is running on the last good check. '.$why);
        }

        return static::result('unverified', null, 'Cannot verify this licence and the grace period has ended. '.$why);
    }

    /**
     * Verify an RSA-SHA256 signature over the canonical JSON of the payload.
     * Mirrors scriptgain's signer exactly: top-level ksort, then json_encode
     * with unescaped slashes.
     */
    public static function verifySignature(array $payload, string $signatureB64): bool
    {
        $public = (string) config('editions.public_key');
        $signature = base64_decode($signatureB64, true);

        if ($signature === false || $public === '') {
            return false;
        }

        return openssl_verify(static::canonical($payload), $signature, $public, OPENSSL_ALGO_SHA256) === 1;
    }

    /** The exact byte string scriptgain signs. */
    public static function canonical(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    protected static function result(string $state, ?array $licence, string $message): array
    {
        return [
            'state' => $state,
            // grace counts as ok: a customer inside the window has paid and the
            // vendor being unreachable is not their problem.
            'ok' => in_array($state, ['valid', 'grace'], true),
            'licence' => $licence,
            'message' => $message,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
