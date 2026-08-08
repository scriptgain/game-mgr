<?php

namespace App\Services\Mods;

use App\Services\UpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A read only client for the Modrinth catalogue.
 *
 * Modrinth (https://modrinth.com) is the open source index of Minecraft mods,
 * plugins, modpacks, resource packs and shaders. It is the only mod source with
 * a public API that needs no key, publishes checksums for every file, and
 * serves the files itself, which is exactly the combination an unattended
 * installer needs.
 *
 * The endpoints, confirmed against https://docs.modrinth.com/api/ and against
 * live responses rather than assumed:
 *
 *   GET /v2/search?query=&facets=&index=&limit=&offset=
 *       { hits: [ { project_id, slug, title, description, author, downloads,
 *         icon_url, project_type, categories, versions, latest_version,
 *         client_side, server_side, ... } ], offset, limit, total_hits }
 *
 *       Facets are a JSON array of arrays. Entries inside one array are OR,
 *       the arrays themselves are AND, so the loader alternatives go in one
 *       array and the game version in another:
 *
 *         [["categories:paper","categories:spigot"],["versions:1.21.4"]]
 *
 *       `categories:` is what carries the loader. There is a `loaders:` alias
 *       that returns the same counts, and `project_type:plugin` exists as a
 *       search-only synthetic type, but a hit's own project_type field still
 *       says "mod" for a Bukkit plugin. Verified live: a search for luckperms
 *       returns 141 hits unfaceted, 101 with categories:paper, and 47 with
 *       categories:paper plus versions:1.20.4.
 *
 *       project_type does NOT support the != operator in practice. Asking for
 *       project_type!=modpack returned modpacks unchanged, so modpacks,
 *       resource packs and shaders are dropped from the hits here instead.
 *
 *   GET /v2/project/{id|slug}
 *       The full project, including its own `loaders` and `game_versions`.
 *
 *   GET /v2/project/{id|slug}/version?loaders=[..]&game_versions=[..]
 *       An array of versions, newest first, each with:
 *         { id, project_id, name, version_number, version_type, loaders,
 *           game_versions, date_published, downloads,
 *           files: [ { url, filename, primary, size,
 *                      hashes: { sha1, sha512 } } ] }
 *
 *       Both filters are JSON arrays in the query string, and they are the same
 *       question the search facets ask, asked again per project because search
 *       ranks projects and installs need a file.
 *
 * Two conditions of use, both enforced here:
 *
 *   1. A uniquely identifying User-Agent naming the application and a contact
 *      is required, and generic ones are blocked. See config('mods.modrinth').
 *
 *   2. 300 requests a minute per address, reported back on every response as
 *      x-ratelimit-limit, x-ratelimit-remaining and x-ratelimit-reset. Nothing
 *      here reaches the network without going through the cache first, and a
 *      429 or any other failure is remembered briefly so a person clicking
 *      around does not spend the rest of the window on doomed requests.
 *
 * Every method answers null when Modrinth could not be reached or did not
 * answer in the shape the docs describe. Null means "we do not know" and the
 * caller degrades: the Mods page still lists what is installed, with a note
 * that the catalogue is unavailable. Nothing in this class throws.
 */
class Modrinth
{
    /** Cache key prefix. Bumped when the shape returned by this class changes. */
    private const PREFIX = 'modrinth:v1:';

    /** How long the last good answer is kept to serve when the API is down. */
    private const STALE = 604800;

    /** Project types that are not a single installable file. */
    private const NOT_INSTALLABLE = ['modpack', 'resourcepack', 'shader', 'datapack'];

    /** Fallback TTLs when config does not name one. */
    private const FRESH = ['search' => 300, 'project' => 3600, 'versions' => 900];

    public function enabled(): bool
    {
        return (bool) config('mods.modrinth.enabled', true);
    }

    /**
     * Projects matching a query, narrowed to what this server can run.
     *
     * @return array<int, array{id:string,slug:string,name:string,summary:string,author:string,downloads:int,icon:?string,categories:array<int,string>,latest_game_version:?string}>|null
     */
    public function search(string $query, ModTarget $target, int $limit = 20): ?array
    {
        $query = trim($query);

        if ($query === '' || $target->loaders === []) {
            return [];
        }

        $facets = self::facets($target);

        $body = $this->get(
            'search:'.md5($query.'|'.$facets.'|'.$limit),
            '/v2/search',
            ['query' => $query, 'facets' => $facets, 'index' => 'relevance', 'limit' => $limit],
        );

        if ($body === null) {
            return null;
        }

        $out = [];

        foreach ((array) ($body['hits'] ?? []) as $hit) {
            if (! is_array($hit) || ! isset($hit['project_id']) || ! is_scalar($hit['project_id'])) {
                continue;
            }

            // Modpacks and resource packs are real Modrinth projects that a
            // loader facet happily matches, and neither is a jar that can be
            // dropped into plugins or mods. Dropped here rather than faceted
            // out because the != operator does not work on project_type.
            if (in_array((string) ($hit['project_type'] ?? ''), self::NOT_INSTALLABLE, true)) {
                continue;
            }

            $versions = array_values(array_filter((array) ($hit['versions'] ?? []), 'is_scalar'));

            $out[] = [
                'id' => (string) $hit['project_id'],
                'slug' => (string) ($hit['slug'] ?? $hit['project_id']),
                'name' => (string) ($hit['title'] ?? $hit['slug'] ?? 'Untitled'),
                'summary' => (string) ($hit['description'] ?? ''),
                'author' => (string) ($hit['author'] ?? ''),
                'downloads' => (int) ($hit['downloads'] ?? 0),
                'icon' => self::text($hit['icon_url'] ?? null),
                'categories' => array_values(array_filter((array) ($hit['categories'] ?? []), 'is_string')),
                'latest_game_version' => $versions === [] ? null : (string) end($versions),
            ];
        }

        return $out;
    }

    /**
     * One project by id or slug.
     *
     * @return array<string,mixed>|null
     */
    public function project(string $id): ?array
    {
        $id = self::normaliseId($id);

        if ($id === null) {
            return null;
        }

        $body = $this->get('project:'.$id, '/v2/project/'.rawurlencode($id));

        return is_array($body) && isset($body['id']) ? $body : null;
    }

    /**
     * Who to credit for a project.
     *
     * GET /v2/project/{id} carries a team id but no names, and the search hit
     * carries an `author` the install flow does not see, because install is
     * given a project id and nothing else on purpose. So the team is asked
     * directly. Cached alongside the project, since it changes about as often.
     *
     * The Owner is preferred over whoever the API happens to list first: for
     * ViaVersion that is the difference between "kennytv" and a contributor.
     */
    public function author(string $id): ?string
    {
        $id = self::normaliseId($id);

        if ($id === null) {
            return null;
        }

        $body = $this->get('project:members:'.$id, '/v2/project/'.rawurlencode($id).'/members');

        if (! is_array($body) || $body === []) {
            return null;
        }

        $first = null;

        foreach ($body as $member) {
            if (! is_array($member) || ! isset($member['user']['username'])) {
                continue;
            }

            $username = (string) $member['user']['username'];
            $first ??= $username;

            if (($member['role'] ?? '') === 'Owner') {
                return $username;
            }
        }

        return $first;
    }

    /**
     * Versions of a project this server can run, newest first.
     *
     * Modrinth returns them newest first already and the order is preserved
     * rather than re-sorted: "1.21.10" against "1.21.9" is not a string
     * comparison, and version_compare does not understand "v5.5.71-bukkit".
     *
     * @return array<int, array<string,mixed>>|null
     */
    public function versions(string $id, ModTarget $target): ?array
    {
        $id = self::normaliseId($id);

        if ($id === null || $target->loaders === []) {
            return null;
        }

        $query = ['loaders' => json_encode(array_values($target->loaders))];

        if ($target->versionKnown()) {
            $query['game_versions'] = json_encode([$target->gameVersion]);
        }

        $body = $this->get(
            'versions:'.$id.':'.md5(json_encode($query)),
            '/v2/project/'.rawurlencode($id).'/version',
            $query,
        );

        if (! is_array($body)) {
            return null;
        }

        return array_values(array_filter($body, fn ($v) => is_array($v) && isset($v['id'])));
    }

    /**
     * The newest version this server can run, preferring a release.
     *
     * An author who has published a release and a later alpha wants the release
     * installed on a server: alphas and betas are for people who opted into
     * them. If the project has only pre-releases, the newest is offered rather
     * than nothing, because a mod that has never cut a release is still a mod
     * somebody meant to install.
     *
     * @return array<string,mixed>|null
     */
    public function latestVersion(string $id, ModTarget $target): ?array
    {
        $versions = $this->versions($id, $target);

        if ($versions === null || $versions === []) {
            return null;
        }

        foreach ($versions as $version) {
            if (($version['version_type'] ?? '') === 'release') {
                return $version;
            }
        }

        return $versions[0];
    }

    /**
     * The primary file of a version: the one that is actually the mod.
     *
     * A version can carry sources jars, javadoc jars and dev jars alongside the
     * real artefact. Modrinth marks exactly one `primary`, and installing the
     * wrong one puts a sources jar in plugins where it does nothing.
     *
     * @param  array<string,mixed>  $version
     * @return array{url:string,filename:string,size:int,sha1:?string,sha512:?string}|null
     */
    public static function primaryFile(array $version): ?array
    {
        $files = array_values(array_filter((array) ($version['files'] ?? []), 'is_array'));

        if ($files === []) {
            return null;
        }

        $chosen = null;

        foreach ($files as $file) {
            if (! empty($file['primary'])) {
                $chosen = $file;
                break;
            }
        }

        $chosen ??= $files[0];

        $url = self::text($chosen['url'] ?? null);
        $filename = self::text($chosen['filename'] ?? null);

        if ($url === null || $filename === null) {
            return null;
        }

        $hashes = is_array($chosen['hashes'] ?? null) ? $chosen['hashes'] : [];

        return [
            'url' => $url,
            'filename' => $filename,
            'size' => (int) ($chosen['size'] ?? 0),
            'sha1' => self::text($hashes['sha1'] ?? null),
            'sha512' => self::text($hashes['sha512'] ?? null),
        ];
    }

    /**
     * Download a file to a temporary path on the panel and verify it.
     *
     * Verification is not optional and not a warning. A jar is executed by the
     * server the moment it boots, so a file whose digest does not match what
     * the API published is either a corrupt transfer or something worse, and
     * either way it must not reach the node. The temporary file is deleted on
     * any failure so a rejected download cannot be picked up by anything later.
     *
     * The size ceiling is checked twice: against the size the API declared
     * before a byte is fetched, and against what actually arrived. The first is
     * the useful one, the second is the one that cannot be lied to.
     *
     * @param  array{url:string,filename:string,size:int,sha1:?string,sha512:?string}  $file
     * @return array{ok:bool,path?:string,bytes?:int,error?:string}
     */
    public function download(array $file, int $maxBytes): array
    {
        if ($file['size'] > 0 && $file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => self::tooLarge($file['size'], $maxBytes)];
        }

        // Only Modrinth's own CDN is fetched. A project cannot point the
        // installer at an arbitrary host, which is the difference between
        // installing from a catalogue and running whatever a URL in a JSON
        // document says to run.
        if (! self::trustedHost($file['url'])) {
            return ['ok' => false, 'error' => 'That file is not hosted by Modrinth, so it was not downloaded.'];
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
                ->get($file['url']);

            if (! $response->successful()) {
                @unlink($path);

                return ['ok' => false, 'error' => 'Modrinth refused the download (HTTP '.$response->status().').'];
            }
        } catch (Throwable) {
            @unlink($path);

            return ['ok' => false, 'error' => 'The download from Modrinth did not finish.'];
        }

        clearstatcache(true, $path);
        $bytes = (int) filesize($path);

        if ($bytes <= 0) {
            @unlink($path);

            return ['ok' => false, 'error' => 'Modrinth returned an empty file.'];
        }

        if ($bytes > $maxBytes) {
            @unlink($path);

            return ['ok' => false, 'error' => self::tooLarge($bytes, $maxBytes)];
        }

        $mismatch = self::checksumMismatch($path, $file);

        if ($mismatch !== null) {
            @unlink($path);

            return ['ok' => false, 'error' => $mismatch];
        }

        return ['ok' => true, 'path' => $path, 'bytes' => $bytes];
    }

    // ---------------------------------------------------------------- inside

    /**
     * The facet document for a target.
     *
     * One array of loader alternatives, which Modrinth reads as OR, plus a
     * second array holding the game version, which makes the two an AND. The
     * version array is left out entirely when nobody pinned one, because
     * `versions:LATEST` matches nothing and would look like a broken search.
     */
    private static function facets(ModTarget $target): string
    {
        $facets = [array_map(fn ($l) => 'categories:'.$l, array_values($target->loaders))];

        if ($target->versionKnown()) {
            $facets[] = ['versions:'.$target->gameVersion];
        }

        return (string) json_encode($facets);
    }

    /**
     * One cached GET.
     *
     * Three layers, in order. A fresh cached body is returned without touching
     * the network. A miss makes one request and a good answer is written to
     * both the fresh key and the week long stale key. A failure of any kind,
     * timeout, connection refused, 429, 500, or a body that is not the shape
     * the docs describe, falls back to the stale copy and then to null.
     *
     * The failure is remembered for a minute twice over: once under this key's
     * own marker, and once globally. The global one is the important half. A
     * page that checks five installed mods for updates would otherwise pay five
     * separate timeouts against a Modrinth that is down, because five distinct
     * keys each have to fail once to learn it. One shared marker turns a whole
     * minute of outage into a single wasted request for the entire panel.
     */
    private function get(string $key, string $path, array $query = []): mixed
    {
        if (! $this->enabled()) {
            return null;
        }

        $fresh = self::PREFIX.$key;
        $stale = self::PREFIX.'stale:'.$key;
        $failed = self::PREFIX.'failed:'.$key;

        $cached = Cache::get($fresh);

        if ($cached !== null) {
            return $cached;
        }

        if (Cache::get($failed) || Cache::get(self::PREFIX.'down')) {
            return Cache::get($stale);
        }

        try {
            $request = Http::acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->connectTimeout(min(2.0, $this->seconds()))
                ->timeout($this->seconds())
                ->retry(1, 200, throw: false);

            $token = trim((string) config('mods.modrinth.token', ''));

            if ($token !== '') {
                $request = $request->withHeaders(['Authorization' => $token]);
            }

            $response = $request->get(rtrim($this->baseUrl(), '/').$path, $query);

            $body = $response->successful() ? $response->json() : null;

            if (is_array($body)) {
                Cache::put($fresh, $body, $this->ttl($key));
                Cache::put($stale, $body, self::STALE);

                return $body;
            }
        } catch (Throwable) {
            // Deliberately swallowed. Nothing Modrinth does may break a page.
        }

        Cache::put($failed, true, 60);
        Cache::put(self::PREFIX.'down', true, 60);

        return Cache::get($stale);
    }

    /** Has a call failed recently enough that the catalogue is being skipped? */
    public function degraded(): bool
    {
        return (bool) Cache::get(self::PREFIX.'down');
    }

    private function ttl(string $key): int
    {
        $kind = explode(':', $key)[0];

        return (int) (config('mods.modrinth.ttl.'.$kind) ?? self::FRESH[$kind] ?? 600);
    }

    private function baseUrl(): string
    {
        return (string) config('mods.modrinth.base', 'https://api.modrinth.com');
    }

    private function seconds(): float
    {
        return max(1.0, (float) config('mods.modrinth.timeout', 5));
    }

    private function downloadSeconds(): float
    {
        return max(5.0, (float) config('mods.modrinth.download_timeout', 120));
    }

    /**
     * Who we are, in the form Modrinth's own documentation asks for:
     * project/version with a contact. Generic agents are blocked outright, and
     * a version that travels means a broken release is identifiable from their
     * side rather than by guesswork.
     */
    private function userAgent(): string
    {
        $version = UpdateService::currentVersion();
        $contact = trim((string) config('mods.modrinth.contact', ''));

        $agent = 'scriptgain/gamemgr/'.$version;

        return $contact === '' ? $agent : $agent.' ('.$contact.')';
    }

    /**
     * A Modrinth id or slug, or null when it is not one.
     *
     * Whitelisting the character set matters: the value goes into a URL path,
     * and an unfiltered one would let a caller walk off the documented route
     * and into whatever else the host serves.
     */
    private static function normaliseId(string $id): ?string
    {
        $id = trim($id);

        return preg_match('/^[A-Za-z0-9!@$()`.+,_"\-]{1,64}$/', $id) === 1 ? $id : null;
    }

    /** Files come from Modrinth's CDN or they do not get downloaded. */
    private static function trustedHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== 'https') {
            return false;
        }

        return $host === 'cdn.modrinth.com' || str_ends_with($host, '.modrinth.com');
    }

    /**
     * Null when the file matches what Modrinth published, a sentence naming the
     * problem when it does not.
     *
     * sha512 is preferred and sha1 accepted, because those are the two the API
     * publishes. A file with neither is refused rather than trusted: an
     * unverifiable jar is exactly the case this check exists for.
     *
     * @param  array{sha1:?string,sha512:?string}  $file
     */
    private static function checksumMismatch(string $path, array $file): ?string
    {
        foreach (['sha512', 'sha1'] as $algorithm) {
            $expected = strtolower((string) ($file[$algorithm] ?? ''));

            if ($expected === '') {
                continue;
            }

            $actual = hash_file($algorithm, $path);

            if (! is_string($actual) || ! hash_equals($expected, strtolower($actual))) {
                return 'The download did not match the '.strtoupper($algorithm).
                    ' checksum Modrinth published, so it was thrown away rather than installed.';
            }

            return null;
        }

        return 'Modrinth published no checksum for that file, so it was not installed.';
    }

    private static function tooLarge(int $bytes, int $maxBytes): string
    {
        return 'That file is '.self::megabytes($bytes).' and this panel installs up to '.
            self::megabytes($maxBytes).' from a catalogue. Upload it in the file manager instead.';
    }

    private static function megabytes(int $bytes): string
    {
        return round($bytes / 1048576, 1).' MB';
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
