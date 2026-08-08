<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Template;
use App\Models\TemplateVariable;
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
    public function run(): void
    {
        foreach ($this->catalogue() as $gameData) {
            $templates = $gameData['templates'];
            unset($gameData['templates']);

            $game = Game::updateOrCreate(['slug' => $gameData['slug']], $gameData);

            foreach ($templates as $t) {
                $vars = $t['variables'] ?? [];
                unset($t['variables']);

                $template = Template::updateOrCreate(
                    ['game_id' => $game->id, 'name' => $t['name']],
                    $t + ['game_id' => $game->id],
                );

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
                        'features' => ['eula', 'java_version', 'pid_limit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'minecraft',
                        'query_protocol' => 'minecraft',
                        // Minecraft's own defaults: RCON on 25575 against a game
                        // port of 25565, and the query on the game port itself.
                        'rcon_port_offset' => 10,
                        'query_port_offset' => 0,
                        'mod_sources' => ['modrinth', 'spigot', 'curseforge'],
                        'update_command' => 'docker pull itzg/minecraft-server:latest',
                        'variables' => [
                            ['name' => 'Accept the Minecraft EULA', 'env_variable' => 'EULA', 'default_value' => 'TRUE', 'rules' => 'required|in:TRUE,true', 'description' => 'Mojang requires this. Without it the container prints the EULA notice and exits immediately, which reads as a crash loop.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'Server Type', 'env_variable' => 'TYPE', 'default_value' => 'PAPER', 'rules' => 'required|in:PAPER', 'description' => 'Which server software the image downloads. Locked, because a Paper template that quietly installs Forge is not a Paper template.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'Minecraft Version', 'env_variable' => 'VERSION', 'default_value' => 'LATEST', 'rules' => 'required|string|max:20', 'description' => 'LATEST tracks the newest Paper release. A specific version such as 1.20.4 pins it.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Paper Build', 'env_variable' => 'PAPER_BUILD', 'default_value' => '', 'rules' => 'nullable|string|max:20', 'description' => 'Leave blank for the newest build of the chosen version. A number pins one build.', 'user_viewable' => true, 'user_editable' => true],
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
                        'features' => ['eula', 'java_version', 'pid_limit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'minecraft',
                        'query_protocol' => 'minecraft',
                        // Same offsets as Paper. They were zero here, which meant
                        // two Minecraft templates disagreed about where RCON lives.
                        'rcon_port_offset' => 10,
                        'query_port_offset' => 0,
                        'mod_sources' => ['curseforge', 'modrinth'],
                        'update_command' => 'docker pull itzg/minecraft-server:latest',
                        'variables' => [
                            ['name' => 'Accept the Minecraft EULA', 'env_variable' => 'EULA', 'default_value' => 'TRUE', 'rules' => 'required|in:TRUE,true', 'description' => 'Mojang requires this. Without it the container prints the EULA notice and exits immediately.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'Server Type', 'env_variable' => 'TYPE', 'default_value' => 'FORGE', 'rules' => 'required|in:FORGE', 'description' => 'Locked. Switching this is really a different template.', 'user_viewable' => true, 'user_editable' => false],
                            ['name' => 'Minecraft Version', 'env_variable' => 'VERSION', 'default_value' => '1.20.1', 'rules' => 'required|string|max:20', 'description' => 'Pinned rather than LATEST, because Forge lags Minecraft by weeks and the pair has to match.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Forge Version', 'env_variable' => 'FORGE_VERSION', 'default_value' => '47.3.0', 'rules' => 'required|string|max:20', 'description' => 'A Forge build for the Minecraft version above, or "latest". 47.3.0 is a 1.20.1 build.', 'user_viewable' => true, 'user_editable' => true],
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
                        'mod_sources' => ['curseforge'],
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
