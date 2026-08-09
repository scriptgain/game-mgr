<?php

namespace Tests\Feature;

use App\Services\Mods\Catalogue\CatalogueFile;
use App\Services\Mods\Sources\CurseForgeSource;
use App\Services\Mods\Sources\HangarSource;
use App\Services\Mods\Sources\SpigetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The checksum, proved by breaking it on purpose.
 *
 * Every other test here asks whether an install works. This one asks whether it
 * FAILS when it should, which is the only question that matters for the one
 * guarantee the mod installer makes: a file that does not match what its author
 * published never reaches the node.
 *
 * It is easy to write a verifier that is never exercised. A hash compared
 * against itself, a null that skips the check, a source quietly added to the
 * unverified list. So the checks are made to fail deliberately: wrong bytes, no
 * published hash at all, and a URL on a host the source does not own.
 */
class ModChecksumTest extends TestCase
{
    use RefreshDatabase;

    private const BYTES = 'this is definitely not the jar that was published';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(fn () => Http::response(self::BYTES, 200));
    }

    /** The whole point. Wrong bytes, real hash, nothing installed. */
    public function test_a_file_that_does_not_match_its_published_hash_is_thrown_away(): void
    {
        $file = new CatalogueFile(
            url: 'https://hangarcdn.papermc.io/plugins/Fake/Fake/versions/1.0/PAPER/fake.jar',
            filename: 'fake.jar',
            size: 0,
            // The hash of something else entirely.
            checksum: hash('sha256', 'the real jar'),
            checksumAlgo: 'sha256',
        );

        $result = app(HangarSource::class)->download($file, 64 * 1024 * 1024);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('checksum', $result['error']);
        // And it did not leave the rejected download lying around for anything
        // else to pick up.
        $this->assertArrayNotHasKey('path', $result);
    }

    /** The same bytes, with the hash that actually describes them, sail through. */
    public function test_a_file_that_matches_is_accepted(): void
    {
        $file = new CatalogueFile(
            url: 'https://hangarcdn.papermc.io/plugins/Real/Real/versions/1.0/PAPER/real.jar',
            filename: 'real.jar',
            checksum: hash('sha256', self::BYTES),
            checksumAlgo: 'sha256',
        );

        $result = app(HangarSource::class)->download($file, 64 * 1024 * 1024);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame(strlen(self::BYTES), $result['bytes']);
        @unlink($result['path']);
    }

    /**
     * No published hash is a refusal, not a shrug, for every source except the
     * one that has declared it never publishes any.
     */
    public function test_a_source_that_published_no_hash_is_refused(): void
    {
        $file = new CatalogueFile(
            url: 'https://edge.forgecdn.net/files/1/2/thing.jar',
            filename: 'thing.jar',
        );

        $result = app(CurseForgeSource::class)->download($file, 64 * 1024 * 1024);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('no checksum', $result['error']);
    }

    /** SpigotMC is the declared exception, and only SpigotMC. */
    public function test_spigot_alone_may_install_something_it_cannot_verify(): void
    {
        $file = new CatalogueFile(
            url: 'https://api.spiget.org/v2/resources/1/download',
            filename: 'plugin.jar',
        );

        $result = app(SpigetSource::class)->download($file, 64 * 1024 * 1024);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        @unlink($result['path']);
    }

    /**
     * A correct hash on a host the source does not own is still a refusal. The
     * allowlist is not a formality: a catalogue entry is user-submitted content
     * and a URL inside one is not a reason to fetch anything.
     */
    public function test_a_file_hosted_somewhere_else_is_refused_even_with_a_good_hash(): void
    {
        $file = new CatalogueFile(
            url: 'https://evil.example.com/totally-legit.jar',
            filename: 'totally-legit.jar',
            checksum: hash('sha256', self::BYTES),
            checksumAlgo: 'sha256',
        );

        $result = app(HangarSource::class)->download($file, 64 * 1024 * 1024);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not hosted by', $result['error']);
        Http::assertNothingSent();
    }

    /** Too big is refused before a byte is fetched, on the declared size. */
    public function test_an_oversized_file_is_refused_before_it_is_downloaded(): void
    {
        $file = new CatalogueFile(
            url: 'https://hangarcdn.papermc.io/plugins/Big/Big/versions/1.0/PAPER/big.jar',
            filename: 'big.jar',
            size: 500 * 1024 * 1024,
            checksum: hash('sha256', self::BYTES),
            checksumAlgo: 'sha256',
        );

        $result = app(HangarSource::class)->download($file, 64 * 1024 * 1024);

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }
}
