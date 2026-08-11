<?php

namespace App\Support;

/**
 * Minimal TOTP (RFC 6238), no external dependency.
 * 6 digits, 30s period, SHA1, compatible with Google Authenticator / Authy.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a new Base32 secret. */
    public static function secret(int $bytes = 20): string
    {
        return self::base32encode(random_bytes($bytes));
    }

    /** Verify a submitted code against the secret (±1 window for clock drift). */
    public static function verify(string $base32Secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $key = self::base32decode($base32Secret);
        if ($key === '') {
            return false;
        }
        $counter = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($key, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /** The otpauth:// URI to add to an authenticator app. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $account);

        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    private static function hotp(string $key, int $counter): string
    {
        // The HMAC and dynamic truncation are shared with SteamGuard, which
        // renders the same value into a different alphabet entirely.
        $val = Hotp::truncate($key, $counter);

        return str_pad((string) ($val % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    private static function base32decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
