<?php

namespace App\Services\Mods\Catalogue;

/**
 * One downloadable file, described the same way whoever is offering it.
 *
 * `verified` is the load-bearing field and the reason this is a class rather
 * than an array. Modrinth, Hangar and CurseForge all publish a hash for every
 * file, so the panel can refuse a download that does not match what the author
 * uploaded. Spiget publishes nothing, so an install from there is exactly as
 * trustworthy as the connection that carried it and no more.
 *
 * A panel that shows both the same way is telling somebody a checksum was
 * checked when it never was. So the flag rides with the file, the installer
 * records it, and the UI says which kind of install it was.
 */
final readonly class CatalogueFile
{
    public function __construct(
        public string $url,
        public string $filename,
        public int $size = 0,
        /** sha512, sha256, sha1 or md5. Null when the source publishes none. */
        public ?string $checksum = null,
        public ?string $checksumAlgo = null,
        /** The loaders this particular file declares, which decide the directory. */
        public array $loaders = [],
    ) {}

    /** Did the source publish something to check the download against? */
    public function verified(): bool
    {
        return $this->checksum !== null && $this->checksumAlgo !== null;
    }
}
