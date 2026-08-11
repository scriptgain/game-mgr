<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Template;
use App\Models\TemplatePort;
use App\Models\TemplateVariable;
use App\Services\TemplateImporter;
use Illuminate\Database\Seeder;

/**
 * The games and templates a fresh install ships with.
 *
 * Chosen to cover all three runtimes rather than to be exhaustive: Minecraft and
 * ARK for Docker, Rust, CS2 and Palworld for SteamCMD, Valheim for LinuxGSM. The
 * point is that a new install can host something on day one, and that every
 * runtime has a working example to copy.
 *
 * Two things everything here has to respect. Every image keeps its files
 * somewhere specific and data_path has to agree with it, because a wrong path
 * does not fail: the server starts, looks healthy, and writes its world into a
 * layer that disappears on the next recreate. And a startup command is run
 * verbatim by /bin/sh, so it may be several statements but only the last one may
 * exec: an exec any earlier replaces the shell with that statement and drops
 * everything after it, which looks like a server that boots and vanishes.
 *
 * Startup commands here read their settings as ${VAR:-default} rather than the
 * {{VAR}} placeholders the Pterodactyl egg format uses, because nothing in
 * GameMGR has ever substituted those: a template written that way hands the game
 * the literal text. The defaults are chosen so that every command is correct on
 * its own, and picks up the panel's value the moment the runtime exports one.
 */
class CatalogueSeeder extends Seeder
{
    /** Templates left alone because an admin had edited them. */
    private array $skipped = [];

    public function run(): void
    {
        $this->hand();
        $this->library();

        if ($this->skipped) {
            $this->command?->warn('Left '.count($this->skipped).' customised template(s) alone: '.implode(', ', $this->skipped));
        }
    }

    /**
     * The vendored community library.
     *
     * Two hundred and fifty definitions live in database/catalogue as ordinary
     * files, fetched deliberately by gamemgr:fetch-catalogue and committed, so
     * a fresh install has a real catalogue without reaching the network and
     * every install has the same one.
     *
     * Runs AFTER the hand-written set and never touches a template that already
     * exists. The nine written by hand are better than their community
     * equivalents in the ways that matter here, with real port roles, startup
     * markers and prose descriptions, so where both describe the same thing the
     * hand-written one wins.
     */
    private function library(): void
    {
        $root = database_path('catalogue');
        if (! is_dir($root)) {
            return;
        }

        $importer = new TemplateImporter;
        $imported = 0;

        foreach (glob($root.'/*/*.json') ?: [] as $path) {
            $source = 'catalogue:'.basename(dirname($path)).'/'.basename($path);

            // Keyed on where it came from, so a re-seed is a no-op rather than
            // a second copy of all two hundred and fifty. This is the whole
            // reason the seeder can keep running on every deploy.
            if (Template::where('imported_from', $source)->exists()) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                continue;
            }

            try {
                $template = $importer->import($decoded, null, $source);
            } catch (\Throwable $e) {
                // One bad file must not stop the other two hundred and forty
                // nine. The fetcher validates before writing, so this is for
                // whatever the validator does not catch.
                $this->command?->warn('Skipped '.basename($path).': '.$e->getMessage());

                continue;
            }

            // A community template that duplicates a hand-written one is
            // dropped rather than left alongside it, because two entries called
            // "Valheim Dedicated" under one game is a worse catalogue than one.
            $duplicate = Template::where('game_id', $template->game_id)
                ->where('name', $template->name)
                ->whereNull('imported_from')
                ->exists();

            if ($duplicate) {
                $template->delete();

                continue;
            }

            // The category comes from the folder the definition was vendored into,
            // which is the only place that fact exists: a definition file never says
            // what kind of thing it is. Only filled when empty, so a category
            // an admin set by hand is not overwritten on the next seed.
            if ($template->game && blank($template->game->category)) {
                $template->game->update(['category' => match (basename(dirname($path))) {
                    'games-steamcmd', 'games-standalone' => 'game',
                    'minecraft' => 'minecraft',
                    'voice' => 'voice',
                    default => 'other',
                }]);
            }

            $imported++;
        }

        if ($imported) {
            $this->command?->info("Imported {$imported} template(s) from the vendored catalogue.");
        }
    }

    /** The hand-written set, which covers all three runtimes properly. */
    private function hand(): void
    {
        foreach ($this->catalogue() as $gameData) {
            $templates = $gameData['templates'];
            unset($gameData['templates']);

            $game = Game::updateOrCreate(['slug' => $gameData['slug']], $gameData);

            foreach ($templates as $t) {
                $vars = $t['variables'] ?? [];
                $ports = $t['ports'] ?? [];
                unset($t['variables'], $t['ports']);

                // A template somebody has edited is theirs, not ours.
                //
                // This used to be an unconditional updateOrCreate, which meant
                // every panel update silently reverted any change an admin had
                // made to a shipped template. It reverted the TF2 template's
                // licensed Steam login twice in one evening on gamemgr001, and
                // nothing anywhere said the update had done it.
                $existing = Template::where('game_id', $game->id)->where('name', $t['name'])->first();
                if ($existing?->customised_at) {
                    $this->skipped[] = $game->name.' / '.$t['name'];

                    continue;
                }

                $template = Template::updateOrCreate(
                    ['game_id' => $game->id, 'name' => $t['name']],
                    $t + ['game_id' => $game->id],
                );

                $this->ports($template, $ports);

                $sort = 0;
                $declared = [];
                foreach ($vars as $v) {
                    TemplateVariable::updateOrCreate(
                        ['template_id' => $template->id, 'env_variable' => $v['env_variable']],
                        $v + ['template_id' => $template->id, 'sort' => $sort++],
                    );
                    $declared[] = $v['env_variable'];
                }

                // updateOrCreate only ever adds, so a variable dropped from a
                // template used to stay in the database forever. That is not
                // cosmetic: a stale SERVER_JARFILE on an image that picks its own
                // jar is a field the client can fill in that changes nothing, and
                // the only way to find that out is to try it.
                $template->variables()->whereNotIn('env_variable', $declared)->delete();
            }
        }
    }

    /**
     * The set of listeners a game needs, written down instead of guessed at.
     *
     * Every one of these was verifiable from the template's own startup command
     * or the image it runs, and the numbers here are the ones the games and
     * their communities actually use. That is the point: a Palworld server is
     * reached on 8211 because that is what every guide says, and a panel that
     * hands out something else is making its users explain a port number to
     * everyone who wants to join.
     */
    private function ports(Template $template, array $ports): void
    {
        if (! $ports) {
            return;
        }

        $sort = 0;
        $declared = [];

        foreach ($ports as $p) {
            TemplatePort::updateOrCreate(
                ['template_id' => $template->id, 'role' => $p['role']],
                $p + [
                    'template_id' => $template->id,
                    'sort' => $sort++,
                    'required' => true,
                    'source' => isset($p['port']) ? 'fixed' : 'offset',
                ],
            );
            $declared[] = $p['role'];
        }

        // Same reason the variables are pruned: a port dropped from a template
        // that stays in the database is a hole the daemon keeps opening on a
        // firewall for a listener that is not there.
        $template->ports()->whereNotIn('role', $declared)->delete();

        // default_port, default_protocol and the two offsets are a mirror of
        // this set now. BootstrapNode and the template pages still read them.
        $template->load('ports');
        $template->syncPortColumns();
    }

    /**
     * The Minecraft server.properties settings a customer may edit.
     *
     * Key names are the real ones the game writes, which is not always the
     * obvious one: the whitelist flag in server.properties is "white-list",
     * not "whitelist", and a form pointed at "whitelist" would append a key
     * the game ignores and quietly do nothing.
     *
     * env matters as much as the key here. itzg/minecraft-server rewrites
     * server.properties from its environment on every boot, but only for the
     * properties it was actually given an environment variable for. So a
     * setting the template also exposes as a variable names it, and the panel
     * writes both, and the restart that applies the change does not undo it.
     * Anything with no env below is a property the image leaves alone.
     */
    private function minecraftProperties(): array
    {
        return [
            'file' => 'server.properties',
            'format' => 'properties',
            'label' => 'Server Properties',
            'description' => 'The file every Minecraft server reads at startup.',
            'settings' => [
                ['key' => 'motd', 'name' => 'MOTD', 'section' => 'Identity', 'env' => 'MOTD',
                    'default' => 'A GameMGR Server', 'rules' => 'nullable|string|max:120',
                    'description' => 'The line under the server name in the client list. Section signs for colour codes survive a save.'],
                ['key' => 'level-name', 'name' => 'World Folder', 'section' => 'Identity',
                    'default' => 'world', 'rules' => 'required|string|max:60', 'user_editable' => false,
                    'description' => 'Which folder on disk holds the world. Changing it here would start a brand new world and leave the old one orphaned, so use the Worlds tab instead.'],

                ['key' => 'gamemode', 'name' => 'Game Mode', 'section' => 'Gameplay', 'env' => 'MODE',
                    'default' => 'survival', 'rules' => 'required|in:survival,creative,adventure,spectator'],
                ['key' => 'difficulty', 'name' => 'Difficulty', 'section' => 'Gameplay', 'env' => 'DIFFICULTY',
                    'default' => 'normal', 'rules' => 'required|in:peaceful,easy,normal,hard'],
                ['key' => 'hardcore', 'name' => 'Hardcore', 'section' => 'Gameplay',
                    'default' => 'false', 'rules' => 'required|in:true,false',
                    'description' => 'Death is permanent and the difficulty is forced to hard. There is no undoing this for a player who dies.'],
                ['key' => 'pvp', 'name' => 'Player Versus Player', 'section' => 'Gameplay',
                    'default' => 'true', 'rules' => 'required|in:true,false'],
                ['key' => 'allow-flight', 'name' => 'Allow Flight', 'section' => 'Gameplay',
                    'default' => 'false', 'rules' => 'required|in:true,false',
                    'description' => 'Needed by most flight mods and plugins. With it off the server kicks anyone it thinks is flying.'],
                ['key' => 'enable-command-block', 'name' => 'Command Blocks', 'section' => 'Gameplay',
                    'default' => 'false', 'rules' => 'required|in:true,false'],
                ['key' => 'spawn-protection', 'name' => 'Spawn Protection', 'section' => 'Gameplay',
                    'default' => '16', 'rules' => 'required|integer|between:0,64',
                    'description' => 'Blocks around spawn that only operators can build in. Zero switches it off.'],

                ['key' => 'max-players', 'name' => 'Max Players', 'section' => 'Performance', 'env' => 'MAX_PLAYERS',
                    'default' => '20', 'rules' => 'required|integer|between:1,200'],
                ['key' => 'view-distance', 'name' => 'View Distance', 'section' => 'Performance',
                    'default' => '10', 'rules' => 'required|integer|between:3,32',
                    'description' => 'Chunks sent to each player. This is the single biggest lever on memory and CPU: every step up costs roughly the square of it.'],
                ['key' => 'simulation-distance', 'name' => 'Simulation Distance', 'section' => 'Performance',
                    'default' => '10', 'rules' => 'required|integer|between:3,32',
                    'description' => 'Chunks that keep ticking: mobs, crops and redstone. Dropping this below the view distance is the cheapest way to buy back tick time.'],

                ['key' => 'online-mode', 'name' => 'Online Mode', 'section' => 'Access', 'env' => 'ONLINE_MODE',
                    'default' => 'true', 'rules' => 'required|in:true,false',
                    'description' => 'Verifies every player against Mojang. Turning it off lets anyone join under any name, including yours.'],
                ['key' => 'white-list', 'name' => 'Whitelist', 'section' => 'Access',
                    'default' => 'false', 'rules' => 'required|in:true,false',
                    'description' => 'Only players on the whitelist may join. Manage the list itself on the Players tab.'],
                // Hidden from customers on purpose: the Players tab, the kick
                // and ban buttons and a clean save on shutdown all authenticate
                // with this, so a customer changing it breaks the panel rather
                // than their server, and they would have no way to know why.
                ['key' => 'rcon.password', 'name' => 'RCON Password', 'section' => 'Access', 'env' => 'RCON_PASSWORD',
                    'default' => '', 'rules' => 'nullable|alpha_dash|max:40', 'user_viewable' => false,
                    'description' => 'Used by the panel itself. Changing it without telling the panel breaks the Players tab.'],
            ],
        ];
    }

    /**
     * bukkit.yml, which only Paper and its relatives have. Included mostly
     * because it is the file people are told to tune when a server lags, and
     * because half of it is comments that no YAML round trip may eat.
     */
    private function bukkitYaml(): array
    {
        return [
            'file' => 'bukkit.yml',
            'format' => 'yaml',
            'label' => 'Bukkit Tuning',
            'description' => 'Spawn caps and save timing. Written by Paper on its first start.',
            'settings' => [
                ['key' => 'spawn-limits.monsters', 'name' => 'Monster Cap', 'section' => 'Spawn Limits',
                    'default' => '70', 'rules' => 'required|integer|between:0,200',
                    'description' => 'Hostile mobs per player before the server stops spawning more. The usual first thing to lower on a busy server.'],
                ['key' => 'spawn-limits.animals', 'name' => 'Animal Cap', 'section' => 'Spawn Limits',
                    'default' => '10', 'rules' => 'required|integer|between:0,100'],
                ['key' => 'spawn-limits.water-animals', 'name' => 'Water Animal Cap', 'section' => 'Spawn Limits',
                    'default' => '5', 'rules' => 'required|integer|between:0,100'],
                ['key' => 'ticks-per.autosave', 'name' => 'Autosave Interval', 'section' => 'Housekeeping',
                    'default' => '6000', 'rules' => 'required|integer|between:0,72000',
                    'description' => 'Ticks between world saves. 6000 is five minutes. Zero switches autosaving off, which is only sane if something else is saving for you.'],
                ['key' => 'settings.allow-end', 'name' => 'Allow the End', 'section' => 'Housekeeping',
                    'default' => 'true', 'rules' => 'required|in:true,false'],
            ],
        ];
    }

    /**
     * PalWorldSettings.ini. Every one of these lives inside the single
     * OptionSettings=(...) tuple, and the key names are the ones from the
     * game's own DefaultPalWorldSettings.ini rather than the friendlier names
     * the in-game menu shows.
     *
     * Two things this template's startup script forces on every boot, so they
     * are handled rather than pretended away: the keys it rewrites from the
     * environment name that variable in env, and PublicPort, which it takes
     * from the server's allocated port and which therefore nobody may type a
     * different answer into.
     */
    private function palworldSettings(): array
    {
        // Reused three times: Palworld writes booleans capitalised and reads
        // nothing else, and a "true" in that file is a setting it ignores.
        $bool = 'required|in:True,False';

        return [
            'file' => 'Pal/Saved/Config/LinuxServer/PalWorldSettings.ini',
            'format' => 'palworld',
            'label' => 'World Settings',
            'description' => 'Everything Palworld reads at boot, all of it on one line inside OptionSettings.',
            'settings' => [
                ['key' => 'ServerName', 'name' => 'Server Name', 'section' => 'Server', 'env' => 'SERVER_NAME',
                    'default' => 'A GameMGR Palworld Server', 'rules' => 'required|string|max:64|regex:/^[^,"()]+$/',
                    'description' => 'Shown in the in-game server browser. Commas, quotes and brackets are refused, because any one of them ends the settings line early and Palworld drops the whole file back to defaults.'],
                ['key' => 'ServerDescription', 'name' => 'Server Description', 'section' => 'Server', 'env' => 'SERVER_DESCRIPTION',
                    'default' => 'Hosted with GameMGR', 'rules' => 'nullable|string|max:128|regex:/^[^,"()]*$/',
                    'description' => 'The blurb under the server name. Same character restrictions as the name.'],
                ['key' => 'ServerPlayerMaxNum', 'name' => 'Max Players', 'section' => 'Server', 'env' => 'MAX_PLAYERS',
                    'default' => '32', 'rules' => 'required|integer|between:1,32',
                    'description' => 'Palworld caps at 32. Each player is roughly another 400 MiB.'],
                ['key' => 'PublicPort', 'name' => 'Public Port', 'section' => 'Server',
                    'default' => '8211', 'rules' => 'required|integer|between:1024,65535', 'user_editable' => false,
                    'description' => 'Taken from this server\'s allocated game port and rewritten on every start, so it is shown here rather than asked for. Change the allocation on the Network tab instead.'],
                ['key' => 'AdminPassword', 'name' => 'Admin Password', 'section' => 'Server', 'env' => 'ADMIN_PASSWORD',
                    'default' => '', 'rules' => 'nullable|alpha_dash|max:30',
                    'description' => 'Needed for in-game admin commands and for RCON. RCON stays switched off until this is set.'],
                ['key' => 'ServerPassword', 'name' => 'Server Password', 'section' => 'Server', 'env' => 'SERVER_PASSWORD',
                    'default' => '', 'rules' => 'nullable|alpha_dash|max:30',
                    'description' => 'Leave blank for an open server. This is what players type to join, not the admin password.'],

                ['key' => 'Difficulty', 'name' => 'Difficulty', 'section' => 'Rules',
                    'default' => 'None', 'rules' => 'required|in:None,Casual,Normal,Hard',
                    'description' => 'None means the individual rates below decide everything, which is what you want once you have touched any of them.'],
                ['key' => 'DeathPenalty', 'name' => 'Death Penalty', 'section' => 'Rules',
                    'default' => 'All', 'rules' => 'required|in:None,Item,ItemAndEquipment,All',
                    'description' => 'What a player drops on death. All includes the Pals on their team.'],
                ['key' => 'bIsPvP', 'name' => 'PvP', 'section' => 'Rules',
                    'default' => 'False', 'rules' => $bool],
                ['key' => 'bEnablePlayerToPlayerDamage', 'name' => 'Player Damage', 'section' => 'Rules',
                    'default' => 'False', 'rules' => $bool,
                    'description' => 'Whether players can hurt each other at all. PvP needs this on as well.'],
                ['key' => 'bEnableFriendlyFire', 'name' => 'Friendly Fire', 'section' => 'Rules',
                    'default' => 'False', 'rules' => $bool],
                ['key' => 'bEnableInvaderEnemy', 'name' => 'Raids', 'section' => 'Rules',
                    'default' => 'True', 'rules' => $bool,
                    'description' => 'Periodic attacks on player bases.'],
                ['key' => 'bEnableFastTravel', 'name' => 'Fast Travel', 'section' => 'Rules',
                    'default' => 'True', 'rules' => $bool],
                ['key' => 'GuildPlayerMaxNum', 'name' => 'Guild Size', 'section' => 'Rules',
                    'default' => '20', 'rules' => 'required|integer|between:1,100'],
                ['key' => 'BaseCampMaxNum', 'name' => 'Base Camp Limit', 'section' => 'Rules',
                    'default' => '128', 'rules' => 'required|integer|between:1,256',
                    'description' => 'Bases across the whole server. This one costs real memory on a busy world.'],

                ['key' => 'DayTimeSpeedRate', 'name' => 'Day Length', 'section' => 'Rates',
                    'default' => '1', 'rules' => 'required|numeric|between:0.1,5',
                    'description' => 'Higher runs the day faster, so a lower number means longer days.'],
                ['key' => 'NightTimeSpeedRate', 'name' => 'Night Length', 'section' => 'Rates',
                    'default' => '1', 'rules' => 'required|numeric|between:0.1,5',
                    'description' => 'Raise this to get the nights over with faster, which is the usual first change on a server people play after work.'],
                ['key' => 'ExpRate', 'name' => 'Experience Rate', 'section' => 'Rates',
                    'default' => '1', 'rules' => 'required|numeric|between:0.1,20'],
                ['key' => 'PalCaptureRate', 'name' => 'Pal Capture Rate', 'section' => 'Rates',
                    'default' => '1', 'rules' => 'required|numeric|between:0.5,2'],
                ['key' => 'PalSpawnNumRate', 'name' => 'Pal Spawn Rate', 'section' => 'Rates',
                    'default' => '1', 'rules' => 'required|numeric|between:0.5,3',
                    'description' => 'How many Pals are alive in the world at once. Raising this is the fastest way to make a server struggle.'],
                ['key' => 'WorkSpeedRate', 'name' => 'Work Speed', 'section' => 'Rates',
                    'default' => '1', 'rules' => 'required|numeric|between:0.1,5'],
                ['key' => 'CollectionDropRate', 'name' => 'Gathering Rate', 'section' => 'Rates',
                    'default' => '1', 'rules' => 'required|numeric|between:0.5,3'],
                ['key' => 'PalEggDefaultHatchingTime', 'name' => 'Egg Hatching Hours', 'section' => 'Rates',
                    'default' => '72', 'rules' => 'required|numeric|between:0,240',
                    'description' => 'In-game hours. Zero hatches an egg the moment it is placed.'],
            ],
        ];
    }

    private function catalogue(): array
    {
        return [
            [
                'name' => 'Minecraft',
                'slug' => 'minecraft',
                'description' => 'Java and Bedrock editions, vanilla through heavily modded.',
                'author' => 'GameMGR',
                'icon' => 'cube',
                'cover_color' => '#16a34a',
                'templates' => [
                    [
                        'name' => 'Paper',
                        'default_port' => 25565,
                        'default_protocol' => 'tcp',
                        // The query listener is the same number as the game port and a
                        // different protocol: Java play is TCP on 25565, the query protocol
                        // is UDP on 25565. One allocation, open on both, which is exactly
                        // what a single port column could never say.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'tcp', 'port' => 25565],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 0],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port' => 25575],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'High performance Spigot fork, run from itzg/minecraft-server, which downloads and upgrades Paper itself.',
                            'Ports: 25565/tcp game and 25565/udp query, plus 25575/tcp RCON inside the container.',
                            'About 500 MiB of image and 50 MiB of server jar to download, so this is by far the cheapest template here to prove a node with.',
                            'Budget 2 GiB of memory as a floor and 4 GiB for a comfortable twenty players.',
                        ]),
                        'runtime' => 'docker',
                        // A real, public image, so a fresh dev stack can boot
                        // a server without anyone having to build one first.
                        'docker_images' => ['Latest' => 'itzg/minecraft-server:latest', 'Java 17' => 'itzg/minecraft-server:java17'],
                        // itzg/minecraft-server keeps everything under /data, NOT
                        // the /home/container the egg format assumes. Getting this
                        // wrong does not fail: the server starts, and the world
                        // goes into the container's writable layer instead of the
                        // volume, where it disappears on the next recreate.
                        'data_path' => '/data',
                        // The image's own entrypoint. /start is a one line shim
                        // that execs /image/scripts/start, and it is kept rather
                        // than the real path because the older java17 tag only has
                        // the shim. Single statement, so exec is safe here.
                        'startup' => 'exec /start',
                        'config_startup' => ['done' => ')! For help, type ', 'strip_ansi' => true],
                        'config_stop' => ['value' => 'stop'],
                        'config_logs' => ['custom' => false, 'location' => 'logs/latest.log'],
                        'config_files' => ['server.properties' => ['parser' => 'properties', 'find' => ['server-ip' => '{{server.build.default.ip}}', 'server-port' => '{{server.build.default.port}}']]],
                        // config_files is the daemon stamping the allocation
                        // into the file before boot. config_schema is the Config
                        // tab: which settings inside that file a customer sees.
                        'config_schema' => [$this->minecraftProperties(), $this->bukkitYaml()],
                        // Carrying this document is the ONLY thing that makes a
                        // template Minecraft as far as the panel is concerned,
                        // and it is what turns the type and version boxes into
                        // the MCJars picker. The keys of `builds` are the types
                        // offered; the value is the variable that pins a build
                        // for that type, which differs per project and is null
                        // where the image has nowhere to put one.
                        'mcjars' => [
                            'type_variable' => 'TYPE',
                            'version_variable' => 'VERSION',
                            'builds' => [
                                'PAPER' => 'PAPER_BUILD',
                                'PURPUR' => 'PURPUR_BUILD',
                                'FOLIA' => 'FOLIABUILD',
                                'PUFFERFISH' => 'PUFFERFISH_BUILD',
                                'SPIGOT' => null,
                                'VANILLA' => null,
                            ],
                        ],
                        'features' => ['eula', 'java_version', 'pid_limit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'minecraft',
                        'query_protocol' => 'minecraft',
                        // Minecraft's own defaults: RCON on 25575 against a game
                        // port of 25565, and the query on the game port itself.
                        'rcon_port_offset' => 10,
                        'query_port_offset' => 0,
                        // Hangar is PaperMC's own plugin repository, so it
                        // belongs on the Paper family before the two that need
                        // a key or have no official API.
                        'mod_sources' => ['modrinth', 'hangar', 'spigot', 'curseforge'],
                        'curseforge_game_id' => 432,
                        'update_command' => 'docker pull itzg/minecraft-server:latest',
                        'variables' => [
                            ['name' => 'Accept the Minecraft EULA', 'env_variable' => 'EULA', 'default_value' => 'TRUE', 'rules' => 'required|in:TRUE,true', 'description' => 'Mojang requires this. Without it the container prints the EULA notice and exits immediately, which reads as a crash loop.', 'user_viewable' => true, 'user_editable' => false],
                            // The type is a choice, not a lock. It stays inside
                            // the Paper family plus the two plain servers that
                            // read the same config files, so the Config tab
                            // never describes files this server will not write.
                            ['name' => 'Server Type', 'env_variable' => 'TYPE', 'default_value' => 'PAPER', 'rules' => 'required|in:PAPER,PURPUR,FOLIA,PUFFERFISH,SPIGOT,VANILLA', 'description' => 'Which server software the image downloads. Picked from the live MCJars catalogue.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Minecraft Version', 'env_variable' => 'VERSION', 'default_value' => 'LATEST', 'rules' => 'required|string|max:40', 'description' => 'LATEST tracks the newest release of the chosen type. Anything else pins that version.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Paper Build', 'env_variable' => 'PAPER_BUILD', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Leave blank for the newest build of the chosen version. A number pins one build.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Purpur Build', 'env_variable' => 'PURPUR_BUILD', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Purpur. Blank means the newest build of the chosen version.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Folia Build', 'env_variable' => 'FOLIABUILD', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Folia. No underscore: that is the name the image uses.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Pufferfish Build', 'env_variable' => 'PUFFERFISH_BUILD', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Pufferfish. Blank means the newest build.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Java Heap', 'env_variable' => 'MEMORY', 'default_value' => '75%', 'rules' => 'required|string|max:10', 'description' => 'A percentage of the container limit, which is what keeps the heap under the cap. An absolute size equal to the cap gets the server OOM killed rather than throwing, because the JVM needs metaspace and threads on top of the heap.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Aikar Flags', 'env_variable' => 'USE_AIKAR_FLAGS', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'description' => 'The garbage collector tuning the Paper community settled on. Worth leaving on.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '20', 'rules' => 'required|integer|between:1,200', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'MOTD', 'env_variable' => 'MOTD', 'default_value' => 'A GameMGR Paper Server', 'rules' => 'nullable|string|max:120', 'description' => 'The line under the server name in the client list.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Difficulty', 'env_variable' => 'DIFFICULTY', 'default_value' => 'normal', 'rules' => 'required|in:peaceful,easy,normal,hard', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Game Mode', 'env_variable' => 'MODE', 'default_value' => 'survival', 'rules' => 'required|in:survival,creative,adventure,spectator', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Online Mode', 'env_variable' => 'ONLINE_MODE', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'description' => 'Verifies players against Mojang. Turning it off lets anyone join under any name.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Operators', 'env_variable' => 'OPS', 'default_value' => '', 'rules' => 'nullable|string|max:500', 'description' => 'Comma separated usernames granted operator on first boot.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Enabled', 'env_variable' => 'ENABLE_RCON', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'description' => 'The Players tab, the console kick and ban buttons and a clean save on shutdown all go through RCON.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'RCON Password', 'env_variable' => 'RCON_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'Leave blank and the image generates one on first boot. Set it if something outside GameMGR needs to log in.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                    [
                        'name' => 'Forge',
                        'default_port' => 25565,
                        'default_protocol' => 'tcp',
                        // Same shape as Paper: TCP play and UDP query on 25565, RCON on the
                        // conventional 25575.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'tcp', 'port' => 25565],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 0],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port' => 25575],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'Modded Minecraft on the same itzg/minecraft-server image, which runs the Forge installer for the version pair below.',
                            'Ports: 25565/tcp game and 25565/udp query, plus 25575/tcp RCON inside the container.',
                            'The image is about 500 MiB; a large modpack adds 1 to 4 GiB on top.',
                            'Budget 6 GiB of memory as a floor and 8 to 12 GiB for anything the size of All The Mods, and expect a first boot of several minutes while Forge installs.',
                        ]),
                        'runtime' => 'docker',
                        'docker_images' => ['Latest' => 'itzg/minecraft-server:latest', 'Java 17' => 'itzg/minecraft-server:java17'],
                        'data_path' => '/data',
                        'startup' => 'exec /start',
                        'config_startup' => ['done' => ')! For help, type ', 'strip_ansi' => true],
                        'config_stop' => ['value' => 'stop'],
                        'config_logs' => ['custom' => false, 'location' => 'logs/latest.log'],
                        'config_files' => ['server.properties' => ['parser' => 'properties', 'find' => ['server-ip' => '{{server.build.default.ip}}', 'server-port' => '{{server.build.default.port}}']]],
                        // No bukkit.yml here: Forge is not a Bukkit server, and
                        // offering a tab for a file that will never exist is worse
                        // than not offering one.
                        'config_schema' => [$this->minecraftProperties()],
                        // The four mod loaders, kept apart from the Paper list
                        // on purpose: a template is a promise about what a
                        // server is, and one dropdown offering both families
                        // would have a Config tab describing files half of its
                        // own choices never write.
                        'mcjars' => [
                            'type_variable' => 'TYPE',
                            'version_variable' => 'VERSION',
                            'builds' => [
                                'FORGE' => 'FORGE_VERSION',
                                'NEOFORGE' => 'NEOFORGE_VERSION',
                                'FABRIC' => 'FABRIC_LOADER_VERSION',
                                'QUILT' => 'QUILT_LOADER_VERSION',
                            ],
                        ],
                        'features' => ['eula', 'java_version', 'pid_limit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'minecraft',
                        'query_protocol' => 'minecraft',
                        // Same offsets as Paper. They were zero here, which meant
                        // two Minecraft templates disagreed about where RCON lives.
                        'rcon_port_offset' => 10,
                        'query_port_offset' => 0,
                        'mod_sources' => ['curseforge', 'modrinth'],
                        'curseforge_game_id' => 432,
                        'update_command' => 'docker pull itzg/minecraft-server:latest',
                        'variables' => [
                            ['name' => 'Accept the Minecraft EULA', 'env_variable' => 'EULA', 'default_value' => 'TRUE', 'rules' => 'required|in:TRUE,true', 'description' => 'Mojang requires this. Without it the container prints the EULA notice and exits immediately.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'Server Type', 'env_variable' => 'TYPE', 'default_value' => 'FORGE', 'rules' => 'required|in:FORGE,NEOFORGE,FABRIC,QUILT', 'description' => 'Which mod loader the image installs. Picked from the live MCJars catalogue.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Minecraft Version', 'env_variable' => 'VERSION', 'default_value' => '1.20.1', 'rules' => 'required|string|max:40', 'description' => 'Pinned rather than LATEST, because a loader lags Minecraft by weeks and the pair has to match.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Forge Version', 'env_variable' => 'FORGE_VERSION', 'default_value' => '47.3.0', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Forge. Blank means the newest build for the Minecraft version above.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'NeoForge Version', 'env_variable' => 'NEOFORGE_VERSION', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is NeoForge. Blank means the newest build for the chosen Minecraft version.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Fabric Loader Version', 'env_variable' => 'FABRIC_LOADER_VERSION', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Fabric. Blank means the newest loader.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Quilt Loader Version', 'env_variable' => 'QUILT_LOADER_VERSION', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Quilt. Blank means the newest loader.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Java Heap', 'env_variable' => 'MEMORY', 'default_value' => '75%', 'rules' => 'required|string|max:10', 'description' => 'A percentage of the container limit. Modded packs want the headroom the remaining quarter gives the JVM.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Aikar Flags', 'env_variable' => 'USE_AIKAR_FLAGS', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '10', 'rules' => 'required|integer|between:1,100', 'description' => 'Modded play is far heavier per player than vanilla.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'MOTD', 'env_variable' => 'MOTD', 'default_value' => 'A GameMGR Forge Server', 'rules' => 'nullable|string|max:120', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Difficulty', 'env_variable' => 'DIFFICULTY', 'default_value' => 'normal', 'rules' => 'required|in:peaceful,easy,normal,hard', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Allow Flight', 'env_variable' => 'ALLOW_FLIGHT', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'description' => 'On by default here: half of modded play involves jetpacks and elytra, and leaving it off kicks those players for flying.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Online Mode', 'env_variable' => 'ONLINE_MODE', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Operators', 'env_variable' => 'OPS', 'default_value' => '', 'rules' => 'nullable|string|max:500', 'description' => 'Comma separated usernames granted operator on first boot.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Enabled', 'env_variable' => 'ENABLE_RCON', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'RCON Password', 'env_variable' => 'RCON_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'Leave blank and the image generates one on first boot.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                    [
                        'name' => 'Bedrock',
                        'default_port' => 19132,
                        'default_protocol' => 'udp',
                        // Bedrock is UDP only and answers its query on the game port. It has
                        // no RCON at all, which is why the Players tab reads the query.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 19132],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 0],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'Mojang bedrock_server for console, mobile and Windows 10 players, run from itzg/minecraft-bedrock-server.',
                            'Ports: 19132/udp only. Bedrock is UDP, so a TCP-only firewall rule produces a server nobody can see.',
                            'About 120 MiB of image plus a 120 MiB server download, and it upgrades itself on every restart.',
                            'Budget 1 GiB of memory as a floor and 2 GiB for ten players.',
                            'There is no RCON and no Java-style query, so the Players tab has nothing to talk to.',
                        ]),
                        'runtime' => 'docker',
                        'docker_images' => ['Latest' => 'itzg/minecraft-bedrock-server:latest'],
                        // This image also keeps its world under /data. It was set
                        // to /home/container, which is the silent version of this
                        // failure: a healthy server writing into a throwaway layer.
                        'data_path' => '/data',
                        // The image's entrypoint, which demotes to the account
                        // owning /data before running the server. Single statement,
                        // so exec is safe and gives the server pid 1 and therefore
                        // the SIGTERM on stop.
                        'startup' => 'exec /opt/demoter-entry.sh',
                        'config_startup' => ['done' => 'Server started.', 'strip_ansi' => true],
                        'config_stop' => ['value' => 'stop'],
                        // bedrock_server writes to stdout only, so the container
                        // log is the whole log.
                        'config_logs' => ['custom' => false],
                        'rcon_supported' => false,
                        'query_protocol' => 'minecraft',
                        'query_port_offset' => 0,
                        'features' => ['eula'],
                        'update_command' => 'docker pull itzg/minecraft-bedrock-server:latest',
                        'variables' => [
                            ['name' => 'Accept the Minecraft EULA', 'env_variable' => 'EULA', 'default_value' => 'TRUE', 'rules' => 'required|in:TRUE,true', 'description' => 'Mojang requires this. Without it the container exits before the server starts.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'Version', 'env_variable' => 'VERSION', 'default_value' => 'LATEST', 'rules' => 'required|string|max:20', 'description' => 'LATEST re-checks on every restart. PREVIEW follows the beta channel, or pin an exact version such as 1.21.44.01.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR Bedrock Server', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Game Mode', 'env_variable' => 'GAMEMODE', 'default_value' => 'survival', 'rules' => 'required|in:survival,creative,adventure', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Difficulty', 'env_variable' => 'DIFFICULTY', 'default_value' => 'easy', 'rules' => 'required|in:peaceful,easy,normal,hard', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Allow Cheats', 'env_variable' => 'ALLOW_CHEATS', 'default_value' => 'false', 'rules' => 'required|in:true,false', 'description' => 'Needed for /gamemode and the rest of the operator commands.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '10', 'rules' => 'required|integer|between:1,100', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Online Mode', 'env_variable' => 'ONLINE_MODE', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'description' => 'Requires an Xbox Live sign in. Turning it off is what LAN-only setups do.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Allow List', 'env_variable' => 'ALLOW_LIST', 'default_value' => 'false', 'rules' => 'required|in:true,false', 'description' => 'Bedrock\'s whitelist. Names go in allowlist.json in the file manager.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Level Name', 'env_variable' => 'LEVEL_NAME', 'default_value' => 'Bedrock level', 'rules' => 'required|string|max:60', 'description' => 'The folder under worlds/ this save lives in. Changing it starts a new world rather than renaming the old one.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Level Seed', 'env_variable' => 'LEVEL_SEED', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only applies when the world is first generated.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'View Distance', 'env_variable' => 'VIEW_DISTANCE', 'default_value' => '10', 'rules' => 'required|integer|between:4,64', 'description' => 'Chunks. This is the single biggest lever on memory and CPU here.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Rust',
                'slug' => 'rust',
                'description' => 'Facepunch survival. Wipes hard, needs a lot of memory.',
                'author' => 'GameMGR',
                'icon' => 'fire',
                'cover_color' => '#b45309',
                'templates' => [
                    [
                        'name' => 'Rust Vanilla',
                        'default_port' => 28015,
                        'default_protocol' => 'udp',
                        // Rust defines RCON and query relative to the game port, so those are
                        // offsets and follow it if the port is ever changed. Rust+ is a fixed
                        // number Facepunch chose and is optional: a server without it works,
                        // it just cannot be paired with the companion app.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 28015],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port_offset' => 1],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 2],
                            ['role' => 'rustplus', 'label' => 'Rust+ Companion App', 'protocol' => 'tcp', 'port' => 28082, 'required' => false],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'SteamCMD native, app 258550, anonymous login. No container, because Rust is happier owning its own network stack.',
                            'Ports: 28015/udp game, 28016/tcp RCON, 28017/udp Steam query, 28082/tcp for the Rust+ companion app.',
                            'About 15 GiB to download and it patches every Thursday, so leave auto-update on.',
                            'Budget 8 GiB of memory as a hard floor at a 3000 world and 16 GiB at 4500 or above; the map is generated in memory at boot, which is the peak.',
                            'Boots in five to ten minutes the first time while it generates the map.',
                        ]),
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 258550,
                        'steam_anonymous' => true,

                        // Multi-statement on purpose, and POSIX: the supervisor
                        // writes this verbatim to a launcher script and runs it
                        // with /bin/sh, so only the final line may exec. An exec
                        // earlier would replace the shell with that one statement
                        // and drop everything after it.
                        //
                        // {{SERVER_PORT}} style placeholders were used here before
                        // and nothing has ever substituted them, so RustDedicated
                        // was being handed the literal text. ${VAR:-default} is
                        // both a real shell expansion and a working default.
                        'startup' => <<<'SH'
                        # Rust ships its own copies of libstdc++ and friends. Without this
                        # the binary starts against the host's and dies on a symbol lookup.
                        export LD_LIBRARY_PATH="$PWD/RustDedicated_Data/Plugins/x86_64:${LD_LIBRARY_PATH:-}"

                        # LinuxGSM's port layout, which is what every Rust admin tool assumes:
                        # game on P, RCON on P+1, Steam query on P+2, Rust+ on P+67.
                        P=${SERVER_PORT:-28015}

                        set -- -batchmode -nographics \
                          +server.ip 0.0.0.0 +server.port "$P" +server.queryport $((P + 2)) \
                          +app.listenip 0.0.0.0 +app.port $((P + 67)) \
                          +server.identity "${SERVER_IDENTITY:-gamemgr}" \
                          +server.hostname "${SERVER_NAME:-A GameMGR Rust Server}" \
                          +server.description "${SERVER_DESCRIPTION:-Hosted with GameMGR}" \
                          +server.level "${WORLD_LEVEL:-Procedural Map}" \
                          +server.worldsize "${WORLD_SIZE:-3000}" \
                          +server.seed "${WORLD_SEED:-1234}" \
                          +server.maxplayers "${MAX_PLAYERS:-50}" \
                          +server.saveinterval "${SAVE_INTERVAL:-300}" \
                          +server.tickrate "${TICKRATE:-30}"

                        # RCON only comes up when there is a password to authenticate against:
                        # an admin port nothing can log in to is worse than no admin port.
                        # rcon.web 0 selects the Source RCON protocol, which is what this
                        # template declares; 1 would be Facepunch's websocket and the panel
                        # would fail to connect while everything looked configured.
                        if [ -n "${RCON_PASSWORD:-}" ]; then
                        set -- "$@" +rcon.ip 0.0.0.0 +rcon.port $((P + 1)) +rcon.password "$RCON_PASSWORD" +rcon.web 0
                        fi

                        # No -logfile: it would send the console to a file and leave the
                        # supervisor's capture, and therefore the Console tab, empty.
                        exec ./RustDedicated "$@"
                        SH,
                        'config_startup' => ['done' => 'Server startup complete', 'strip_ansi' => true],
                        'config_stop' => ['value' => 'quit'],
                        'config_logs' => ['custom' => false],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        'rcon_port_offset' => 1,
                        // Rust's query does not sit on the game port. It was left
                        // at zero, which pointed the status page at the game port
                        // and reported every healthy server as unreachable.
                        'query_port_offset' => 2,
                        // Vanilla Rust has no mod ecosystem at all; the Mods tab
                        // belongs on the Oxide template, not this one.
                        'mod_sources' => null,
                        'update_command' => 'app_update 258550 validate',
                        'variables' => [
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR Rust Server', 'rules' => 'required|string|max:60', 'description' => 'Shown in the server browser. Named SERVER_NAME rather than HOSTNAME on purpose: HOSTNAME is already set in most process environments and would silently win.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Description', 'env_variable' => 'SERVER_DESCRIPTION', 'default_value' => 'Hosted with GameMGR', 'rules' => 'nullable|string|max:200', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Identity', 'env_variable' => 'SERVER_IDENTITY', 'default_value' => 'gamemgr', 'rules' => 'required|alpha_dash|max:32', 'description' => 'The folder under server/ that holds the save. Changing it starts a fresh wipe and leaves the old world on disk.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'World Level', 'env_variable' => 'WORLD_LEVEL', 'default_value' => 'Procedural Map', 'rules' => 'required|in:Procedural Map,Barren,HapisIsland,SavasIsland', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Size', 'env_variable' => 'WORLD_SIZE', 'default_value' => '3000', 'rules' => 'required|integer|between:1000,6000', 'description' => 'Metres across. Memory scales with the square of this, so 6000 needs roughly four times what 3000 does.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Seed', 'env_variable' => 'WORLD_SEED', 'default_value' => '1234', 'rules' => 'required|integer|between:1,2147483647', 'description' => 'The same seed and size always generate the same map.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '50', 'rules' => 'required|integer|between:1,500', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Save Interval', 'env_variable' => 'SAVE_INTERVAL', 'default_value' => '300', 'rules' => 'required|integer|between:60,3600', 'description' => 'Seconds between world saves. This is how much progress a crash costs.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Tick Rate', 'env_variable' => 'TICKRATE', 'default_value' => '30', 'rules' => 'required|integer|between:15,100', 'description' => 'Facepunch supports 30. Higher numbers are a CPU bill, not a free upgrade.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Password', 'env_variable' => 'RCON_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'RCON stays switched off until this is set. Once set it listens on the game port plus one, over TCP.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                    [
                        'name' => 'Rust Oxide',
                        'default_port' => 28015,
                        'default_protocol' => 'udp',
                        // Identical to vanilla. Oxide changes what runs, not what listens.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 28015],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port_offset' => 1],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 2],
                            ['role' => 'rustplus', 'label' => 'Rust+ Companion App', 'protocol' => 'tcp', 'port' => 28082, 'required' => false],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'Rust with the Oxide/uMod plugin framework, applied over the Steam files on every boot.',
                            'That is not laziness: Oxide is not part of any Steam depot, and the app_update validate this runtime uses on install and update deletes anything that is not, so a one-off install would vanish at the first update.',
                            'Same ports and the same appetite as Rust Vanilla: 28015/udp game, 28016/tcp RCON, 28017/udp query, 28082/tcp Rust+, about 15 GiB down and 8 GiB of memory as a floor.',
                            'The node needs curl and unzip, which is the one dependency a bare SteamCMD node often lacks.',
                        ]),
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 258550,
                        'steam_anonymous' => true,
                        'startup' => <<<'SH'
                        # Oxide is reapplied before every boot rather than installed once,
                        # because the runtime's app_update runs with validate and validate
                        # deletes every file Steam did not put there.
                        if [ "${OXIDE_UPDATE:-true}" = true ]; then
                        if command -v unzip >/dev/null 2>&1 && command -v curl >/dev/null 2>&1; then
                        echo "[gamemgr] applying Oxide from ${OXIDE_URL:-https://umod.org/games/rust/download}"
                        curl -fsSL -o .oxide.zip "${OXIDE_URL:-https://umod.org/games/rust/download}" \
                          && unzip -oq .oxide.zip -d . && rm -f .oxide.zip \
                          || echo "[gamemgr] Oxide download failed, starting on vanilla files"
                        else
                        echo "[gamemgr] curl and unzip are needed to apply Oxide and one of them is missing"
                        fi
                        fi

                        export LD_LIBRARY_PATH="$PWD/RustDedicated_Data/Plugins/x86_64:${LD_LIBRARY_PATH:-}"

                        P=${SERVER_PORT:-28015}

                        set -- -batchmode -nographics \
                          +server.ip 0.0.0.0 +server.port "$P" +server.queryport $((P + 2)) \
                          +app.listenip 0.0.0.0 +app.port $((P + 67)) \
                          +server.identity "${SERVER_IDENTITY:-gamemgr}" \
                          +server.hostname "${SERVER_NAME:-A GameMGR Oxide Server}" \
                          +server.description "${SERVER_DESCRIPTION:-Hosted with GameMGR}" \
                          +server.level "${WORLD_LEVEL:-Procedural Map}" \
                          +server.worldsize "${WORLD_SIZE:-3000}" \
                          +server.seed "${WORLD_SEED:-1234}" \
                          +server.maxplayers "${MAX_PLAYERS:-50}" \
                          +server.saveinterval "${SAVE_INTERVAL:-300}" \
                          +server.tickrate "${TICKRATE:-30}"

                        if [ -n "${RCON_PASSWORD:-}" ]; then
                        set -- "$@" +rcon.ip 0.0.0.0 +rcon.port $((P + 1)) +rcon.password "$RCON_PASSWORD" +rcon.web 0
                        fi

                        exec ./RustDedicated "$@"
                        SH,
                        'config_startup' => ['done' => 'Server startup complete', 'strip_ansi' => true],
                        'config_stop' => ['value' => 'quit'],
                        'config_logs' => ['custom' => false],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        'rcon_port_offset' => 1,
                        'query_port_offset' => 2,
                        // Oxide plugins come from umod.org, which the Mods tab has
                        // no client for. "manual" is the honest answer: they are
                        // uploaded into oxide/plugins and tracked, not searched.
                        'mod_sources' => ['manual'],
                        'update_command' => 'app_update 258550 validate',
                        'variables' => [
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR Oxide Server', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Description', 'env_variable' => 'SERVER_DESCRIPTION', 'default_value' => 'Hosted with GameMGR', 'rules' => 'nullable|string|max:200', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Identity', 'env_variable' => 'SERVER_IDENTITY', 'default_value' => 'gamemgr', 'rules' => 'required|alpha_dash|max:32', 'description' => 'The folder under server/ that holds the save and the Oxide data files.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'World Level', 'env_variable' => 'WORLD_LEVEL', 'default_value' => 'Procedural Map', 'rules' => 'required|in:Procedural Map,Barren,HapisIsland,SavasIsland', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Size', 'env_variable' => 'WORLD_SIZE', 'default_value' => '3000', 'rules' => 'required|integer|between:1000,6000', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Seed', 'env_variable' => 'WORLD_SEED', 'default_value' => '1234', 'rules' => 'required|integer|between:1,2147483647', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '50', 'rules' => 'required|integer|between:1,500', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Save Interval', 'env_variable' => 'SAVE_INTERVAL', 'default_value' => '300', 'rules' => 'required|integer|between:60,3600', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Tick Rate', 'env_variable' => 'TICKRATE', 'default_value' => '30', 'rules' => 'required|integer|between:15,100', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Password', 'env_variable' => 'RCON_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'RCON stays switched off until this is set. Oxide plugins that expect RCON need it too.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Reapply Oxide On Boot', 'env_variable' => 'OXIDE_UPDATE', 'default_value' => 'true', 'rules' => 'required|in:true,false', 'description' => 'Leave this on. Turning it off keeps the currently installed build, which only survives until the next Steam update runs validate over it.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Oxide Download URL', 'env_variable' => 'OXIDE_URL', 'default_value' => 'https://umod.org/games/rust/download', 'rules' => 'required|url|max:200', 'description' => 'Point this at a pinned build if a new Oxide release breaks your plugins.', 'user_viewable' => true, 'user_editable' => false],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Valheim',
                'slug' => 'valheim',
                'description' => 'Norse survival co-op. Small player counts, long uptimes.',
                'author' => 'GameMGR',
                'icon' => 'shield',
                'cover_color' => '#0e7490',
                'templates' => [
                    [
                        'name' => 'Valheim Dedicated',
                        'default_port' => 2456,
                        'default_protocol' => 'udp',
                        // Valheim genuinely defines its A2S port as the game port plus one, so
                        // this one is an offset rather than a number: move the game port and
                        // the query follows it, which is what the game does.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 2456],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 1],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'LinuxGSM vhserver, Steam app 896660, anonymous login. LinuxGSM does the install, the update and the console, so this runtime drives its control script rather than wrapping it in a second supervisor.',
                            'Ports: 2456/udp game and 2457/udp Steam query. Both are UDP; Valheim has no TCP listener and no RCON at all.',
                            'About 1.5 GiB to download, which makes this the cheapest way to prove the LinuxGSM runtime on a real node.',
                            'Budget 2 GiB of memory as a floor and 4 GiB for a full ten players; a long-lived world grows the save rather than the memory.',
                            'LinuxGSM reads its own configuration file, not the environment, so the settings below are the values to put in lgsm/config-lgsm/vhserver/vhserver.cfg through the file manager. Each one names the key it maps to.',
                        ]),
                        'runtime' => 'linuxgsm',
                        'lgsm_shortname' => 'vhserver',
                        'steam_app_id' => 896660,
                        // The LinuxGSM driver calls the control script itself, so
                        // these two are what the panel displays rather than what it
                        // executes. They are kept accurate anyway: an operator on
                        // the box runs exactly these.
                        'startup' => './vhserver start',
                        'config_startup' => ['done' => 'Game server connected', 'strip_ansi' => true],
                        'config_stop' => ['value' => './vhserver stop'],
                        // LinuxGSM keeps its own console capture under
                        // log/console/vhserver-console.log, which the driver reads
                        // instead of making a second one.
                        'config_logs' => ['custom' => true, 'location' => 'log/console/vhserver-console.log'],
                        'rcon_supported' => false,
                        'query_protocol' => 'a2s',
                        // Valheim answers A2S on the game port plus one.
                        'query_port_offset' => 1,
                        'mod_sources' => null,
                        'update_command' => './vhserver update',
                        'variables' => [
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR Valheim Server', 'rules' => 'required|string|max:60', 'description' => 'LinuxGSM key: servername.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Name', 'env_variable' => 'WORLD_NAME', 'default_value' => 'Midgard', 'rules' => 'required|alpha_dash|max:32', 'description' => 'LinuxGSM key: worldname. Names the .db and .fwl save pair. Changing it starts a new world and leaves the old one on disk.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Password', 'env_variable' => 'SERVER_PASSWORD', 'default_value' => 'changeme', 'rules' => 'required|string|min:5|max:32', 'description' => 'LinuxGSM key: serverpassword. Valheim refuses to start without one of at least five characters, and it may not appear inside the server name.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Public Listing', 'env_variable' => 'PUBLIC', 'default_value' => '1', 'rules' => 'required|in:0,1', 'description' => 'LinuxGSM key: public. 1 lists the server in the community browser, 0 makes it join-by-IP only.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Save Interval', 'env_variable' => 'SAVE_INTERVAL', 'default_value' => '1800', 'rules' => 'required|integer|between:60,7200', 'description' => 'LinuxGSM key: saveinterval, in seconds. This is how much progress a crash costs.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Backups', 'env_variable' => 'BACKUPS', 'default_value' => '4', 'rules' => 'required|integer|between:0,20', 'description' => 'LinuxGSM key: backups. Valheim\'s own rolling world backups, which are separate from GameMGR backups.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Modifiers', 'env_variable' => 'WORLD_MODIFIERS', 'default_value' => '', 'rules' => 'nullable|string|max:200', 'description' => 'LinuxGSM key: worldmodifiers. For example "-preset hard" or "-modifier raids none". Removing a modifier does not reset it: it is stored in the .fwl and needs "-preset normal" once to clear.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'ARK: Survival Ascended',
                'slug' => 'ark-survival-ascended',
                'description' => 'Dinosaurs, and an appetite for RAM that has to be seen to be believed. Windows-only server, so on Linux it runs under Proton.',
                'author' => 'GameMGR',
                'icon' => 'bolt',
                'cover_color' => '#7c2d12',
                'templates' => [
                    [
                        'name' => 'ARK ASA',
                        'default_port' => 7777,
                        'default_protocol' => 'udp',
                        // Survival Ascended dropped the separate query port its predecessor
                        // had and answers on the game port. RCON is the image's own 27020.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 7777],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 0],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port' => 27020],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'Docker, because there is no other option: Studio Wildcard never shipped a Linux server for Survival Ascended, and LinuxGSM has no asaserver, only arkserver for the older Survival Evolved.',
                            'This template used to claim a LinuxGSM shortname that does not exist, which meant the install could only ever fail.',
                            'acekorneya/asa_server runs the Windows server binaries under Proton and handles the SteamCMD download, the update and the RCON-driven graceful save itself.',
                            'Ports: 7777/tcp and 7777/udp game, 27020/tcp RCON. Survival Ascended answers Steam queries on the game port, so there is no separate query port.',
                            'About 3.5 GiB of image plus roughly 15 GiB of game files, and it is slow: expect fifteen minutes or more to a first join.',
                            'Budget 16 GiB of memory as a hard floor for one map and treat that as the whole machine, not a share of it. This is not a template to test alongside anything else.',
                            'The container runs as uid 7777, so the data directory has to be owned by 7777 before it will start.',
                        ]),
                        'runtime' => 'docker',
                        'docker_images' => ['Proton (2.1)' => 'acekorneya/asa_server:2_1_latest', 'Proton (2.0)' => 'acekorneya/asa_server:2_0_latest'],
                        // Everything the image keeps, including ShooterGame/Saved,
                        // lives under this one path, so a single bind mount here
                        // captures the install and the saves together.
                        'data_path' => '/home/pok/arkserver',
                        'steam_app_id' => 2430930,
                        // Explicitly cleared, not just omitted. The row already
                        // holds "asaserver" from when this template claimed to be
                        // a LinuxGSM one, and updateOrCreate leaves anything it is
                        // not given alone: the daemon would keep being handed a
                        // shortname for a game LinuxGSM has never supported.
                        'lgsm_shortname' => null,

                        // Multi-statement, and only the last line execs. The image
                        // reads ASA_PORT and RCON_PORT, not the SERVER_PORT this
                        // runtime injects, so they are bridged here rather than
                        // left for somebody to notice when the allocation and the
                        // listening port disagree.
                        'startup' => <<<'SH'
                        ASA_PORT=${ASA_PORT:-${SERVER_PORT:-7777}}
                        export ASA_PORT
                        export RCON_PORT="${RCON_PORT:-$((ASA_PORT + 19243))}"

                        exec /home/pok/scripts/init.sh
                        SH,

                        'config_startup' => ['done' => 'Server has completed startup', 'strip_ansi' => true],
                        // The image never reads stdin: init.sh traps the signal and
                        // does an RCON saveworld before it lets the server go. "^C"
                        // is the convention for "signal it instead", and the Docker
                        // runtime honours it by skipping the pointless wait on a
                        // console that is not listening.
                        'config_stop' => ['value' => '^C'],
                        'config_logs' => ['custom' => false],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        // 7777 + 19243 = 27020, the image's RCON default.
                        'rcon_port_offset' => 19243,
                        'query_port_offset' => 0,
                        // Survival Ascended mods come from CurseForge. The Steam
                        // Workshop is Survival Evolved, a different game.
                        // 83374 is ARK: Survival Ascended on CurseForge, which
                        // is where Wildcard moved ASA modding. Without this the
                        // search ran against Minecraft.
                        'mod_sources' => ['curseforge'],
                        'curseforge_game_id' => 83374,
                        'update_command' => 'docker pull acekorneya/asa_server:2_1_latest',
                        'variables' => [
                            ['name' => 'Session Name', 'env_variable' => 'SESSION_NAME', 'default_value' => 'A GameMGR ASA Server', 'rules' => 'required|string|max:60', 'description' => 'What the server calls itself in the in-game browser.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Map', 'env_variable' => 'MAP_NAME', 'default_value' => 'TheIsland_WP', 'rules' => 'required|string|max:60', 'description' => 'TheIsland_WP, ScorchedEarth_WP, TheCenter_WP, Aberration_WP, Extinction_WP, or a modded map id. Each map is its own save, so changing this is a new world.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '20', 'rules' => 'required|integer|between:1,127', 'description' => 'Lowered from the game default of 70: each player is roughly another 250 MiB on top of a floor that is already 16 GiB.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Admin Password', 'env_variable' => 'SERVER_ADMIN_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'Needed for in-game admin commands and for RCON. Leave it blank and the image cannot save the world before a restart.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Password', 'env_variable' => 'SERVER_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_num|max:40', 'description' => 'Leave blank for an open server. Wildcard allows letters and numbers only here.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Enabled', 'env_variable' => 'RCON_ENABLED', 'default_value' => 'TRUE', 'rules' => 'required|in:TRUE,FALSE', 'description' => 'The graceful shutdown, the update notices and the Players tab all go through RCON. Turning it off makes every stop a hard kill.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'RCON Port', 'env_variable' => 'RCON_PORT', 'default_value' => '', 'rules' => 'nullable|integer|between:1024,65535', 'description' => 'Leave blank to use the game port plus 19243, which is the conventional 27020 when the game port is 7777. TCP, not UDP.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'BattlEye', 'env_variable' => 'BATTLEEYE', 'default_value' => 'FALSE', 'rules' => 'required|in:TRUE,FALSE', 'description' => 'Off by default because BattlEye under Proton is the most common cause of a server that boots and then drops everyone.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Auto Update', 'env_variable' => 'UPDATE_SERVER', 'default_value' => 'TRUE', 'rules' => 'required|in:TRUE,FALSE', 'description' => 'Clients refuse to connect to a server on an older build, and this one patches often.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Restart Notice Minutes', 'env_variable' => 'RESTART_NOTICE_MINUTES', 'default_value' => '30', 'rules' => 'required|integer|between:0,120', 'description' => 'How long players are warned in chat before an update restart.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Mod IDs', 'env_variable' => 'MOD_IDS', 'default_value' => '', 'rules' => 'nullable|string|max:500', 'description' => 'Comma separated CurseForge project ids. Every player needs the same list or they cannot join.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Cluster ID', 'env_variable' => 'CLUSTER_ID', 'default_value' => 'gamemgr', 'rules' => 'required|alpha_dash|max:40', 'description' => 'Servers sharing this id let players transfer characters between maps.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Instance Name', 'env_variable' => 'INSTANCE_NAME', 'default_value' => 'gamemgr', 'rules' => 'required|alpha_dash|max:40', 'description' => 'The image\'s own name for this instance, used in its log and save paths.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'Timezone', 'env_variable' => 'TZ', 'default_value' => 'UTC', 'rules' => 'required|string|max:40', 'description' => 'Sets the clock the update window and the restart notices are measured against.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Extra Server Arguments', 'env_variable' => 'CUSTOM_SERVER_ARGS', 'default_value' => '', 'rules' => 'nullable|string|max:300', 'description' => 'Appended verbatim, for example -ForceAllowCaveFlyers.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Counter-Strike 2',
                'slug' => 'counter-strike-2',
                'description' => 'Source 2 competitive. Latency matters more than anything else here.',
                'author' => 'GameMGR',
                'icon' => 'target',
                'cover_color' => '#c2410c',
                'templates' => [
                    [
                        'name' => 'CS2 Dedicated',
                        'default_port' => 27015,
                        'default_protocol' => 'udp',
                        // All three on 27015: game and A2S over UDP, Source RCON over TCP.
                        // That collapses to one allocation open on both protocols, carrying
                        // all three roles, which is the only shape the unique index on
                        // (node_id, ip, port) allows and also the truth.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 27015],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 0],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port_offset' => 0],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'SteamCMD native, app 730, anonymous login. Anti-cheat and container networking do not always agree, so this one skips the container.',
                            'Ports: 27015/udp game and Steam query, 27015/tcp RCON. Source servers put RCON on the game port, so there is nothing extra to open and nothing extra to allocate.',
                            'About 35 GiB to download, which is the largest install in this catalogue by some way even though it is the lightest to run.',
                            'Budget 2 GiB of memory as a floor and 4 GiB for a full server. This one is bound by single-core clock speed and by latency, not by RAM.',
                            'Without a Game Server Login Token the server will not appear on the internet at all, so get one from steamcommunity.com/dev/managegameservers before testing anything beyond the LAN.',
                        ]),
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 730,
                        'steam_anonymous' => true,

                        // Multi-statement, POSIX, and only the last line execs.
                        // The placeholders that used to be here were never
                        // substituted by anything, so cs2 was being started with
                        // the literal text "{{SERVER_PORT}}" as its port.
                        'startup' => <<<'SH'
                        P=${SERVER_PORT:-27015}

                        # game_type and game_mode are a pair: 0/0 casual, 0/1 competitive,
                        # 0/2 wingman, 1/0 arms race, 1/1 demolition, 1/2 deathmatch.
                        set -- -dedicated -ip 0.0.0.0 -port "$P" \
                          -maxplayers "${MAX_PLAYERS:-16}" \
                          +game_type "${GAME_TYPE:-0}" +game_mode "${GAME_MODE:-1}" \
                          +mapgroup "${MAP_GROUP:-mg_active}" \
                          +map "${START_MAP:-de_dust2}" \
                          +hostname "${SERVER_NAME:-A GameMGR CS2 Server}"

                        # An empty token is worse than no token: sv_setsteamaccount with a
                        # blank argument makes CS2 refuse the Steam login outright, where
                        # omitting it merely leaves the server on the LAN.
                        [ -n "${GSLT:-}" ] && set -- "$@" +sv_setsteamaccount "$GSLT"
                        [ -n "${WORKSHOP_API_KEY:-}" ] && set -- "$@" -authkey "$WORKSHOP_API_KEY"
                        [ -n "${RCON_PASSWORD:-}" ] && set -- "$@" +rcon_password "$RCON_PASSWORD"
                        [ -n "${SERVER_PASSWORD:-}" ] && set -- "$@" +sv_password "$SERVER_PASSWORD"

                        exec ./game/bin/linuxsteamrt64/cs2 "$@"
                        SH,
                        'config_startup' => ['done' => 'Connection to Steam servers successful', 'strip_ansi' => true],
                        'config_stop' => ['value' => 'quit'],
                        'config_logs' => ['custom' => false],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        // Source keeps RCON and the A2S query on the game port.
                        'rcon_port_offset' => 0,
                        'query_port_offset' => 0,
                        'mod_sources' => ['workshop'],
                        'update_command' => 'app_update 730 validate',
                        'variables' => [
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR CS2 Server', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Starting Map', 'env_variable' => 'START_MAP', 'default_value' => 'de_dust2', 'rules' => 'required|string|max:60', 'description' => 'A stock map, or a Workshop id when a Workshop API key is set.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Map Group', 'env_variable' => 'MAP_GROUP', 'default_value' => 'mg_active', 'rules' => 'required|string|max:40', 'description' => 'The rotation the server cycles through. mg_active is the current competitive pool.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Game Type', 'env_variable' => 'GAME_TYPE', 'default_value' => '0', 'rules' => 'required|integer|in:0,1', 'description' => 'Pairs with the game mode: 0 is classic, 1 is the arms race family.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Game Mode', 'env_variable' => 'GAME_MODE', 'default_value' => '1', 'rules' => 'required|integer|in:0,1,2', 'description' => 'With game type 0: 0 casual, 1 competitive, 2 wingman. With game type 1: 0 arms race, 1 demolition, 2 deathmatch.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '16', 'rules' => 'required|integer|between:2,64', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Game Server Login Token', 'env_variable' => 'GSLT', 'default_value' => '', 'rules' => 'nullable|alpha_num|max:64', 'description' => 'From steamcommunity.com/dev/managegameservers, one per server, tied to app 730. Without one the server is LAN only. A revoked or reused token is the usual reason a server logs in and then vanishes from the browser.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Workshop API Key', 'env_variable' => 'WORKSHOP_API_KEY', 'default_value' => '', 'rules' => 'nullable|alpha_num|max:64', 'description' => 'A Steam Web API key, needed only to host Workshop maps. From steamcommunity.com/dev/apikey.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Password', 'env_variable' => 'RCON_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'RCON stays switched off until this is set. It then listens on the game port over TCP, which is worth remembering before exposing it.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Password', 'env_variable' => 'SERVER_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'Leave blank for an open server. This is what players type to join, not the RCON password.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Team Fortress 2',
                'slug' => 'team-fortress-2',
                'description' => 'Source 1 class shooter. Old, small and cheap to run, which makes it the best thing in this catalogue to test a node with.',
                'author' => 'GameMGR',
                'icon' => 'target',
                'cover_color' => '#b45309',
                'templates' => [
                    [
                        'name' => 'TF2 Dedicated',
                        'default_port' => 27015,
                        'default_protocol' => 'udp',
                        // Same collapse as CS2: one allocation on 27015 carrying all
                        // three roles, because Source puts the A2S query and RCON on
                        // the game port rather than on ports of their own.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 27015],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port_offset' => 0],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port_offset' => 0],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'SteamCMD native, app 232250, anonymous login. Roughly 15 GiB, and it will run in 1 GiB of memory, so this is the cheapest realistic end to end test of a new node.',
                            'Ports: 27015/udp game and Steam query, 27015/tcp RCON.',
                            'A 32 bit binary, so the node needs i386 multiarch. The node installer enables it; a hand built node that skipped it fails with "srcds_linux: No such file or directory" on a file that is plainly there, which is the loader failing, not the file missing.',
                            'Without a Game Server Login Token the server stays off the master list. One per server, tied to app 232250.',
                            'Useful trick: setting this template to a licensed Steam account instead of anonymous is the cheapest way to exercise the Steam Guard path, because the download works either way.',
                        ]),
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 232250,
                        // True because it is true: 232250 downloads without an account.
                        // Flip it off on a copy of this template to test a licensed
                        // login; do not misrepresent the game's real requirement here.
                        'steam_anonymous' => true,

                        // Shell expansion, not {{PLACEHOLDER}}. The steamcmd driver
                        // exports the environment before it runs this, and the braces
                        // form reached CS2 as literal text once already.
                        'startup' => <<<'SH'
                        P=${SERVER_PORT:-27015}

                        set -- -game tf -console -usercon -norestart \
                          -ip 0.0.0.0 -port "$P" \
                          +maxplayers "${MAX_PLAYERS:-24}" \
                          +map "${START_MAP:-cp_dustbowl}" \
                          +sv_pure "${SV_PURE:-1}" \
                          +hostname "${SERVER_NAME:-A GameMGR TF2 Server}"

                        # Empty values are omitted rather than passed. A blank
                        # sv_setsteamaccount makes the Steam login fail outright,
                        # where leaving it out merely keeps the server off the
                        # master list.
                        [ -n "${GSLT:-}" ] && set -- "$@" +sv_setsteamaccount "$GSLT"
                        [ -n "${RCON_PASSWORD:-}" ] && set -- "$@" +rcon_password "$RCON_PASSWORD"
                        [ -n "${SERVER_PASSWORD:-}" ] && set -- "$@" +sv_password "$SERVER_PASSWORD"

                        exec ./srcds_run "$@"
                        SH,
                        'config_startup' => ['done' => 'Connection to Steam servers successful', 'strip_ansi' => true],
                        'config_stop' => ['value' => 'quit'],
                        'config_logs' => ['custom' => false],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        'rcon_port_offset' => 0,
                        'query_port_offset' => 0,
                        'mod_sources' => ['workshop'],
                        'update_command' => 'app_update 232250 validate',
                        'variables' => [
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR TF2 Server', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Starting Map', 'env_variable' => 'START_MAP', 'default_value' => 'cp_dustbowl', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '24', 'rules' => 'required|integer|between:2,32', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Pure Server', 'env_variable' => 'SV_PURE', 'default_value' => '1', 'rules' => 'required|integer|in:-1,0,1,2', 'description' => 'How strictly client files are checked. 1 is the normal setting; -1 turns the check off and is what custom content servers use.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Game Server Login Token', 'env_variable' => 'GSLT', 'default_value' => '', 'rules' => 'nullable|alpha_num|max:64', 'description' => 'From steamcommunity.com/dev/managegameservers, one per server, tied to app 232250. Without one the server stays off the master list.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Password', 'env_variable' => 'RCON_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'RCON stays off until this is set. It then listens on the game port over TCP.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Password', 'env_variable' => 'SERVER_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:40', 'description' => 'Leave blank for an open server. This is what players type to join, not the RCON password.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Palworld',
                'slug' => 'palworld',
                'description' => 'Creature collecting survival. Memory hungry and update happy.',
                'author' => 'GameMGR',
                'icon' => 'sparkles',
                'cover_color' => '#0369a1',
                'templates' => [
                    [
                        'name' => 'Palworld Dedicated',
                        'default_port' => 8211,
                        'default_protocol' => 'udp',
                        // The set that started all of this. 8211 is the number in every
                        // Palworld guide, 27015 is the Steam query port the game defaults to,
                        // and 25575 is its RCON. All three are fixed rather than derived,
                        // because that is what the game ships with and what a player expects.
                        'ports' => [
                            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'port' => 8211],
                            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'port' => 27015],
                            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'port' => 25575],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'SteamCMD native, app 2394010, anonymous login.',
                            'Ports: 8211/udp game, 27015/udp Steam query, 25575/tcp RCON. Only 8211/udp is required to play.',
                            'Budget 8 GiB of memory as a hard floor for a handful of players and 16 GiB for a full 32, plus 40 GiB of disk.',
                            'The simulation is single threaded, so clock speed decides how it feels and extra cores do very little.',
                            'The Steam query port is fixed at 27015 and is not relocatable, so a second Palworld server on the same IP will not appear in the community browser.',
                            'Turn on auto-update: this one ships patches constantly.',
                        ]),
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 2394010,
                        'steam_anonymous' => true,

                        // Multi-statement on purpose. The tmux supervisor writes
                        // this to a launcher script verbatim and runs it with
                        // /bin/sh, so it must be POSIX, and only the final line
                        // may exec. An exec anywhere earlier would replace the
                        // shell with that one statement and drop the rest: the
                        // server would appear to boot, print a line and vanish.
                        'startup' => <<<'SH'
                        # PalWorldSettings.ini only exists after a first boot, and is only ever
                        # seeded from DefaultPalWorldSettings.ini in the install root. Writing it
                        # here means the first session already runs on the settings somebody chose.
                        D=Pal/Saved/Config/LinuxServer; C=$D/PalWorldSettings.ini
                        mkdir -p "$D"
                        grep -q OptionSettings "$C" 2>/dev/null || cp DefaultPalWorldSettings.ini "$C"

                        # OptionSettings is one comma-separated tuple on a single line: a stray
                        # comma, quote or bracket in a value ends it early and Palworld drops the
                        # whole file without saying so, so anything going in is stripped first.
                        q() { printf %s "$1" | tr -d '",()/&\\'; }

                        # Rewrite one key in place, appending it if an older build never shipped it.
                        s() {
                        if grep -q "[(,]$1=" "$C"; then sed -i "s/\([(,]\)$1=[^,)]*/\1$1=$2/" "$C"
                        else sed -i "s/)[[:space:]]*\$/,$1=$2)/" "$C"; fi
                        }

                        P=${SERVER_PORT:-8211}; N=${MAX_PLAYERS:-32}
                        s ServerPlayerMaxNum "$N"
                        s PublicPort "$P"

                        # Only keys the panel actually supplied are touched, so an edit made by
                        # hand in the file manager survives the next restart.
                        [ -n "${SERVER_NAME:-}" ] && s ServerName "\"$(q "$SERVER_NAME")\""
                        [ -n "${SERVER_DESCRIPTION:-}" ] && s ServerDescription "\"$(q "$SERVER_DESCRIPTION")\""
                        [ -n "${SERVER_PASSWORD:-}" ] && s ServerPassword "\"$(q "$SERVER_PASSWORD")\""

                        # RCON needs a password to authenticate against, so it stays shut without
                        # one: an admin port nothing can log in to is worse than no admin port.
                        if [ -n "${ADMIN_PASSWORD:-}" ]; then
                        s AdminPassword "\"$(q "$ADMIN_PASSWORD")\""
                        s RCONEnabled True
                        s RCONPort "${RCON_PORT:-$((P + 17364))}"
                        fi

                        [ "${COMMUNITY_SERVER:-false}" = true ] && set -- -publiclobby -publicport="$P"
                        [ -n "${PUBLIC_IP:-}" ] && set -- "$@" -publicip="$PUBLIC_IP"

                        # exec, and only on the last line, so the pane becomes the game: killing the
                        # tmux session then hangs it up, which Unreal handles by saving and exiting.
                        exec ./PalServer.sh -port="$P" -players="$N" -logformat=text "$@"
                        SH,

                        // The line the community eggs agree on, with the app id
                        // kept: "Setting breakpad minidump AppID" on its own also
                        // matches the SteamCMD install output for any app.
                        'config_startup' => ['done' => 'Setting breakpad minidump AppID = 2394010', 'strip_ansi' => true],

                        // Palworld's console is not interactive: the dedicated
                        // server never reads stdin, so no typed word can stop it.
                        // "^C" is the egg convention for "signal it instead", and
                        // the supervisor honours that by skipping the pointless
                        // 30 second wait and killing the tmux session, which
                        // hangs the process up. Unreal handles the hangup, saves
                        // the world and exits. Typing "quit" here, as this
                        // template used to, saves nothing and wastes 30 seconds
                        // arriving at the same place.
                        'config_stop' => ['value' => '^C'],

                        // Palworld writes to stdout and never to a file, so the
                        // supervisor's console capture is the only log there is.
                        'config_logs' => ['custom' => false],

                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        // Offsets from the game port, matching the conventional
                        // 8211 allocation: 8211 + 17364 = 25575 for RCON and
                        // 8211 + 18804 = 27015 for the Steam query.
                        'rcon_port_offset' => 17364,
                        'query_port_offset' => 18804,
                        'update_command' => 'app_update 2394010 validate',
                        'config_schema' => [$this->palworldSettings()],
                        'variables' => [
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR Palworld Server', 'rules' => 'required|string|max:64', 'description' => 'Shown in the in-game server browser. Commas, quotes and brackets are stripped before this reaches PalWorldSettings.ini, because any one of them voids the whole file.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Description', 'env_variable' => 'SERVER_DESCRIPTION', 'default_value' => 'Hosted with GameMGR', 'rules' => 'nullable|string|max:128', 'description' => 'The blurb under the server name in the browser.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '32', 'rules' => 'required|integer|between:1,32', 'description' => 'Palworld caps at 32. Each player is roughly another 400 MiB, so 32 players wants 16 GiB.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Admin Password', 'env_variable' => 'ADMIN_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:30', 'description' => 'Needed for in-game admin commands and for RCON. RCON stays switched off until this is set, because an admin port with no password is one nothing can log in to.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Password', 'env_variable' => 'SERVER_PASSWORD', 'default_value' => '', 'rules' => 'nullable|alpha_dash|max:30', 'description' => 'Leave blank for an open server. This is what players type to join, not the admin password.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'RCON Port', 'env_variable' => 'RCON_PORT', 'default_value' => '', 'rules' => 'nullable|integer|between:1024,65535', 'description' => 'Leave blank to use the game port plus 17364, which is the conventional 25575 when the game port is 8211. Only takes effect once an admin password is set. TCP, not UDP.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Community Server', 'env_variable' => 'COMMUNITY_SERVER', 'default_value' => 'false', 'rules' => 'required|in:true,false', 'description' => 'Lists the server in the in-game community browser. Needs the Steam query port 27015/udp open as well as the game port.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Public IP', 'env_variable' => 'PUBLIC_IP', 'default_value' => '', 'rules' => 'nullable|ip', 'description' => 'Only needed for a community server behind NAT, where Palworld cannot work out its own address. Leave blank on a VM with a public IP.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
