<?php

namespace App\Services\Mods\Contracts;

use App\Models\Server;
use App\Services\Mods\Catalogue\CatalogueProject;

/**
 * A source whose files the PANEL never touches.
 *
 * Modrinth, Hangar, SpigotMC and CurseForge all hand over a URL: the panel
 * downloads it, checks it against a published hash, and streams it to the node.
 * The Steam Workshop cannot work that way, because Valve serves Workshop
 * content only to an authenticated Steam client. The fetching is done by
 * steamcmd on the node itself.
 *
 * Rather than bending ModSource::download() into something that sometimes
 * returns a file and sometimes performs an install, a source that works this
 * way says so by implementing this, and ModInstaller takes the other branch.
 * The consequence is worth stating: there is no checksum on this path, because
 * the panel never sees the bytes. Steam is the transport and Steam is the
 * verification.
 */
interface NodeInstalledSource
{
    /**
     * Have the node fetch and place the item.
     *
     * @return array{ok:bool,error?:string,path?:string,version?:string}
     */
    public function installOnNode(Server $server, CatalogueProject $project): array;
}
