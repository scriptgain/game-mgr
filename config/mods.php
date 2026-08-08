<?php

// The mod and plugin catalogue.
//
// Modrinth is the only source wired to a real API so far. CurseForge needs an
// API key per install and SpigotMC has no official API at all, so both stay
// declared on templates and unavailable in the browser until they have a client
// of their own. Saying so in the UI is better than a search box that silently
// returns nothing.
return [
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
