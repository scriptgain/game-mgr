<?php

namespace App\Services\Mods\Sources;

use App\Services\Mods\Catalogue\CatalogueFile;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The parts of a catalogue client that are the same wherever it points.
 *
 * App\Services\Mods\Modrinth wrote all of this once, against a live API, and it
 * is not being rewritten or moved: it works and it is verified. This is the
 * same behaviour for the sources that came after, so that adding a fifth
 * catalogue is a search method and a version method rather than another
 * hand-rolled download with its own idea of what a size limit means.
 *
 * What lives here, and why each one is not optional:
 *
 *   A cache with a stale fallback. A rate limit or a blip should serve the last
 *   good answer, not an empty screen.
 *
 *   A degraded marker. One failure stops the panel hammering a service that is
 *   already struggling, and lets the page say "the catalogue did not answer"
 *   rather than "there are no results", which mean very different things.
 *
 *   A host allowlist per source. A project page is user-submitted content and a
 *   URL inside one is not a reason to fetch anything. Each source says which
 *   hosts are really its own.
 *
 *   The size ceiling checked twice, before and after: the declared size is the
 *   useful check and the arrived size is the one that cannot be lied to.
 *
 *   Checksum verification against whatever the source published, and a refusal
 *   when it published nothing, unless the source has declared that it never
 *   does. That exception exists for exactly one catalogue and is stated out
 *   loud there rather than assumed here.
 */
abstract class HttpSource
{
    /** How long a failure suppresses further calls. */
    private const DOWN_SECONDS = 60;

    abstract public function key(): string;

    abstract public function label(): string;

    /** Hosts this source really serves its own files from. */
    abstract protected function trustedHost(string $url): bool;

    /** The API root, from config. */
    abstract protected function baseUrl(): string;

    /**
     * May a file with no published checksum still be installed?
     *
     * False everywhere except Spiget, which publishes none at all. Saying yes
     * is a decision about what the panel promises, so it is a method a source
     * has to override deliberately rather than a config flag somebody flips.
     */
    protected function allowsUnverified(): bool
    {
        return false;
    }

    public function degraded(): bool
    {
        return (bool) Cache::get($this->cacheKey('down'));
    }

    protected function userAgent(): string
    {
        $contact = (string) config('mods.contact', config('mods.modrinth.contact', 'support@scriptgain.com'));

        return 'GameMGR/'.trim((string) @file_get_contents(base_path('VERSION'))).' ('.$contact.')';
    }

    protected function seconds(): float
    {
        return max(1.0, (float) config('mods.'.$this->key().'.timeout', 5));
    }

    protected function downloadSeconds(): float
    {
        return max(5.0, (float) config('mods.'.$this->key().'.download_timeout', 120));
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => $this->userAgent()])
            ->acceptJson()
            ->connectTimeout(min(5.0, $this->seconds()))
            ->timeout($this->seconds());
    }

    /**
     * A cached GET that answers null rather than throwing.
     *
     * @return array<mixed>|null
     */
    protected function fetch(string $key, string $path, array $query = [], ?int $ttl = null): ?array
    {
        $cacheKey = $this->cacheKey($key);
        $ttl ??= (int) (config('mods.'.$this->key().'.ttl.'.explode(':', $key)[0]) ?? 600);

        if (($hit = Cache::get($cacheKey)) !== null) {
            return $hit === 'null' ? null : $hit;
        }

        if ($this->degraded()) {
            return Cache::get($cacheKey.':stale');
        }

        try {
            $response = $this->client()->get(rtrim($this->baseUrl(), '/').$path, $query);

            if (! $response->successful()) {
                // A 404 is an answer, not a failure: the project is not there.
                // Marking the whole source down for it would hide a working
                // catalogue behind one bad slug.
                if ($response->status() === 404) {
                    Cache::put($cacheKey, 'null', 60);

                    return null;
                }

                $this->markDown();

                return Cache::get($cacheKey.':stale');
            }

            $body = $response->json();
        } catch (Throwable) {
            $this->markDown();

            return Cache::get($cacheKey.':stale');
        }

        if (! is_array($body)) {
            return null;
        }

        Cache::put($cacheKey, $body, $ttl);
        // Kept far longer than the fresh copy purely to have something to show
        // when the source stops answering.
        Cache::put($cacheKey.':stale', $body, 86400);

        return $body;
    }

    protected function markDown(): void
    {
        Cache::put($this->cacheKey('down'), true, self::DOWN_SECONDS);
    }

    protected function cacheKey(string $key): string
    {
        return 'mods:'.$this->key().':'.$key;
    }

    /**
     * Fetch to a temporary file, refusing anything that fails a check.
     *
     * @return array{ok:bool,error?:string,path?:string,bytes?:int}
     */
    public function download(CatalogueFile $file, int $maxBytes): array
    {
        if ($file->size > 0 && $file->size > $maxBytes) {
            return ['ok' => false, 'error' => $this->tooLarge($file->size, $maxBytes)];
        }

        if (! $file->verified() && ! $this->allowsUnverified()) {
            return ['ok' => false, 'error' => $this->label().' published no checksum for that file, so it was not installed.'];
        }

        if (! $this->trustedHost($file->url)) {
            return ['ok' => false, 'error' => 'That file is not hosted by '.$this->label().', so it was not downloaded.'];
        }

        $path = tempnam(sys_get_temp_dir(), 'gamemgr-mod-');

        if ($path === false) {
            return ['ok' => false, 'error' => 'The panel could not open a temporary file for the download.'];
        }

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->connectTimeout(min(5.0, $this->downloadSeconds()))
                ->timeout($this->downloadSeconds())
                ->sink($path)
                ->get($file->url);

            if (! $response->successful()) {
                @unlink($path);

                return ['ok' => false, 'error' => $this->label().' refused the download (HTTP '.$response->status().').'];
            }
        } catch (Throwable) {
            @unlink($path);

            return ['ok' => false, 'error' => 'The download from '.$this->label().' did not finish.'];
        }

        clearstatcache(true, $path);
        $bytes = (int) filesize($path);

        if ($bytes <= 0) {
            @unlink($path);

            return ['ok' => false, 'error' => $this->label().' returned an empty file.'];
        }

        if ($bytes > $maxBytes) {
            @unlink($path);

            return ['ok' => false, 'error' => $this->tooLarge($bytes, $maxBytes)];
        }

        if ($file->verified()) {
            $actual = hash_file((string) $file->checksumAlgo, $path);

            if (! is_string($actual) || ! hash_equals(strtolower((string) $file->checksum), strtolower($actual))) {
                @unlink($path);

                return ['ok' => false, 'error' => 'The download did not match the '.strtoupper((string) $file->checksumAlgo).
                    ' checksum '.$this->label().' published, so it was thrown away rather than installed.'];
            }
        }

        return ['ok' => true, 'path' => $path, 'bytes' => $bytes];
    }

    protected function tooLarge(int $bytes, int $maxBytes): string
    {
        $mb = fn (int $b) => number_format($b / 1048576, 1).' MiB';

        return 'That file is '.$mb($bytes).' and this panel installs up to '.$mb($maxBytes).
            ' from a catalogue. Upload it in the file manager instead.';
    }
}
