<?php

// The mod and plugin catalogue.
//
// Modrinth and Hangar are wired to real APIs and need no credentials. Both
// publish a checksum for every file and serve those files themselves, which is
// the combination an unattended installer needs.
//
// CurseForge needs an API key per install and SpigotMC has no official API, so
// they remain declared on templates and are reported as unavailable with the
// reason, rather than being a search box that silently returns nothing.
return [
    // The contact address every catalogue is asked to identify this panel by.
    // Modrinth requires one as a condition of use and blocks generic agents;
    // the others simply behave better with it.
    'contact' => env('GAMEMGR_MODS_CONTACT', 'support@scriptgain.com'),

    'spigot' => [
        // SpigotMC has no API; Spiget indexes it and does. No key, no auth.
        //
        // The catch is stated in SpigetSource and surfaced in the UI rather
        // than buried here: Spiget publishes no checksums, and a resource that
        // is external or premium cannot be fetched at all. EssentialsX, the
        // most-installed plugin on the site, is one of those.
        'enabled' => filter_var(env('GAMEMGR_SPIGOT', true), FILTER_VALIDATE_BOOL),

        'base' => env('GAMEMGR_SPIGOT_URL', 'https://api.spiget.org'),

        'timeout' => (float) env('GAMEMGR_SPIGOT_TIMEOUT', 6),

        'download_timeout' => (float) env('GAMEMGR_SPIGOT_DOWNLOAD_TIMEOUT', 120),

        'ttl' => [
            'search' => 300,
            'project' => 3600,
            'versions' => 900,
        ],
    ],

    'hangar' => [
        'enabled' => filter_var(env('GAMEMGR_HANGAR', true), FILTER_VALIDATE_BOOL),

        'base' => env('GAMEMGR_HANGAR_URL', 'https://hangar.papermc.io'),

        'timeout' => (float) env('GAMEMGR_HANGAR_TIMEOUT', 5),

        'download_timeout' => (float) env('GAMEMGR_HANGAR_DOWNLOAD_TIMEOUT', 120),

        // Same reasoning as the Modrinth block below: searches move with the
        // ranking, project metadata barely changes, and the version list is the
        // one an update check needs to see move.
        'ttl' => [
            'search' => 300,
            'project' => 3600,
            'versions' => 900,
        ],
    ],

    'modrinth' => [
        'enabled' => filter_var(env('GAMEMGR_MODRINTH', true), FILTER_VALIDATE_BOOL),

        'base' => env('GAMEMGR_MODRINTH_URL', 'https://api.modrinth.com'),

        // Seconds before a catalogue call is abandoned. Short on purpose: the
        // Mods page has to render whether or not Modrinth is answering, and a
        // page that hangs for ten seconds on a third party is worse than one
        // that says the catalogue is unavailable.
        'timeout' => (float) env('GAMEMGR_MODRINTH_TIMEOUT', 5),

        // Seconds a download may take. A jar is not a search: it is megabytes
        // over whatever connection the panel has.
        'download_timeout' => (float) env('GAMEMGR_MODRINTH_DOWNLOAD_TIMEOUT', 120),

        // Modrinth requires a uniquely identifying User-Agent with a contact,
        // and blocks generic ones. This is a condition of use, not a nicety.
        // See https://docs.modrinth.com/api/ under "User Agents".
        'contact' => env('GAMEMGR_MODRINTH_CONTACT', 'support@scriptgain.com'),

        // Optional personal access token. Anonymous access is allowed and is
        // what a self hosted panel will normally use; a token only raises the
        // ceiling on a very busy install.
        'token' => env('GAMEMGR_MODRINTH_TOKEN'),

        // Seconds a fresh answer is served for. Search results move with the
        // ranking and are cheap to refetch, so they are the shortest. Project
        // metadata barely changes. Version lists change when an author
        // publishes, which is the thing an update check needs to notice.
        'ttl' => [
            'search' => 300,
            'project' => 3600,
            'versions' => 900,
        ],
    ],

    // The largest file this panel will install from a catalogue, in bytes.
    //
    // 64 MiB is deliberately far above a plugin and comfortably above a mod:
    // LuckPerms is 1.5 MB, EssentialsX about 2 MB, and the heaviest single
    // Forge or Fabric mods land in the tens of megabytes. Anything larger is a
    // modpack or a server image, which is a different job and belongs in the
    // file manager where it can be streamed.
    //
    // The ceiling also protects the transfer itself. The download is buffered
    // to a temporary file on the panel before it is sent to the node, so an
    // unbounded install would be an unbounded amount of panel disk held for the
    // length of somebody else's download.
    'max_bytes' => (int) env('GAMEMGR_MOD_MAX_BYTES', 64 * 1024 * 1024),
];
