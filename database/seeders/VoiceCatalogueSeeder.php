<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Database\Seeder;

/**
 * Voice servers.
 *
 * Kept apart from CatalogueSeeder because they are not games and behave like
 * it. A voice server has no world, no player list the panel can query the usual
 * way, and no Steam app behind it; what it has is a port people connect to and
 * a config file. Filing TeamSpeak under "survival" would be nonsense, which is
 * why games.category exists.
 *
 * Idempotent, so it can be run on an install that already has them: everything
 * here is updateOrCreate keyed on the slug or the name.
 */
class VoiceCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogue() as $gameData) {
            $templates = $gameData['templates'];
            unset($gameData['templates']);

            $game = Game::updateOrCreate(['slug' => $gameData['slug']], $gameData);

            foreach ($templates as $t) {
                $vars = $t['variables'] ?? [];
                $ports = $t['ports'] ?? [];
                unset($t['variables'], $t['ports']);

                $template = Template::updateOrCreate(
                    ['game_id' => $game->id, 'name' => $t['name']],
                    $t + ['game_id' => $game->id],
                );

                $sort = 0;
                $declared = [];
                foreach ($ports as $p) {
                    $template->ports()->updateOrCreate(
                        ['role' => $p['role']],
                        $p + ['source' => 'fixed', 'required' => $p['required'] ?? true, 'sort' => $sort++],
                    );
                    $declared[] = $p['role'];
                }
                $template->ports()->whereNotIn('role', $declared)->delete();

                $sort = 0;
                $declaredVars = [];
                foreach ($vars as $v) {
                    TemplateVariable::updateOrCreate(
                        ['template_id' => $template->id, 'env_variable' => $v['env_variable']],
                        $v + ['template_id' => $template->id, 'sort' => $sort++],
                    );
                    $declaredVars[] = $v['env_variable'];
                }
                $template->variables()->whereNotIn('env_variable', $declaredVars)->delete();
            }
        }
    }

    private function catalogue(): array
    {
        return [
            [
                'name' => 'TeamSpeak',
                'slug' => 'teamspeak',
                'category' => 'voice',
                'description' => 'The voice server most communities already have. Light on everything except the licence terms.',
                'author' => 'GameMGR',
                'icon' => 'bell',
                'cover_color' => '#1d4ed8',
                'templates' => [
                    [
                        'name' => 'TeamSpeak 3 Server',
                        'default_port' => 9987,
                        'default_protocol' => 'udp',
                        /*
                         * Three ports and only the first matters to a person.
                         * 9987/udp is voice, and it is what somebody types into
                         * their client. 10011/tcp is ServerQuery, which is how
                         * anything automated talks to it, and 30033/tcp is file
                         * transfer, for avatars and icons.
                         *
                         * Query and file transfer are marked optional: a voice
                         * server with neither still works perfectly for talking,
                         * and refusing to create one because 30033 was taken
                         * would be refusing over something nobody would miss.
                         */
                        'ports' => [
                            ['role' => 'game', 'label' => 'Voice', 'protocol' => 'udp', 'port' => 9987],
                            ['role' => 'query', 'label' => 'ServerQuery', 'protocol' => 'tcp', 'port' => 10011, 'required' => false],
                            ['role' => 'filetransfer', 'label' => 'File Transfer', 'protocol' => 'tcp', 'port' => 30033, 'required' => false],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'The official TeamSpeak 3 server image.',
                            'Ports: 9987/udp voice, 10011/tcp ServerQuery, 30033/tcp file transfer. Only 9987/udp is needed to talk.',
                            'Tiny by game server standards: 512 MiB of memory and a couple of GiB of disk runs a large community.',
                            'The first boot prints a ServerQuery admin password and a privilege key ONCE, into the console. Copy them then, because nothing prints them again and the privilege key is what makes the first person to join an admin.',
                            'Accepting the licence is mandatory and is why ACCEPT_LICENSE exists: the server refuses to start without it.',
                        ]),
                        'runtime' => 'docker',
                        'docker_images' => ['Latest' => 'teamspeak:latest', 'TeamSpeak 3.13' => 'teamspeak:3.13'],
                        'data_path' => '/var/ts3server',
                        'startup' => 'exec ts3server',
                        // What the server prints when it is genuinely up and
                        // listening, rather than when the process merely exists.
                        'config_startup' => ['done' => 'listening on 0.0.0.0:9987', 'strip_ansi' => true],
                        'rcon_supported' => false,
                        'query_protocol' => null,
                        'variables' => [
                            [
                                'name' => 'Accept The Licence',
                                'env_variable' => 'TS3SERVER_LICENSE',
                                'description' => 'Must be "accept". The server refuses to start otherwise, which is the licence being enforced rather than a fault.',
                                'default_value' => 'accept',
                                'rules' => 'required|in:accept',
                                'user_viewable' => true,
                                'user_editable' => false,
                            ],
                            [
                                'name' => 'Maximum Clients',
                                'env_variable' => 'TS3SERVER_MAX_CLIENTS',
                                'description' => 'How many people can be connected at once.',
                                'default_value' => '32',
                                'rules' => 'nullable|integer|min:1|max:1000',
                                'user_viewable' => true,
                                'user_editable' => true,
                            ],
                        ],
                    ],
                ],
            ],

            [
                'name' => 'Mumble',
                'slug' => 'mumble',
                'category' => 'voice',
                'description' => 'Open source, low latency voice. No licence to accept and nothing phoning home.',
                'author' => 'GameMGR',
                'icon' => 'bell',
                'cover_color' => '#047857',
                'templates' => [
                    [
                        'name' => 'Mumble Server',
                        'default_port' => 64738,
                        'default_protocol' => 'both',
                        /*
                         * One port, and it genuinely needs both protocols on the
                         * same number: TCP carries the control channel and the
                         * text chat, UDP carries the voice. Open only one and it
                         * connects and nobody can hear anybody, which is a far
                         * more confusing failure than not connecting at all.
                         */
                        'ports' => [
                            ['role' => 'game', 'label' => 'Voice And Control', 'protocol' => 'both', 'port' => 64738],
                        ],
                        'author' => 'GameMGR',
                        'description' => implode(' ', [
                            'The official Mumble server image.',
                            'Port: 64738 on both TCP and UDP. TCP carries control and text, UDP carries voice, and it needs both on the same number.',
                            'Very small: 256 MiB of memory is generous and the disk holds little more than the channel tree.',
                            'Set a SuperUser password before anybody joins, or the first person to arrive cannot be made an admin without a console.',
                        ]),
                        'runtime' => 'docker',
                        'docker_images' => ['Latest' => 'mumblevoip/mumble-server:latest'],
                        'data_path' => '/data',
                        'startup' => 'exec /usr/bin/mumble-server -fg -ini /data/mumble_server_config.ini',
                        'config_startup' => ['done' => 'Server listening on', 'strip_ansi' => true],
                        'rcon_supported' => false,
                        'query_protocol' => null,
                        'variables' => [
                            [
                                'name' => 'SuperUser Password',
                                'env_variable' => 'MUMBLE_SUPERUSER_PASSWORD',
                                'description' => 'The administrator password. Set it before the first person joins.',
                                'default_value' => '',
                                'rules' => 'nullable|string|max:64',
                                'user_viewable' => true,
                                'user_editable' => true,
                            ],
                            [
                                'name' => 'Welcome Text',
                                'env_variable' => 'MUMBLE_WELCOMETEXT',
                                'description' => 'Shown to everybody as they connect.',
                                'default_value' => 'Welcome.',
                                'rules' => 'nullable|string|max:255',
                                'user_viewable' => true,
                                'user_editable' => true,
                            ],
                            [
                                'name' => 'Maximum Users',
                                'env_variable' => 'MUMBLE_USERS',
                                'description' => 'How many people can be connected at once.',
                                'default_value' => '100',
                                'rules' => 'nullable|integer|min:1|max:1000',
                                'user_viewable' => true,
                                'user_editable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
