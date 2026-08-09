<?php

namespace App\Services\Mods\Catalogue;

/**
 * One release of a project, narrowed to the file this server would install.
 *
 * `number` is what goes in the mods table and what "Update Ready" compares, so
 * it has to be the author's own version string rather than an internal id: a
 * customer reading "1.2.3 to 1.2.4" understands it, and a pair of UUIDs tells
 * them nothing.
 *
 * `file` is null when a version exists but nothing can be fetched from it, which
 * is not a hypothetical: Hangar allows a release whose download is a link to
 * somebody's own site, and Spiget marks a resource external for the same reason.
 * Those are shown with a link out and no install button, rather than an install
 * button that fails.
 */
final readonly class CatalogueVersion
{
    public function __construct(
        public string $id,
        public string $number,
        public ?CatalogueFile $file = null,
        /** Where to send somebody when the file cannot be fetched here. */
        public ?string $externalUrl = null,
    ) {}

    public function installable(): bool
    {
        return $this->file !== null;
    }
}
