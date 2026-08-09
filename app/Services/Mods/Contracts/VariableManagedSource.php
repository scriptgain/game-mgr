<?php

namespace App\Services\Mods\Contracts;

use App\Models\Mod;
use App\Models\Server;
use App\Services\Mods\Catalogue\CatalogueProject;

/**
 * A source where the GAME fetches its own mods, given a list of ids.
 *
 * The third install strategy, and the one that surprised me. The other two both
 * end with a file somewhere: the panel downloads it and streams it to the node,
 * or steamcmd fetches it on the node. ARK: Survival Ascended does neither.
 * Wildcard moved ASA modding to CurseForge and locked distribution, so every
 * ASA mod comes back with `allowModDistribution: false` and there is no file to
 * fetch, by anybody, ever. What the server takes instead is a comma separated
 * list of project ids in MOD_IDS, and it downloads them itself at boot through
 * its own CurseForge integration.
 *
 * So installing here means editing a server variable, and removing means
 * editing it back. Nothing is downloaded, nothing is checksummed, and the mods
 * row is a record of what this server has been TOLD to load rather than of a
 * file on disk.
 *
 * Worth being plain about the consequence: until the server restarts and
 * fetches them, an entry here is an intention rather than an installed mod. The
 * panel says so rather than implying otherwise.
 */
interface VariableManagedSource
{
    /** The template variable carrying the list, for example MOD_IDS. */
    public function listVariable(): string;

    /**
     * Does THIS server work that way?
     *
     * Asked per server rather than per source, because one client serves both:
     * CurseForge downloads a jar for Minecraft and hands ARK a list of ids.
     */
    public function managesByList(\App\Services\Mods\ModTarget $target): bool;

    /** Add this project to the server's list. */
    public function addToList(Server $server, CatalogueProject $project): array;

    /** Take it back out again. */
    public function removeFromList(Server $server, Mod $mod): array;
}
