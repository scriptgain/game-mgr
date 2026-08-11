<?php

namespace Tests\Unit;

use App\Support\SteamGuard;
use App\Support\Totp;
use PHPUnit\Framework\TestCase;

/**
 * Steam Guard code generation.
 *
 * The vectors below were produced from an independent implementation of the
 * published algorithm, so they pin this one against silent drift. They do not
 * prove Steam itself accepts them: only a real login does that, and that check
 * belongs on a node with a real account, not in a unit test.
 */
class SteamGuardTest extends TestCase
{
    /** 20 bytes, 0x00 through 0x13, Base64. A real shared_secret is the same shape. */
    private const SECRET = 'AAECAwQFBgcICQoLDA0ODxAREhM=';

    public function test_it_matches_known_vectors(): void
    {
        $this->assertSame('YFG53', SteamGuard::code(self::SECRET, 0));
        $this->assertSame('2GJY4', SteamGuard::code(self::SECRET, 1600000000));
    }

    public function test_the_code_holds_for_a_thirty_second_window(): void
    {
        // 1600000020 opens a window; everything inside it is one code.
        $this->assertSame(
            SteamGuard::code(self::SECRET, 1600000020),
            SteamGuard::code(self::SECRET, 1600000049)
        );
        $this->assertNotSame(
            SteamGuard::code(self::SECRET, 1600000019),
            SteamGuard::code(self::SECRET, 1600000020)
        );
    }

    public function test_it_only_ever_uses_the_steam_alphabet(): void
    {
        for ($t = 0; $t < 60000; $t += 997) {
            $code = SteamGuard::code(self::SECRET, $t);
            $this->assertSame(5, strlen($code));
            $this->assertMatchesRegularExpression('/\A[23456789BCDFGHJKMNPQRTVWXY]{5}\z/', $code);
        }
    }

    /**
     * An unusable secret must produce nothing at all.
     *
     * This is the case that matters most. A wrong code does not fail the login
     * cleanly: it consumes an attempt and can put the account into a rate limit
     * that reads exactly like a bad password, so "no code" has to stay
     * distinguishable from "a code that happens to be wrong".
     */
    public function test_an_unusable_secret_yields_no_code(): void
    {
        foreach ([null, '', '   ', 'not base64 at all!', 'c2hvcnQ=', 'JBSWY3DPEHPK3PXP'] as $bad) {
            $this->assertSame('', SteamGuard::code($bad), var_export($bad, true).' should yield no code');
            $this->assertFalse(SteamGuard::valid($bad));
        }

        $this->assertTrue(SteamGuard::valid(self::SECRET));
    }

    /**
     * Totp shares the HMAC and truncation with SteamGuard now. This is here so
     * that extraction cannot regress two factor login for every panel user,
     * which would be a considerably worse outcome than a Deadlock server that
     * will not install.
     */
    public function test_extracting_the_shared_core_did_not_change_totp(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->assertTrue(Totp::verify($secret, $this->currentTotp($secret)));
        $this->assertFalse(Totp::verify($secret, '000000'));
    }

    /** The same six digits Totp should produce, computed the long way round. */
    private function currentTotp(string $base32): string
    {
        $bits = '';
        foreach (str_split($base32) as $c) {
            $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $c)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }

        $hash = hash_hmac('sha1', "\0\0\0\0".pack('N', intdiv(time(), 30)), $key, true);
        $offset = ord($hash[19]) & 0xf;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
