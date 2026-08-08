<?php

namespace Tests\Unit;

use App\Services\EggImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Egg import is how GameMGR inherits Pterodactyl's catalogue instead of
 * starting empty, so the awkward parts of the format are pinned down here.
 */
class EggImporterTest extends TestCase
{
    use RefreshDatabase;

    private function egg(array $overrides = []): array
    {
        return array_replace_recursive([
            'meta' => ['version' => 'PTDL_v2'],
            'name' => 'Paper Minecraft',
            'author' => 'someone@example.com',
            'description' => 'A Minecraft server.',
            'docker_images' => ['Java 21' => 'ghcr.io/example/java:21'],
            'startup' => 'java -jar server.jar',
            'config' => [
                // Pterodactyl double-encodes these: JSON strings inside JSON.
                'files' => '{"server.properties":{"parser":"properties"}}',
                'startup' => '{"done":") For help, type"}',
                'stop' => 'stop',
                'logs' => '{}',
            ],
            'scripts' => ['installation' => [
                'script' => "#!/bin/bash\nsteamcmd +app_update 302550 validate\n",
                'container' => 'ghcr.io/example/installers:debian',
                'entrypoint' => 'bash',
            ]],
            'variables' => [
                ['name' => 'Jar File', 'env_variable' => 'SERVER_JARFILE', 'default_value' => 'server.jar',
                    'user_viewable' => true, 'user_editable' => true, 'rules' => 'required|string'],
            ],
        ], $overrides);
    }

    public function test_it_imports_a_v2_egg(): void
    {
        $template = (new EggImporter)->import($this->egg());

        $this->assertSame('Paper Minecraft', $template->name);
        $this->assertSame('Minecraft', $template->game->name);
        $this->assertSame('docker', $template->runtime);
        $this->assertSame(['Java 21' => 'ghcr.io/example/java:21'], $template->docker_images);
        $this->assertSame(') For help, type', $template->doneMarker());
        $this->assertSame('stop', $template->stopCommand());
        $this->assertCount(1, $template->variables);
    }

    /** The double-encoded config blocks must come out as arrays, not strings. */
    public function test_it_decodes_double_encoded_config(): void
    {
        $template = (new EggImporter)->import($this->egg());

        $this->assertIsArray($template->config_files);
        $this->assertArrayHasKey('server.properties', $template->config_files);
    }

    /** PTDL_v1 used a single "image" string rather than a label map. */
    public function test_it_handles_the_v1_single_image_shape(): void
    {
        $egg = $this->egg();
        unset($egg['docker_images']);
        $egg['meta']['version'] = 'PTDL_v1';
        $egg['image'] = 'quay.io/example/java:8';

        $template = (new EggImporter)->import($egg);

        $this->assertSame(['8' => 'quay.io/example/java:8'], array_change_key_case($template->docker_images));
    }

    public function test_it_finds_the_steam_app_id_in_the_install_script(): void
    {
        $template = (new EggImporter)->import($this->egg());

        $this->assertSame(302550, $template->steam_app_id);
    }

    /**
     * Community eggs write the visibility flags as booleans, as 0 and 1, and as
     * the strings "true" and "false". All three appear in the wild.
     */
    public function test_it_accepts_every_boolean_dialect(): void
    {
        $egg = $this->egg();
        $egg['variables'] = [
            ['name' => 'A', 'env_variable' => 'A', 'user_viewable' => true, 'user_editable' => false, 'rules' => 'nullable'],
            ['name' => 'B', 'env_variable' => 'B', 'user_viewable' => 1, 'user_editable' => 0, 'rules' => 'nullable'],
            ['name' => 'C', 'env_variable' => 'C', 'user_viewable' => 'true', 'user_editable' => 'false', 'rules' => 'nullable'],
        ];

        $template = (new EggImporter)->import($egg);

        foreach ($template->variables as $variable) {
            $this->assertTrue($variable->user_viewable, $variable->env_variable.' should be viewable');
            $this->assertFalse($variable->user_editable, $variable->env_variable.' should not be editable');
        }
    }

    /**
     * "serverconfig.txt" contains the letters r-c-o-n. A substring search flagged
     * every Terraria egg as RCON capable and put a Players tab on a server with
     * no way to answer it.
     */
    public function test_it_does_not_mistake_serverconfig_for_rcon(): void
    {
        $egg = $this->egg(['startup' => './start.sh -config serverconfig.txt']);
        unset($egg['config']['files']);

        $template = (new EggImporter)->import($egg);

        $this->assertFalse($template->rcon_supported);
    }

    public function test_it_flags_an_egg_that_would_run_better_natively(): void
    {
        $importer = new EggImporter;
        $importer->import($this->egg());

        $this->assertNotEmpty($importer->warnings);
        $this->assertStringContainsString('SteamCMD', implode(' ', $importer->warnings));
    }

    public function test_it_rejects_something_that_is_not_an_egg(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new EggImporter)->import(['hello' => 'world']);
    }

    // ---------------------------------------------------------------- ports

    /**
     * The egg format has no port field, so the ports hide in the variables: an
     * egg that wants a second listener has no other way to hand the number to
     * its startup command.
     */
    public function test_it_reads_a_port_set_out_of_the_egg_variables(): void
    {
        $egg = $this->egg(['variables' => [
            ['name' => 'Port', 'env_variable' => 'SERVER_PORT', 'default_value' => '25565', 'rules' => 'required|integer'],
            ['name' => 'RCON', 'env_variable' => 'RCON_PORT', 'default_value' => '25575', 'rules' => 'required|integer'],
            ['name' => 'Query', 'env_variable' => 'QUERY_PORT', 'default_value' => '25565', 'rules' => 'required|integer'],
        ]]);

        $template = (new EggImporter)->import($egg);
        $ports = $template->ports->keyBy('role');

        $this->assertSame(25565, (int) $ports['game']->port);
        $this->assertSame(25575, (int) $ports['rcon']->port);
        $this->assertSame('tcp', $ports['rcon']->protocol, 'RCON is TCP unless the game says BattlEye');
        $this->assertSame('udp', $ports['query']->protocol, 'every query protocol in use is UDP');

        // The legacy columns are a mirror of the set, so nothing that reads
        // them has to learn about the new table.
        $this->assertSame(25565, (int) $template->default_port);
        $this->assertSame(10, (int) $template->rcon_port_offset);
    }

    /** A declared "ports" key beats anything inferred from the variables. */
    public function test_a_declared_ports_key_wins(): void
    {
        $egg = $this->egg([
            'ports' => ['game' => 8211, 'rcon' => 25575],
            'variables' => [
                ['name' => 'Port', 'env_variable' => 'SERVER_PORT', 'default_value' => '27015', 'rules' => 'required|integer'],
            ],
        ]);

        $template = (new EggImporter)->import($egg);

        $this->assertSame(8211, (int) $template->canonicalGamePort());
        $this->assertCount(2, $template->ports);
    }

    /**
     * An egg whose SERVER_PORT default is blank is saying "the panel picks
     * this", which is exactly the case we must not invent a canonical port for.
     */
    public function test_an_egg_that_never_names_a_port_gets_no_port_set(): void
    {
        $importer = new EggImporter;
        $egg = $this->egg(['variables' => [
            ['name' => 'Port', 'env_variable' => 'SERVER_PORT', 'default_value' => '', 'rules' => 'nullable|integer'],
        ]]);

        $template = $importer->import($egg);

        $this->assertCount(0, $template->ports);
        $this->assertNull($template->default_port);
        $this->assertStringContainsString('never says which ports', implode(' ', $importer->warnings));
    }
}
