<?php

namespace App\Services\Mods\Sources;

use App\Services\Mods\Catalogue\CatalogueFile;
use App\Services\Mods\Catalogue\CatalogueProject;
use App\Services\Mods\Catalogue\CatalogueVersion;
use App\Services\Mods\Contracts\ModSource;
use App\Services\Mods\ModTarget;
use Illuminate\Support\Str;

/**
 * Hangar, PaperMC's own plugin repository.
 *
 * The second source worth having for the same reasons as the first: a public
 * API with no key, files it serves itself, and a published hash for every one
 * of them. Everything below was confirmed against live responses rather than
 * read off a docs page:
 *
 *   GET /api/v1/projects?q=&limit=&offset=
 *       { pagination: { count }, result: [ { name, namespace: { owner, slug },
 *         description, avatarUrl, category, stats: { downloads },
 *         supportedPlatforms: { PAPER: [..], VELOCITY: [..], WATERFALL: [..] } } ] }
 *
 *   GET /api/v1/projects/{slug}/versions?limit=
 *       { result: [ { id, name, channel: { name }, platformDependencies:
 *         { PAPER: [game versions] }, downloads: { PAPER: { fileInfo:
 *         { name, sizeBytes, sha256Hash }, downloadUrl, externalUrl } } } ] }
 *
 * Three things that shape the code, all learned from the live API:
 *
 *   1. The path variable is the NAMESPACE SLUG, not the display name. Asking
 *      for /projects/EssentialsX returns "Unknown value for path variable"
 *      because that project is not on Hangar at all, and a search for it
 *      returns EssentialsX_Selectors and two other unrelated plugins. So the
 *      slug is carried through from search and never guessed from a title.
 *
 *   2. The newest version is routinely a SNAPSHOT. ViaVersion's top entry is
 *      5.12.0-SNAPSHOT+1048. Installing the newest thing published would put
 *      snapshot builds on people's servers without them asking, so a Release
 *      channel version is preferred and a snapshot is only used when a project
 *      has published nothing else.
 *
 *   3. A version can carry an externalUrl instead of a downloadUrl, meaning the
 *      author hosts the jar somewhere else. Those cannot be installed and are
 *      offered as a link out instead of an install button that fails.
 *
 * Paper family only. Hangar's other platforms, Velocity and Waterfall, are
 * proxies rather than game servers and nothing in this panel runs one, so a
 * Fabric or Forge server never sees this source at all.
 */
final class HangarSource extends HttpSource implements ModSource
{
    /** Hangar's own name for the only platform that matters here. */
    private const PLATFORM = 'PAPER';

    public function key(): string
    {
        return 'hangar';
    }

    public function label(): string
    {
        return 'Hangar';
    }

    public function available(): bool
    {
        return (bool) config('mods.hangar.enabled', true);
    }

    public function unavailableReason(): ?string
    {
        return $this->available() ? null : 'Hangar is turned off in this panel\'s configuration.';
    }

    protected function baseUrl(): string
    {
        return (string) config('mods.hangar.base', 'https://hangar.papermc.io');
    }

    protected function trustedHost(string $url): bool
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'hangar.papermc.io' || $host === 'hangarcdn.papermc.io';
    }

    /** Bukkit-family servers only. A Fabric server can load none of this. */
    public function supports(ModTarget $target): bool
    {
        return in_array('paper', $target->loaders, true)
            || in_array('spigot', $target->loaders, true)
            || in_array('bukkit', $target->loaders, true);
    }

    public function search(string $query, ModTarget $target, int $limit = 20): ?array
    {
        $body = $this->fetch(
            'search:'.md5($query.'|'.$limit),
            '/api/v1/projects',
            ['q' => $query, 'limit' => max(1, min(25, $limit)), 'offset' => 0],
        );

        if ($body === null) {
            return null;
        }

        $out = [];

        foreach ((array) ($body['result'] ?? []) as $hit) {
            if (! is_array($hit)) {
                continue;
            }

            // A project that does not list PAPER at all cannot run here, and
            // showing it would be offering something that cannot be installed.
            if (! isset($hit['supportedPlatforms'][self::PLATFORM])) {
                continue;
            }

            $project = $this->toProject($hit);

            if ($project !== null) {
                $out[] = $project;
            }
        }

        return $out;
    }

    public function project(string $id): ?CatalogueProject
    {
        $slug = self::cleanSlug($id);

        if ($slug === null) {
            return null;
        }

        $body = $this->fetch('project:'.$slug, '/api/v1/projects/'.rawurlencode($slug));

        return is_array($body) ? $this->toProject($body) : null;
    }

    public function latestVersion(string $id, ModTarget $target): ?CatalogueVersion
    {
        $slug = self::cleanSlug($id);

        if ($slug === null) {
            return null;
        }

        $body = $this->fetch(
            'versions:'.$slug,
            '/api/v1/projects/'.rawurlencode($slug).'/versions',
            ['limit' => 25, 'offset' => 0],
        );

        if ($body === null) {
            return null;
        }

        $versions = array_values(array_filter((array) ($body['result'] ?? []), 'is_array'));
        $compatible = array_values(array_filter($versions, fn (array $v) => $this->runsHere($v, $target)));

        if ($compatible === []) {
            return null;
        }

        // Release first. Only if a project has never published one does a
        // snapshot get installed, and then it is the newest of those.
        $release = array_values(array_filter($compatible, static fn (array $v) => strtolower(
            (string) ($v['channel']['name'] ?? '')
        ) === 'release'));

        return $this->toVersion(($release[0] ?? $compatible[0]));
    }

    /**
     * Does this version list a PAPER build for the Minecraft version we run?
     *
     * An unknown game version (the VERSION=LATEST case) is not treated as "no",
     * because that would hide every plugin from a server whose version simply
     * floats. Platform support alone is enough then.
     */
    private function runsHere(array $version, ModTarget $target): bool
    {
        $supported = (array) ($version['platformDependencies'][self::PLATFORM] ?? []);

        if ($supported === []) {
            return false;
        }

        if (! $target->versionKnown()) {
            return true;
        }

        return in_array($target->gameVersion, array_map('strval', $supported), true);
    }

    private function toVersion(array $version): ?CatalogueVersion
    {
        $download = $version['downloads'][self::PLATFORM] ?? null;

        if (! is_array($download)) {
            return null;
        }

        $number = (string) ($version['name'] ?? $version['id'] ?? '');
        $info = is_array($download['fileInfo'] ?? null) ? $download['fileInfo'] : [];
        $url = self::text($download['downloadUrl'] ?? null);
        $external = self::text($download['externalUrl'] ?? null);

        // Hosted somewhere else: real version, nothing this panel may fetch.
        if ($url === null) {
            return new CatalogueVersion(
                id: (string) ($version['id'] ?? $number),
                number: $number,
                file: null,
                externalUrl: $external,
            );
        }

        return new CatalogueVersion(
            id: (string) ($version['id'] ?? $number),
            number: $number,
            file: new CatalogueFile(
                url: $url,
                filename: (string) ($info['name'] ?? $number.'.jar'),
                size: (int) ($info['sizeBytes'] ?? 0),
                checksum: self::text($info['sha256Hash'] ?? null),
                checksumAlgo: isset($info['sha256Hash']) ? 'sha256' : null,
                // Hangar is a Bukkit-family repository and says so by platform
                // rather than by loader, so the directory decision is told
                // "paper" explicitly instead of being left to guess.
                loaders: ['paper'],
            ),
        );
    }

    private function toProject(array $hit): ?CatalogueProject
    {
        $slug = self::text($hit['namespace']['slug'] ?? null);

        if ($slug === null) {
            return null;
        }

        return new CatalogueProject(
            id: $slug,
            slug: $slug,
            name: (string) ($hit['name'] ?? $slug),
            summary: Str::limit((string) ($hit['description'] ?? ''), 480),
            author: self::text($hit['namespace']['owner'] ?? null),
            downloads: (int) ($hit['stats']['downloads'] ?? 0),
            icon: self::text($hit['avatarUrl'] ?? null),
            url: 'https://hangar.papermc.io/'.($hit['namespace']['owner'] ?? '').'/'.$slug,
        );
    }

    /** Hangar slugs are plain; anything else is not asked about. */
    private static function cleanSlug(string $id): ?string
    {
        $id = trim($id);

        return preg_match('/^[A-Za-z0-9._\-]{1,64}$/', $id) === 1 ? $id : null;
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
