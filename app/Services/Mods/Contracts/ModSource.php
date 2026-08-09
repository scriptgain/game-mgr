<?php

namespace App\Services\Mods\Contracts;

use App\Services\Mods\Catalogue\CatalogueFile;
use App\Services\Mods\Catalogue\CatalogueProject;
use App\Services\Mods\Catalogue\CatalogueVersion;
use App\Services\Mods\ModTarget;

/**
 * A place mods and plugins can be installed from.
 *
 * This exists because the catalogue was one client wired straight into the
 * installer, while templates already declared four sources. Paper says
 * modrinth, spigot and curseforge; ARK says curseforge alone. Only the first
 * worked, so an ARK owner had a Mods tab that refused everything it offered.
 *
 * The contract is deliberately small. Everything hard about installing, the
 * checksum, the temporary file, the streaming upload to the node, the safe
 * filename and the plugins-versus-mods decision, belongs to ModInstaller and is
 * the same wherever the bytes came from. A source only has to answer four
 * questions: what can I offer this server, what is this project, which release
 * fits, and where do I get the file.
 *
 * Two rules every implementation keeps:
 *
 *   Nothing throws. A catalogue is a third party and will be slow, rate
 *   limiting or down. Every method answers null and sets degraded() rather than
 *   raising, because the Mods page has to render from the database whatever the
 *   network is doing.
 *
 *   Downloads come from hosts this source owns. Each implementation carries its
 *   own allowlist, because a project page is user-submitted content and a URL
 *   inside it is not a reason to fetch anything.
 */
interface ModSource
{
    /** The key stored on the mods row: modrinth, hangar, spigot, curseforge, workshop. */
    public function key(): string;

    public function label(): string;

    /** Turned on, and holding whatever credential it needs to work at all. */
    public function available(): bool;

    /** Why it cannot be used, for a screen that would otherwise say nothing. */
    public function unavailableReason(): ?string;

    /** Did the last call to this source fail or time out? */
    public function degraded(): bool;

    /**
     * Can this source serve THIS server?
     *
     * Hangar has Paper plugins and nothing else, so a Fabric server should
     * never see the tab rather than see an empty one.
     */
    public function supports(ModTarget $target): bool;

    /**
     * Search, narrowed to what the server can actually run.
     *
     * @return array<int,CatalogueProject>|null null means the source did not answer
     */
    public function search(string $query, ModTarget $target, int $limit = 20): ?array;

    public function project(string $id): ?CatalogueProject;

    /** The newest release this server can run, or null if there is none. */
    public function latestVersion(string $id, ModTarget $target): ?CatalogueVersion;

    /**
     * Fetch the file to a temporary path on the panel, verifying it on the way.
     *
     * @return array{ok:bool,error?:string,path?:string,bytes?:int}
     */
    public function download(CatalogueFile $file, int $maxBytes): array;
}
