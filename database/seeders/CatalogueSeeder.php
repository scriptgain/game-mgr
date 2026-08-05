<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Database\Seeder;

/**
 * The games and templates a fresh install ships with.
 *
 * Chosen to cover all three runtimes rather than to be exhaustive: Minecraft
 * for Docker, Rust and CS2 for SteamCMD, Valheim and ARK for LinuxGSM. The
 * point is that a new install can host something on day one, and that every
 * runtime has a working example to copy.
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
                foreach ($vars as $v) {
                    TemplateVariable::updateOrCreate(
                        ['template_id' => $template->id, 'env_variable' => $v['env_variable']],
                        $v + ['template_id' => $template->id, 'sort' => $sort++],
                    );
                }
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
                        'author' => 'GameMGR',
                        'description' => 'High performance Spigot fork. The sensible default for a plugin server.',
                        'runtime' => 'docker',
                        // A real, public image, so a fresh dev stack can boot
                        // a server without anyone having to build one first.
                        'docker_images' => ['Latest' => 'itzg/minecraft-server:latest', 'Java 17' => 'itzg/minecraft-server:java17'],
                        'data_path' => '/data',
                        'startup' => '/start',
                        'config_startup' => ['done' => ')! For help, type ', 'strip_ansi' => false],
                        'config_stop' => ['value' => 'stop'],
                        'config_logs' => ['custom' => false, 'location' => 'logs/latest.log'],
                        'config_files' => ['server.properties' => ['parser' => 'properties', 'find' => ['server-ip' => '{{server.build.default.ip}}', 'server-port' => '{{server.build.default.port}}']]],
                        'features' => ['eula', 'java_version', 'pid_limit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'minecraft',
                        'query_protocol' => 'minecraft',
                        'rcon_port_offset' => 10,
                        'mod_sources' => ['modrinth', 'spigot', 'curseforge'],
                        'update_command' => 'gamemgr paper update',
                        'variables' => [
                            ['name' => 'Server Jar File', 'env_variable' => 'SERVER_JARFILE', 'default_value' => 'server.jar', 'rules' => 'required|regex:/^([\w\d._-]+)(\.jar)$/', 'description' => 'The jar the server boots from.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Minecraft Version', 'env_variable' => 'MINECRAFT_VERSION', 'default_value' => 'latest', 'rules' => 'required|string|max:20', 'description' => 'Version to install. "latest" tracks the newest stable release.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Build Number', 'env_variable' => 'BUILD_NUMBER', 'default_value' => 'latest', 'rules' => 'required|string|max:20', 'description' => 'Paper build to install.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                    [
                        'name' => 'Forge',
                        'author' => 'GameMGR',
                        'description' => 'Modded Minecraft. Pair with the Mods tab to pull straight from CurseForge.',
                        'runtime' => 'docker',
                        'docker_images' => ['Latest' => 'itzg/minecraft-server:latest'],
                        'data_path' => '/data',
                        'startup' => '/start',
                        'config_startup' => ['done' => ')! For help, type '],
                        'config_stop' => ['value' => 'stop'],
                        'features' => ['eula', 'java_version'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'minecraft',
                        'query_protocol' => 'minecraft',
                        'mod_sources' => ['curseforge', 'modrinth'],
                        'variables' => [
                            ['name' => 'Minecraft Version', 'env_variable' => 'MC_VERSION', 'default_value' => '1.20.1', 'rules' => 'required|string|max:20', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Forge Version', 'env_variable' => 'FORGE_VERSION', 'default_value' => '47.3.0', 'rules' => 'required|string|max:20', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                    [
                        'name' => 'Bedrock',
                        'author' => 'GameMGR',
                        'description' => 'Mojang bedrock_server, for console and mobile players.',
                        'runtime' => 'docker',
                        'docker_images' => ['Debian' => 'ghcr.io/gamemgr/debian:bookworm'],
                        'startup' => './bedrock_server',
                        'config_startup' => ['done' => 'Server started.'],
                        'config_stop' => ['value' => 'stop'],
                        'query_protocol' => 'minecraft',
                        'variables' => [
                            ['name' => 'Version', 'env_variable' => 'BEDROCK_VERSION', 'default_value' => 'latest', 'rules' => 'required|string|max:20', 'user_viewable' => true, 'user_editable' => true],
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
                        'author' => 'GameMGR',
                        'description' => 'Native SteamCMD install, no container in the way.',
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 258550,
                        'steam_anonymous' => true,
                        'startup' => './RustDedicated -batchmode +server.port {{SERVER_PORT}} +server.hostname "{{HOSTNAME}}" +server.worldsize {{WORLD_SIZE}} +server.seed {{WORLD_SEED}}',
                        'config_startup' => ['done' => 'Server startup complete'],
                        'config_stop' => ['value' => 'quit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        'rcon_port_offset' => 1,
                        'mod_sources' => ['workshop'],
                        'update_command' => 'app_update 258550 validate',
                        'variables' => [
                            ['name' => 'Hostname', 'env_variable' => 'HOSTNAME', 'default_value' => 'A GameMGR Rust Server', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Size', 'env_variable' => 'WORLD_SIZE', 'default_value' => '3000', 'rules' => 'required|integer|between:1000,6000', 'description' => 'Bigger maps need more memory.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'World Seed', 'env_variable' => 'WORLD_SEED', 'default_value' => '1234', 'rules' => 'required|integer', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                    [
                        'name' => 'Rust Oxide',
                        'author' => 'GameMGR',
                        'description' => 'Rust with the Oxide plugin framework preinstalled.',
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 258550,
                        'startup' => './RustDedicated -batchmode +server.port {{SERVER_PORT}} +server.hostname "{{HOSTNAME}}"',
                        'config_startup' => ['done' => 'Server startup complete'],
                        'config_stop' => ['value' => 'quit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        'mod_sources' => ['workshop'],
                        'variables' => [
                            ['name' => 'Hostname', 'env_variable' => 'HOSTNAME', 'default_value' => 'A GameMGR Oxide Server', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
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
                        'author' => 'GameMGR',
                        'description' => 'Managed by LinuxGSM, so updates and monitoring come free.',
                        'runtime' => 'linuxgsm',
                        'lgsm_shortname' => 'vhserver',
                        'steam_app_id' => 896660,
                        'startup' => './vhserver start',
                        'config_startup' => ['done' => 'Game server connected'],
                        'config_stop' => ['value' => './vhserver stop'],
                        'query_protocol' => 'a2s',
                        'update_command' => './vhserver update',
                        'variables' => [
                            ['name' => 'World Name', 'env_variable' => 'WORLD_NAME', 'default_value' => 'Midgard', 'rules' => 'required|string|max:32', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Server Password', 'env_variable' => 'SERVER_PASSWORD', 'default_value' => 'changeme', 'rules' => 'required|string|min:5|max:32', 'description' => 'Valheim refuses to start without one of at least five characters.', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'ARK: Survival Ascended',
                'slug' => 'ark-survival-ascended',
                'description' => 'Dinosaurs, and an appetite for RAM that has to be seen to be believed.',
                'author' => 'GameMGR',
                'icon' => 'bolt',
                'cover_color' => '#7c2d12',
                'templates' => [
                    [
                        'name' => 'ARK ASA',
                        'author' => 'GameMGR',
                        'description' => 'LinuxGSM managed. Budget 16 GiB and be pleasantly surprised.',
                        'runtime' => 'linuxgsm',
                        'lgsm_shortname' => 'asaserver',
                        'steam_app_id' => 2430930,
                        'startup' => './asaserver start',
                        'config_startup' => ['done' => 'Server has completed startup'],
                        'config_stop' => ['value' => './asaserver stop'],
                        'query_protocol' => 'a2s',
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'mod_sources' => ['workshop'],
                        'variables' => [
                            ['name' => 'Session Name', 'env_variable' => 'SESSION_NAME', 'default_value' => 'GameMGR ASA', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Map', 'env_variable' => 'ARK_MAP', 'default_value' => 'TheIsland_WP', 'rules' => 'required|string|max:40', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '70', 'rules' => 'required|integer|between:1,127', 'user_viewable' => true, 'user_editable' => true],
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
                        'author' => 'GameMGR',
                        'description' => 'Native SteamCMD. Anti-cheat and container networking do not always agree, so this one skips the container.',
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 730,
                        'steam_anonymous' => true,
                        'startup' => './game/bin/linuxsteamrt64/cs2 -dedicated -port {{SERVER_PORT}} +map {{START_MAP}} +sv_setsteamaccount {{GSLT}}',
                        'config_startup' => ['done' => 'Connection to Steam servers successful'],
                        'config_stop' => ['value' => 'quit'],
                        'rcon_supported' => true,
                        'rcon_protocol' => 'source',
                        'query_protocol' => 'a2s',
                        'mod_sources' => ['workshop'],
                        'update_command' => 'app_update 730 validate',
                        'variables' => [
                            ['name' => 'Starting Map', 'env_variable' => 'START_MAP', 'default_value' => 'de_dust2', 'rules' => 'required|string|max:40', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Game Server Login Token', 'env_variable' => 'GSLT', 'default_value' => '', 'rules' => 'nullable|string|max:64', 'description' => 'From steamcommunity.com/dev/managegameservers. Without one the server is LAN only.', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Tickrate', 'env_variable' => 'TICKRATE', 'default_value' => '64', 'rules' => 'required|integer|in:64,128', 'user_viewable' => true, 'user_editable' => false],
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
                        'author' => 'GameMGR',
                        'description' => 'SteamCMD native. Turn on auto-update: this one ships patches constantly.',
                        'runtime' => 'steamcmd',
                        'steam_app_id' => 2394010,
                        'startup' => './PalServer.sh -port={{SERVER_PORT}} -players={{MAX_PLAYERS}} -useperfthreads -NoAsyncLoadingThread',
                        'config_startup' => ['done' => 'Setting breakpad minidump AppID'],
                        'config_stop' => ['value' => 'quit'],
                        'query_protocol' => 'a2s',
                        'update_command' => 'app_update 2394010 validate',
                        'variables' => [
                            ['name' => 'Server Name', 'env_variable' => 'SERVER_NAME', 'default_value' => 'A GameMGR Palworld Server', 'rules' => 'required|string|max:60', 'user_viewable' => true, 'user_editable' => true],
                            ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '32', 'rules' => 'required|integer|between:1,32', 'user_viewable' => true, 'user_editable' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
