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
];
