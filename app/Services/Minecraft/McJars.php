<?php

namespace App\Services\Minecraft;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A read only client for the MCJars catalogue of Minecraft server builds.
 *
 * MCJars (https://mcjars.app) indexes every published build of every Java
 * server flavour: Paper, Purpur, Folia, Spigot, Vanilla, Forge, NeoForge,
 * Fabric, Quilt, the proxies and a long tail besides. GameMGR uses it for one
 * thing only: to know which types and versions really exist, so an operator
 * picks from a list instead of typing "1.20.4" and finding out at boot that
 * they meant "1.20.6".
 *
 * The endpoints, confirmed against https://mcjars.app/openapi.json (API
 * 3.12.1) rather than assumed:
 *
 *   GET /api/v2/types
 *       { success, types: { recommended: {...}, established: {...},
 *         experimental: {...}, miscellaneous: {...}, limbos: {...} } }
 *       Each group maps a type code (PAPER) to name, icon, colour, homepage,
 *       description, deprecated, experimental and build counts. There is no v3
 *       equivalent, so types come from v2 and everything else from v3.
 *
 *   GET /api/v3/builds/types/{type}/versions?page=&per_page=
 *       { versions: { total, per_page, page, data: [ { id, type, supported,
 *         java, builds, created, latest: {...} } ] } }
 *
 *   GET /api/v3/builds/types/{type}/versions/{version}?page=&per_page=
 *       { builds: { total, per_page, page, data: [ { uuid, version_id,
 *         project_version_id, type, experimental, name, installation, changes,
 *         created } ] } }
 *
 * Two things the API does NOT give, both worth writing down because they
 * shaped the design:
 *
 *   1. No checksum. A build carries jarUrl/jarSize on v2 and an installation
 *      script of download/unzip/remove steps on v3, and neither carries a
 *      digest. `hash` exists only as the REQUEST body of POST /api/v2/build,
 *      which is the reverse lookup: "here is the sha256 of a jar I already
 *      have, tell me which build it is". So MCJars cannot be used to verify a
 *      download before running it, only to identify one afterwards.
 *
 *   2. No auth on the public read endpoints, but a real rate limit. Responses
 *      carry x-ratelimit-limit: 120 and x-ratelimit-reset: 60, so 120 requests
 *      a minute per address. A version list rendered straight from the API on
 *      every page load would burn that on one busy panel, which is why nothing
 *      here talks to the network without going through the cache first.
 *
 * Every method answers null, never an empty array, when the service could not
 * be reached. Null means "we do not know", and the caller falls back to the
 * free text input the panel had before. An empty array means MCJars answered
 * and there genuinely is nothing.
 */
class McJars
{
    /** Cache key prefix. Bumped when the shape returned by this class changes. */
    private const PREFIX = 'mcjars:v1:';

    /**
     * How long a fresh answer is served without asking again.
     *
     * Types barely move. Version lists gain an entry when Mojang ships a
     * release. Build lists gain an entry whenever a project pushes a commit,
     * which for Paper is most days, so that one is the shortest.
     */
    private const FRESH = ['types' => 21600, 'versions' => 10800, 'builds' => 1800];

    /** How long the last good answer is kept to serve when the API is down. */
    private const STALE = 604800;

    /** Page size and the ceiling on how many pages one list may cost. */
    private const PER_PAGE = 200;

    private const MAX_PAGES = 6;

    public function __construct(
        private readonly string $base = '',
        private readonly float $timeout = 0,
    ) {}

    /** Is the integration switched on at all? */
    public function enabled(): bool
    {
        return (bool) config('gamemgr.mcjars.enabled', true);
    }

    /**
     * Every server type MCJars knows, flattened out of its five groups and
     * keyed by type code.
     *
     * The groups are presentation, not meaning: PAPER appears under
     * "recommended" and MOHIST under "miscellaneous", and a caller that wants
     * to offer both should not have to know which bucket each landed in. The
     * group name is kept on each entry so a picker can still sort by it.
     *
     * @return array<string, array{code:string,name:string,icon:?string,color:?string,description:?string,homepage:?string,deprecated:bool,experimental:bool,group:string,builds:int}>|null
     */
    public function types(): ?array
    {
        $body = $this->get('types', '/api/v2/types');

        if ($body === null) {
            return null;
        }

        $out = [];

        foreach ((array) ($body['types'] ?? []) as $group => $members) {
            if (! is_array($members)) {
                continue;
            }

            foreach ($members as $code => $info) {
                if (! is_array($info) || ! is_string($code) || $code === '') {
                    continue;
                }

                $out[$code] = [
                    'code' => $code,
                    'name' => (string) ($info['name'] ?? $code),
                    'icon' => $this->nullableString($info['icon'] ?? null),
                    'color' => $this->nullableString($info['color'] ?? null),
                    'description' => $this->nullableString($info['description'] ?? null),
                    'homepage' => $this->nullableString($info['homepage'] ?? null),
                    'deprecated' => (bool) ($info['deprecated'] ?? false),
                    'experimental' => (bool) ($info['experimental'] ?? false),
                    'group' => (string) $group,
                    'builds' => (int) ($info['builds'] ?? 0),
                ];
            }
        }

        return $out;
    }

    /**
     * The Minecraft versions this type has builds for, newest first.
     *
     * MCJars returns them newest first already; the order is preserved rather
     * than re-sorted, because "1.21.10" against "1.21.9" is not a string
     * comparison and version_compare does not understand "26.2-rc-2" either.
     *
     * @return array<int, array{id:string,channel:string,supported:bool,java:?int,builds:int,latest_build:?string}>|null
     */
    public function versions(string $type): ?array
    {
        $type = $this->normaliseType($type);

        if ($type === null) {
            return null;
        }

        $rows = $this->paged(
            'versions:'.$type,
            '/api/v3/builds/types/'.rawurlencode($type).'/versions',
            'versions',
        );

        if ($rows === null) {
            return null;
        }

        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id']) || ! is_scalar($row['id'])) {
                continue;
            }

            $latest = is_array($row['latest'] ?? null) ? $row['latest'] : [];

            $out[] = [
                'id' => (string) $row['id'],
                // RELEASE or SNAPSHOT. Named "channel" here because "type" in
                // this class already means the server flavour.
                'channel' => (string) ($row['type'] ?? 'RELEASE'),
                'supported' => (bool) ($row['supported'] ?? true),
                'java' => isset($row['java']) && is_numeric($row['java']) ? (int) $row['java'] : null,
                'builds' => (int) ($row['builds'] ?? 0),
                'latest_build' => $latest ? self::buildValue($latest) : null,
            ];
        }

        return $out;
    }

    /**
     * The builds published for one type and version, newest first.
     *
     * @return array<int, array{value:string,label:string,experimental:bool,created:?string,changes:array<int,string>}>|null
     */
    public function builds(string $type, string $version): ?array
    {
        $type = $this->normaliseType($type);

        if ($type === null || trim($version) === '') {
            return null;
        }

        $body = $this->get(
            'builds:'.$type.':'.$version,
            '/api/v3/builds/types/'.rawurlencode($type).'/versions/'.rawurlencode($version),
            ['per_page' => 100],
        );

        if ($body === null) {
            return null;
        }

        $out = [];

        foreach ((array) ($body['builds']['data'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = self::buildValue($row);

            if ($value === null) {
                continue;
            }

            $changes = [];
            foreach ((array) ($row['changes'] ?? []) as $change) {
                if (is_scalar($change)) {
                    $changes[] = (string) $change;
                }
            }

            $out[] = [
                'value' => $value,
                'label' => (string) ($row['name'] ?? $value),
                'experimental' => (bool) ($row['experimental'] ?? false),
                'created' => $this->nullableString($row['created'] ?? null),
                'changes' => array_slice($changes, 0, 3),
            ];
        }

        return $out;
    }

    /**
     * What to put in the environment variable that pins a build.
     *
     * The two families answer differently and the container images follow the
     * upstream projects, not MCJars. Paper names its builds "#60" and
     * PAPER_BUILD wants "60". Fabric and Forge have no build number at all:
     * the thing being pinned is the loader version, which MCJars carries as
     * project_version_id ("0.19.3") and repeats as the build name. So
     * project_version_id wins where it exists, and the hash is stripped off
     * the name where it does not.
     */
    public static function buildValue(array $build): ?string
    {
        $project = $build['project_version_id'] ?? $build['projectVersionId'] ?? null;

        if (is_scalar($project) && trim((string) $project) !== '') {
            return trim((string) $project);
        }

        $name = $build['name'] ?? null;

        if (! is_scalar($name)) {
            return null;
        }

        $name = ltrim(trim((string) $name), '#');

        return $name === '' ? null : $name;
    }

    // ----------------------------------------------------------------- inside

    /**
     * Every page of a paginated v3 list, newest first, up to a hard cap.
     *
     * One page is not enough and the difference matters. Paper has 66 versions
     * and Purpur 40, so a single request covers them, but Fabric has 491, Quilt
     * 430 and Vanilla 838, and stopping at the first page would quietly hide
     * every Minecraft version older than the last couple of years from anyone
     * running a modded server. The cap is there so a type that grows a third
     * time cannot turn one page render into thirty requests.
     *
     * The whole assembled list is cached under one key, so the pages are only
     * ever walked on a cold cache.
     */
    private function paged(string $key, string $path, string $envelope): ?array
    {
        $cached = Cache::get(self::PREFIX.$key);

        if (is_array($cached)) {
            return $cached;
        }

        $rows = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $body = $this->get($key.':p'.$page, $path, ['per_page' => self::PER_PAGE, 'page' => $page]);

            if ($body === null) {
                // A first page that never arrived is no answer at all. A later
                // page that fails leaves a short list, which is still a usable
                // one, so it is kept rather than thrown away.
                return $page === 1 ? null : $this->remember($key, $rows);
            }

            $data = (array) ($body[$envelope]['data'] ?? []);
            $rows = array_merge($rows, $data);

            $total = (int) ($body[$envelope]['total'] ?? count($rows));

            if (count($data) < self::PER_PAGE || count($rows) >= $total) {
                break;
            }
        }

        return $this->remember($key, $rows);
    }

    /** Cache an assembled list under both the fresh and the stale key. */
    private function remember(string $key, array $rows): array
    {
        Cache::put(self::PREFIX.$key, $rows, $this->ttl($key));
        Cache::put(self::PREFIX.'stale:'.$key, $rows, self::STALE);

        return $rows;
    }

    /**
     * One cached GET.
     *
     * Three layers, in order. A fresh cached body is returned without touching
     * the network. A miss makes one request with a short timeout, and a good
     * answer is written to both the fresh key and the week long stale key. A
     * failure of any kind, timeout, connection refused, a 500, a 400 because
     * somebody asked for a type that does not exist, or a body that is not the
     * shape the docs describe, falls back to the stale copy and then to null.
     *
     * The failure is also remembered for a minute under the fresh key's own
     * negative marker, so a panel whose operator is clicking around does not
     * make one doomed request per click while MCJars is down.
     */
    private function get(string $key, string $path, array $query = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $fresh = self::PREFIX.$key;
        $stale = self::PREFIX.'stale:'.$key;
        $failed = self::PREFIX.'failed:'.$key;

        $cached = Cache::get($fresh);

        if (is_array($cached)) {
            return $cached;
        }

        if (Cache::get($failed)) {
            $last = Cache::get($stale);

            return is_array($last) ? $last : null;
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->connectTimeout(min(2.0, $this->seconds()))
                ->timeout($this->seconds())
                ->retry(1, 200, throw: false)
                ->get(rtrim($this->baseUrl(), '/').$path, $query);

            $body = $response->successful() ? $response->json() : null;

            if (is_array($body)) {
                Cache::put($fresh, $body, $this->ttl($key));
                Cache::put($stale, $body, self::STALE);

                return $body;
            }
        } catch (Throwable) {
            // Deliberately swallowed. Nothing MCJars does may break a page.
        }

        Cache::put($failed, true, 60);

        $last = Cache::get($stale);

        return is_array($last) ? $last : null;
    }

    /** The fresh TTL for a key, chosen by its first segment. */
    private function ttl(string $key): int
    {
        $kind = explode(':', $key)[0];

        return (int) (config('gamemgr.mcjars.ttl.'.$kind) ?? self::FRESH[$kind] ?? 3600);
    }

    private function baseUrl(): string
    {
        return $this->base !== '' ? $this->base : (string) config('gamemgr.mcjars.base', 'https://mcjars.app');
    }

    private function seconds(): float
    {
        return $this->timeout > 0 ? $this->timeout : (float) config('gamemgr.mcjars.timeout', 4);
    }

    /**
     * MCJars counts requests per user agent on its own stats pages, so saying
     * who we are is both polite and useful to them. The version travels so a
     * broken release is identifiable from their side.
     */
    private function userAgent(): string
    {
        return 'GameMGR/'.trim((string) config('gamemgr.version', '1.0.0')).' (+https://github.com/gamemgr)';
    }

    /**
     * A type code as MCJars writes it, or null when it is not one.
     *
     * Whitelisting the character set matters: the code goes into a URL path,
     * and an unfiltered one would let a caller walk off the documented route
     * and into whatever else the host serves.
     */
    private function normaliseType(string $type): ?string
    {
        $type = mb_strtoupper(trim($type));

        return preg_match('/^[A-Z0-9_]{1,32}$/', $type) === 1 ? $type : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }
}
