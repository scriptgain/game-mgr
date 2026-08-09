<?php

// Node daemon transport. One panel, nodes anywhere: every game server lives on
// a node, and the panel reaches it over HTTPS plus a per-node bearer token.
return [
    // Default daemon port offered on the node create form.
    'default_port' => (int) env('NODE_DEFAULT_PORT', 8942),

    // Seconds before a daemon call is abandoned. Kept short: a dead node must
    // not stall a page render, it must degrade to "offline".
    'timeout' => (int) env('NODE_TIMEOUT', 10),

    // Seconds a power action may take. A graceful stop sends the game its own
    // stop command and waits for it to save, which the drivers allow 30 seconds
    // for, and a restart is a stop followed by a start. The ten second default
    // expired first and the panel reported "Node unreachable" about a node that
    // was answering perfectly well and doing exactly what it was told.
    'power_timeout' => (int) env('NODE_POWER_TIMEOUT', 90),

    // Seconds a file manager upload may take. Deliberately not the timeout
    // above: a few hundred megabytes over a domestic connection is minutes, and
    // ten seconds would abort every upload worth having this feature for.
    'upload_timeout' => (int) env('NODE_UPLOAD_TIMEOUT', 3600),

    // When true, an unreachable daemon is answered with synthetic data instead
    // of an error, so the whole UI stays exercisable while the real runtime
    // drivers are still being written. Turn this OFF in production.
    'fake' => filter_var(env('NODE_FAKE', false), FILTER_VALIDATE_BOOL),

    // A node is considered offline once its last heartbeat is older than this.
    'offline_after' => 120,

    // Enroll tokens are single use and expire quickly; they only ever buy the
    // daemon its long-lived credential.
    'enroll_token_ttl' => 3600,

    /*
     * Reverse mode: a node behind NAT that dials the panel instead of being
     * dialled. Work is parked in node_calls and the daemon's own long poll
     * collects it.
     *
     * WHAT IT COSTS, because it is the honest headline of this feature: one
     * PHP-FPM worker per reverse node while its poll is parked, plus one more
     * for the duration of each call the panel makes. A handful of nodes on a
     * self-hosted panel is nothing. A hundred is a worker pool, and the answer
     * there is direct nodes, not a bigger pool.
     */
    'reverse' => [
        // How long a daemon's poll may wait before being told to come back.
        // Long enough that an idle node costs one request every half minute,
        // short enough to sit inside any sane proxy read timeout.
        'poll_hold' => (int) env('NODE_REVERSE_POLL_HOLD', 25),

        // How often both sides look at the row. 100ms is imperceptible on a
        // button press and cheap on an indexed primary key.
        'poll_interval_ms' => (int) env('NODE_REVERSE_POLL_INTERVAL_MS', 100),

        // The largest body that can travel inside a parked row, in bytes. A
        // reverse node has no other channel, so an upload has to fit in the
        // database. 8 MB covers the config files people actually edit and
        // refuses the modpack that would otherwise be base64 in three places
        // at once.
        'max_payload' => (int) env('NODE_REVERSE_MAX_PAYLOAD', 8 * 1024 * 1024),

        // How long finished calls stay before the prune drops them. They are
        // only useful while somebody is waiting; the hour is for debugging.
        'prune_after' => (int) env('NODE_REVERSE_PRUNE_AFTER', 3600),
    ],
];
