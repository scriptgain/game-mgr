<?php

/*
|--------------------------------------------------------------------------
| GameMGR editions
|--------------------------------------------------------------------------
| GameMGR is free to run. The paid editions raise the ceilings and unlock the
| games and features below; the free edition is a real product, not a crippled
| demo, and everything a free install already has keeps working forever.
|
| Two rules govern every gate in this file.
|
| Nothing here ever stops a running server. A gate refuses to CREATE the next
| thing; it never suspends, never stops, and never deletes. An install whose
| licence lapses keeps every game it already has online, and the person whose
| Minecraft world is up does not lose it because a card expired.
|
| And nothing here locks the operator out of their own panel. A licensing
| problem shows a banner and blocks new creation. It does not block logging in,
| taking a backup, or getting a server back up.
*/

return [

    // Which edition this install behaves as when no licence is verified. Free
    // is the honest default: an install with no key is a free install.
    'default' => env('GAMEMGR_EDITION', 'free'),

    /*
     * What a valid licence grants when it does not name an edition itself.
     *
     * The vendor response carries an "edition" field. If a key verifies but
     * says nothing about which edition it is for, refusing to grant anything
     * would punish a paying customer for a gap at the vendor's end, so the
     * benefit of the doubt goes to the customer and the status message says so.
     */
    'licensed_default' => env('GAMEMGR_LICENSED_EDITION', 'basic'),

    /*
     * The editions themselves, cheapest first. Order matters: "at least pro"
     * is decided by position in this list.
     *
     * null means unlimited. Setting a limit to 0 would mean "none at all",
     * which is never what an edition wants to say.
     *
     * ONE LIMIT: how many servers. Everything else is the same on every
     * edition, and that is deliberate.
     *
     * The server count already catches everybody it should. Anyone running this
     * commercially is past five servers on their first day, so a separate gate
     * on the API, on subusers or on importing a template only ever landed on
     * hobbyists, who were never going to pay and whose product was worse for
     * it. Gating subusers in particular produced shared passwords rather than
     * upgrades, which is a worse outcome for everybody including us.
     *
     * The features key is kept, and every edition holds every feature. Nothing
     * is gated by it today; it stays because removing the mechanism and later
     * wanting it back is a rebuild, while an unused list is a line of config.
     */
    'tiers' => [

        /*
         * Self-hosted, and unlimited.
         *
         * A limit on a panel somebody runs on their own machine is a limit they
         * can edit out of a config file, so it only ever taxed the honest. The
         * limits below are the HOSTED plans, where the servers are counted by a
         * panel we run and cannot be edited by the person being counted.
         */
        'free' => [
            'label' => 'Self-Hosted',
            // Nodes are not limited on any edition. Somebody who wants a second
            // machine has a reason for it, and telling them to pay for the
            // privilege of running their own hardware is a strange thing to
            // charge for.
            'nodes' => null,
            'servers' => null,
            /*
             * Every game, on every edition.
             *
             * Withholding games was the wrong line to draw. Somebody who wants
             * to run Rust and is told to pay first has not been shown what this
             * panel is worth; somebody running five servers and wanting a sixth
             * has. Scale and the integration features are what separate the
             * editions now, so an upgrade is bought out of growth rather than
             * out of frustration.
             *
             * Importing arbitrary eggs is still a paid feature, which is a
             * different question: that is "run anything on the internet", not
             * "run the games we ship".
             */
            'games' => null,
            'features' => ['subusers', 'backups.scheduled', 'api', 'templates.import', 'webhooks'],
            'support' => 'Community',
            'hosted' => false,
        ],

        'basic' => [
            'label' => 'Basic',
            'hosted' => true,
            'nodes' => null,
            'servers' => 25,
            // null means every game in the catalogue, but still not imported
            // eggs, which are their own feature below.
            'games' => null,
            'features' => ['subusers', 'backups.scheduled', 'api', 'templates.import', 'webhooks'],
            'support' => 'Email',
        ],

        'pro' => [
            'label' => 'Pro',
            'hosted' => true,
            'nodes' => null,
            'servers' => 250,
            'games' => null,
            'features' => ['subusers', 'backups.scheduled', 'api', 'templates.import', 'webhooks'],
            'support' => 'Priority email',
        ],

        'plus' => [
            'label' => 'Plus',
            'hosted' => true,
            'nodes' => null,
            'servers' => null,
            'games' => null,
            'features' => ['subusers', 'backups.scheduled', 'api', 'templates.import', 'webhooks'],
            'support' => 'Priority, with a real person',
        ],
    ],

    /*
     * Every gateable feature, and what it is called where a customer can see
     * it. A feature missing from this list is not gated at all.
     */
    'features' => [
        'subusers' => 'Inviting other people to a server',
        'backups.scheduled' => 'Scheduled backups',
        'api' => 'The application API',
        'templates.import' => 'Importing templates and eggs',
        'webhooks' => 'Webhooks',
    ],

    /*
     * Talking to scriptgain.com. Same contract as the other -MGR products, so
     * one vendor endpoint serves all of them and there is one signing key to
     * rotate rather than one per product.
     */
    'endpoint' => env('LICENCE_ENDPOINT', env('LICENSE_ENDPOINT', 'https://scriptgain.com/v1')),
    'product' => env('LICENCE_PRODUCT', 'gamemgr'),

    // A signed response is only accepted if it echoes the nonce this install
    // just generated AND was issued recently. Without both, one captured
    // "valid" response validates forever, on any number of installs, served
    // from a static file.
    'max_age_minutes' => (int) env('LICENCE_MAX_AGE_MINUTES', 10),
    'clock_skew_minutes' => (int) env('LICENCE_CLOCK_SKEW_MINUTES', 5),

    // How long a previously valid licence keeps working when scriptgain.com
    // cannot be reached. Generous on purpose: the customer has already paid,
    // and an outage at the vendor must not become an outage for them.
    'grace_days' => (int) env('LICENCE_GRACE_DAYS', 14),

    // How often to re-check online. Twice a day is plenty for something that
    // changes when somebody buys or cancels.
    'check_every_minutes' => (int) env('LICENCE_CHECK_MINUTES', 720),

    // scriptgain.com RSA-2048 public key, used to verify signed responses.
    'public_key' => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzFrRFiXb2ClbB+YDkOTj
vwMwJCZ1hC65IJ2rbLNM2zdUzMB/eT/MJ7iL5fFEWFCKytAoAuLr0Gofx2CE3u7y
WILwb+ZUT2eFNctFrWJiL737Cgh3Dx1tQmkveVZvs8elvZ+Kh2Gh8tEbKZ7pW+pl
dZwlHY4gBo3+YiAaYns9mcZuHDNO7Dm6Vn8B3hxYMzJ6lr/qoH/f+ZiT67Lcjzsl
O64X+7D4A0nBGBOVk6h0n8ZkoToXply6Qe0tUz8YWcJ4VJkAnFNlaDPDAl+E4EmL
B8CwKpuG6rsQaopXKP2K+XGXge9oOB25RCTKcQyB0hOqeu61pxwquUkC/iVyxPzH
jwIDAQAB
-----END PUBLIC KEY-----
PEM,
];
