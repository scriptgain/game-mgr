<?php

namespace App\Support;

/**
 * Steam Guard mobile authenticator codes.
 *
 * Steam uses the HOTP construction with a 30 second time step, and then throws
 * away the part everyone recognises: instead of six decimal digits it renders
 * five characters from its own 26 character alphabet, which deliberately omits
 * the letters and digits that are easy to misread aloud.
 *
 * The seed is the `shared_secret` out of a mobile authenticator export, which
 * is Base64, NOT the Base32 an ordinary authenticator app uses. Feeding a
 * Base32 string in here produces a code that is the right shape and always
 * wrong, so the decode is strict and an unusable secret yields no code at all.
 */
class SteamGuard
{
    /**
     * 26 characters. No A, E, I, L, O, S, U, Z, 0 or 1: Steam reads these codes
     * out over voice support, and those are the pairs people confuse.
     */
    private const ALPHABET = '23456789BCDFGHJKMNPQRTVWXY';

    private const PERIOD = 30;

    private const LENGTH = 5;

    /**
     * The code for a shared secret at a given moment.
     *
     * Returns an empty string for an empty or malformed secret. That is the
     * contract the callers depend on: no code must mean "do not send one",
     * never "send this wrong one", because a wrong code does not fail the login
     * cleanly, it consumes an attempt and can put the account into a rate limit
     * that looks exactly like a bad password.
     */
    public static function code(?string $sharedSecret, ?int $at = null): string
    {
        $key = self::decode($sharedSecret);
        if ($key === '') {
            return '';
        }

        $value = Hotp::truncate($key, intdiv($at ?? time(), self::PERIOD));

        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[$value % strlen(self::ALPHABET)];
            $value = intdiv($value, strlen(self::ALPHABET));
        }

        return $code;
    }

    /** Whether a secret is usable, for form validation rather than for guessing. */
    public static function valid(?string $sharedSecret): bool
    {
        return self::decode($sharedSecret) !== '';
    }

    /**
     * Strict Base64. A real shared secret decodes to 20 bytes, the SHA1 block
     * size, and anything else is a paste error worth rejecting up front rather
     * than at 3am when an install cannot log in.
     */
    private static function decode(?string $sharedSecret): string
    {
        $sharedSecret = trim((string) $sharedSecret);
        if ($sharedSecret === '') {
            return '';
        }

        $key = base64_decode($sharedSecret, true);

        return $key !== false && strlen($key) === 20 ? $key : '';
    }
}
