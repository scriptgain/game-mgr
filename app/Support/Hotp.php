<?php

namespace App\Support;

/**
 * The truncation step shared by every HOTP-derived code (RFC 4226 section 5.4).
 *
 * Two callers want this and they disagree on everything else: Totp decodes a
 * Base32 secret and renders six digits, SteamGuard decodes a Base64 one and
 * renders five characters from its own alphabet. Only the HMAC and the dynamic
 * truncation are genuinely common, so only that lives here.
 */
class Hotp
{
    /**
     * The 31-bit dynamic truncation of HMAC-SHA1(key, counter).
     *
     * The counter is the 8-byte big-endian block RFC 4226 specifies. PHP's
     * pack('N') is 4 bytes, hence the four leading nulls: every value either
     * caller passes is a Unix time divided by 30, which will not need the high
     * word for another few thousand years.
     */
    public static function truncate(string $key, int $counter): int
    {
        $hash = hash_hmac('sha1', "\0\0\0\0".pack('N', $counter), $key, true);
        $offset = ord($hash[19]) & 0xf;

        return ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
    }
}
