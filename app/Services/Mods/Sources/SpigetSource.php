<?php

namespace App\Services\Mods\Sources;

use App\Services\Mods\Catalogue\CatalogueFile;
use App\Services\Mods\Catalogue\CatalogueProject;
use App\Services\Mods\Catalogue\CatalogueVersion;
use App\Services\Mods\Contracts\ModSource;
use App\Services\Mods\ModTarget;
use Illuminate\Support\Str;

/**
 * SpigotMC, through Spiget.
 *
 * SpigotMC has no API of its own. Spiget (https://spiget.org) is a third party
 * that indexes it and does have one: no key, no auth, and it will hand over the
 * jar for resources that are hosted on SpigotMC itself. It is the classic home
 * of Bukkit plugins and leaving it out would miss most of what a Paper server
 * owner actually goes looking for.
 *
 * Confirmed against live responses:
 *
 *   GET /v2/search/resources/{q}?field=name&size=&sort=-downloads
 *   GET /v2/resources/{id}      -> { name, tag, author: { id }, external,
 *                                    premium, testedVersions: [..],
 *                                    version: { id }, file: { type, url,
 *                                    externalUrl, size, sizeUnit } }
 *   GET /v2/resources/{id}/versions/latest -> { name, id }
 *   GET /v2/authors/{id}        -> { name }
 *   GET /v2/resources/{id}/download -> 302 to cdn.spiget.org, real jar
 *
 * TWO LIMITS, and both are stated on screen rather than discovered by a failed
 * install:
 *
 *   1. A resource can be `external`, meaning SpigotMC hosts a link rather than
 *      a file, and `premium`, meaning it is paid. Neither can be fetched. This
 *      is not an edge case: EssentialsX, the single most-installed plugin on
 *      the site, is external and points at a GitHub release. Those get their
 *      page linked and no install button.
 *
 *   2. Spiget publishes NO CHECKSUM for anything. Every other source here
 *      hands over a hash that the download is verified against before a byte
 *      reaches the node, and this one cannot. So installs from here are marked
 *      unverified and the source says so, rather than quietly implying a check
 *      that never happened. It is the one source allowed to skip verification,
 *      declared out loud in allowsUnverified() rather than configured.
 */
final class SpigetSource extends HttpSource implements ModSource
{
    public function key(): string
    {
        return 'spigot';
    }

    public function label(): string
    {
        return 'SpigotMC';
    }

    public function available(): bool
    {
        return (bool) config('mods.spigot.enabled', true);
    }

    public function unavailableReason(): ?string
    {
        return $this->available() ? null : 'SpigotMC is turned off in this panel\'s configuration.';
    }

    protected function baseUrl(): string
    {
        return (string) config('mods.spigot.base', 'https://api.spiget.org');
    }

    /**
     * The download starts at the API and lands on the CDN, so both are named.
     * Nothing else is followed: a resource page is user-submitted content and a
     * URL inside one is not a reason to fetch anything.
     */
    protected function trustedHost(string $url): bool
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'api.spiget.org' || $host === 'cdn.spiget.org';
    }

    /** The one source that publishes no hashes at all. See the class comment. */
    protected function allowsUnverified(): bool
    {
        return true;
    }

    /** Bukkit-family only. A Fabric server can load none of it. */
    public function supports(ModTarget $target): bool
    {
        return in_array('spigot', $target->loaders, true)
            || in_array('bukkit', $target->loaders, true)
            || in_array('paper', $target->loaders, true);
    }

    public function search(string $query, ModTarget $target, int $limit = 20): ?array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $body = $this->fetch(
            'search:'.md5($query.'|'.$limit),
            '/v2/search/resources/'.rawurlencode($query),
            ['field' => 'name', 'size' => max(1, min(25, $limit)), 'sort' => '-downloads'],
        );

        if ($body === null) {
            return null;
        }

        $out = [];

        foreach ($body as $hit) {
            if (is_array($hit) && ($project = $this->toProject($hit)) !== null) {
                $out[] = $project;
            }
        }

        return $out;
    }

    public function project(string $id): ?CatalogueProject
    {
        $id = self::cleanId($id);

        if ($id === null) {
            return null;
        }

        $body = $this->fetch('project:'.$id, '/v2/resources/'.$id);

        return is_array($body) ? $this->toProject($body) : null;
    }

    public function latestVersion(string $id, ModTarget $target): ?CatalogueVersion
    {
        $id = self::cleanId($id);

        if ($id === null) {
            return null;
        }

        $resource = $this->fetch('project:'.$id, '/v2/resources/'.$id);

        if (! is_array($resource)) {
            return null;
        }

        $version = $this->fetch('versions:'.$id, '/v2/resources/'.$id.'/versions/latest');
        $number = is_array($version) ? (string) ($version['name'] ?? '') : '';

        if ($number === '') {
            $number = (string) ($resource['version']['id'] ?? 'latest');
        }

        $page = 'https://www.spigotmc.org/resources/'.$id;

        // Hosted elsewhere or behind a paywall: a real version this panel is
        // not able to fetch, offered as a link rather than a broken button.
        if (($resource['external'] ?? false) || ($resource['premium'] ?? false)) {
            return new CatalogueVersion(
                id: $number,
                number: $number,
                file: null,
                externalUrl: self::text($resource['file']['externalUrl'] ?? null) ?? $page,
            );
        }

        $extension = (string) ($resource['file']['type'] ?? '.jar');
        $name = Str::slug((string) ($resource['name'] ?? 'plugin')).'-'.$number.
            (str_starts_with($extension, '.') ? $extension : '.jar');

        return new CatalogueVersion(
            id: $number,
            number: $number,
            file: new CatalogueFile(
                url: rtrim($this->baseUrl(), '/').'/v2/resources/'.$id.'/download',
                filename: $name,
                // Spiget reports size in a separate unit field (KB or MB) and
                // rounds it, so it is not used as the pre-flight ceiling: the
                // real check is the byte count after it arrives.
                size: 0,
                checksum: null,
                checksumAlgo: null,
                // SpigotMC is Bukkit by definition, so the directory decision
                // is told rather than inferred from a loader list that this
                // API does not have.
                loaders: ['spigot'],
            ),
            externalUrl: $page,
        );
    }

    private function toProject(array $hit): ?CatalogueProject
    {
        $id = self::text($hit['id'] ?? null);

        if ($id === null) {
            return null;
        }

        // The author arrives as an id. Resolving every one during a search
        // would be twenty extra calls for a list nobody has clicked yet, so it
        // is looked up only for the project actually being installed.
        $author = is_array($hit['author'] ?? null) && isset($hit['author']['name'])
            ? (string) $hit['author']['name']
            : $this->authorName($hit['author']['id'] ?? null);

        return new CatalogueProject(
            id: $id,
            slug: (string) ($hit['tag'] ?? $id),
            name: (string) ($hit['name'] ?? 'Untitled'),
            summary: Str::limit(strip_tags((string) ($hit['tag'] ?? '')), 480),
            author: $author,
            downloads: (int) ($hit['downloads'] ?? 0),
            icon: null,
            url: 'https://www.spigotmc.org/resources/'.$id,
            installable: ! ($hit['external'] ?? false) && ! ($hit['premium'] ?? false),
        );
    }

    private function authorName(mixed $id): ?string
    {
        $id = self::cleanId((string) $id);

        if ($id === null) {
            return null;
        }

        $body = $this->fetch('project:author-'.$id, '/v2/authors/'.$id);

        return is_array($body) ? self::text($body['name'] ?? null) : null;
    }

    /** Spiget ids are plain integers. Anything else is not asked about. */
    private static function cleanId(string $id): ?string
    {
        $id = trim($id);

        return preg_match('/^\d{1,12}$/', $id) === 1 ? $id : null;
    }

    private static function text(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
