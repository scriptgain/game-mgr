<?php

namespace App\Services\Mods\Catalogue;

/**
 * A project, in the only shape the installer and the browse screen need.
 *
 * Every field here is written to the mods table or drawn on a card, and nothing
 * else survives the trip out of a source client. Each catalogue names these
 * differently (Modrinth has title and description, Hangar has name and
 * description, CurseForge has name and summary, Spiget has name and tag) and
 * normalising once here is what stops that spreading through the installer and
 * three Blade files.
 */
final readonly class CatalogueProject
{
    public function __construct(
        /** The id this source will accept back for a version lookup. */
        public string $id,
        public string $slug,
        public string $name,
        public string $summary = '',
        public ?string $author = null,
        public int $downloads = 0,
        public ?string $icon = null,
        /** The project's page, for the times a file cannot be fetched. */
        public ?string $url = null,
        /**
         * Can this panel fetch it at all?
         *
         * False for a SpigotMC resource that is external or premium, which is
         * not an edge case: EssentialsX, the most-installed plugin on the site,
         * is hosted on GitHub and SpigotMC only carries the link. A browse list
         * that offers Install on those is offering a button that fails, so the
         * answer rides on the hit and the row links out instead.
         */
        public bool $installable = true,
    ) {}
}
