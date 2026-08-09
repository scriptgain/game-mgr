<?php

namespace App\Services\Mods\Sources;

use App\Services\Mods\Catalogue\CatalogueFile;
use App\Services\Mods\Catalogue\CatalogueProject;
use App\Services\Mods\Catalogue\CatalogueVersion;
use App\Services\Mods\Contracts\ModSource;
use App\Services\Mods\Modrinth;
use App\Services\Mods\ModTarget;
use Illuminate\Support\Str;

/**
 * Modrinth, behind the common interface.
 *
 * A thin adapter and nothing more. App\Services\Mods\Modrinth is the client
 * that has actually been run against the live API, including the facet rules,
 * the caching, the CDN allowlist and the checksum verification, and none of
 * that is rewritten here. This class only translates its arrays into the shapes
 * every other source also answers in.
 */
final class ModrinthSource implements ModSource
{
    public function __construct(private readonly Modrinth $api) {}

    public function key(): string
    {
        return 'modrinth';
    }

    public function label(): string
    {
        return 'Modrinth';
    }

    public function available(): bool
    {
        return $this->api->enabled();
    }

    public function unavailableReason(): ?string
    {
        return $this->available() ? null : 'Modrinth is turned off in this panel\'s configuration.';
    }

    public function degraded(): bool
    {
        return $this->api->degraded();
    }

    /** Everything Modrinth indexes: plugins for Bukkit servers, mods for the rest. */
    public function supports(ModTarget $target): bool
    {
        return $target->loader !== null;
    }

    public function search(string $query, ModTarget $target, int $limit = 20): ?array
    {
        $hits = $this->api->search($query, $target, $limit);

        if ($hits === null) {
            return null;
        }

        return array_map(fn (array $hit) => new CatalogueProject(
            id: (string) $hit['id'],
            slug: (string) ($hit['slug'] ?? $hit['id']),
            name: (string) $hit['name'],
            summary: (string) ($hit['summary'] ?? ''),
            author: $hit['author'] ?? null,
            downloads: (int) ($hit['downloads'] ?? 0),
            icon: $hit['icon'] ?? null,
            url: 'https://modrinth.com/project/'.($hit['slug'] ?? $hit['id']),
        ), $hits);
    }

    public function project(string $id): ?CatalogueProject
    {
        $project = $this->api->project($id);

        if ($project === null) {
            return null;
        }

        $slug = (string) ($project['slug'] ?? $project['id']);

        return new CatalogueProject(
            id: (string) $project['id'],
            slug: $slug,
            name: (string) ($project['title'] ?? $slug),
            summary: Str::limit((string) ($project['description'] ?? ''), 480),
            author: $this->api->author((string) $project['id']),
            downloads: (int) ($project['downloads'] ?? 0),
            icon: $project['icon_url'] ?? null,
            url: 'https://modrinth.com/project/'.$slug,
        );
    }

    public function latestVersion(string $id, ModTarget $target): ?CatalogueVersion
    {
        $version = $this->api->latestVersion($id, $target);

        if ($version === null) {
            return null;
        }

        $file = Modrinth::primaryFile($version);

        return new CatalogueVersion(
            id: (string) $version['id'],
            number: (string) ($version['version_number'] ?? $version['id']),
            file: $file === null ? null : new CatalogueFile(
                url: $file['url'],
                filename: $file['filename'],
                size: (int) $file['size'],
                // sha512 preferred, sha1 accepted: the two the API publishes.
                checksum: $file['sha512'] ?? $file['sha1'] ?? null,
                checksumAlgo: isset($file['sha512']) ? 'sha512' : (isset($file['sha1']) ? 'sha1' : null),
                loaders: array_values(array_filter((array) ($version['loaders'] ?? []), 'is_string')),
            ),
        );
    }

    public function download(CatalogueFile $file, int $maxBytes): array
    {
        // The existing client owns the allowlist and the hash check, so it is
        // handed back the array shape it already expects.
        return $this->api->download([
            'url' => $file->url,
            'filename' => $file->filename,
            'size' => $file->size,
            'sha1' => $file->checksumAlgo === 'sha1' ? $file->checksum : null,
            'sha512' => $file->checksumAlgo === 'sha512' ? $file->checksum : null,
        ], $maxBytes);
    }
}
