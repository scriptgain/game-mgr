<?php

// Connection names for game servers.
//
// Every value here is a default only. An operator sets these in Settings and
// they land in the settings table, per the DB-driven config rule: a token in
// .env is a token in a backup, in a deploy log and in a support paste. The
// token in particular is never overlaid onto config at boot, it is read on
// demand and decrypted only when a call is about to be made.
//
// The shape of a name is:
//
//     <server label>.<node dns_label>.<zone>
//     alpha.lax1.play.scriptgain.com
//
// which is answered by one wildcard A record per node:
//
//     *.lax1.play.scriptgain.com.  A  45.63.49.152   (grey, never proxied)
//
// Nothing here can take the direct ip:port address away. A name is an extra
// address; Server::address() is untouched and stays the default everywhere.
return [
    // Master switch. Off means the panel behaves exactly as it did before this
    // feature existed: no names shown, no API calls, no records touched.
    'enabled' => env('GAMEMGR_DNS', false),

    // Which driver talks to the DNS provider. `null` is a real, supported
    // choice: it makes every call succeed locally without contacting anything,
    // which is what the dev stack uses.
    'provider' => env('GAMEMGR_DNS_PROVIDER', 'null'),

    // The suffix names are built under. This is NOT necessarily the provider's
    // zone: the zone at Cloudflare would be scriptgain.com while names live
    // under play.scriptgain.com. The driver walks up to find the real zone.
    'zone' => env('GAMEMGR_DNS_ZONE', ''),

    // Fallback only, and deliberately not the documented place to put it.
    // Settings wins, and Settings encrypts.
    'api_token' => env('GAMEMGR_DNS_TOKEN', ''),

    // Short on purpose. A provider being slow must never hold a page open: the
    // failure is recorded and dns-sync repairs it later.
    'timeout' => (float) env('GAMEMGR_DNS_TIMEOUT', 5),
    'connect_timeout' => (float) env('GAMEMGR_DNS_CONNECT_TIMEOUT', 3),

    // Low TTL so moving a node's address is a five minute problem, not a day.
    'ttl' => (int) env('GAMEMGR_DNS_TTL', 120),

    // Labels a server may never take. www/panel/api/status are the names an
    // operator will want for the panel itself; every node dns_label is added to
    // this list at runtime, so a server called "lax1" cannot shadow a node.
    'reserved_labels' => [
        'www', 'panel', 'api', 'status', 'admin', 'node', 'nodes', 'mail',
        'ns', 'ns1', 'ns2', 'mx', 'smtp', 'ftp', 'cdn', 'static', 'assets',
        'billing', 'support', 'docs', 'dev', 'staging', 'test', 'localhost',
    ],

    // Longest label a server name is cut down to. 63 is the DNS limit; 24 is
    // what somebody can actually type into a game client without mistyping it.
    'max_label' => 24,
];
