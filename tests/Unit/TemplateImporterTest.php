<?php

namespace Tests\Unit;

use App\Services\TemplateImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Egg import is how GameMGR inherits Pterodactyl's catalogue instead of
 * starting empty, so the awkward parts of the format are pinned down here.
 */
class TemplateImporterTest extends TestCase
{
    use RefreshDatabase;

    private function definition(array $overrides = []): array
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

    public function test_it_imports_a_v2_definition(): void
    {
        $template = (new TemplateImporter)->import($this->definition());

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
        $template = (new TemplateImporter)->import($this->definition());

        $this->assertIsArray($template->config_files);
        $this->assertArrayHasKey('server.properties', $template->config_files);
    }

    /** PTDL_v1 used a single "image" string rather than a label map. */
    public function test_it_handles_the_v1_single_image_shape(): void
    {
        $definition = $this->definition();
        unset($definition['docker_images']);
        $definition['meta']['version'] = 'PTDL_v1';
        $definition['image'] = 'quay.io/example/java:8';

        $template = (new TemplateImporter)->import($definition);

        $this->assertSame(['8' => 'quay.io/example/java:8'], array_change_key_case($template->docker_images));
    }

    public function test_it_finds_the_steam_app_id_in_the_install_script(): void
    {
        $template = (new TemplateImporter)->import($this->definition());

        $this->assertSame(302550, $template->steam_app_id);
    }

    /**
     * Community definitions write the visibility flags as booleans, as 0 and 1, and as
     * the strings "true" and "false". All three appear in the wild.
     */
    public function test_it_accepts_every_boolean_dialect(): void
    {
        $definition = $this->definition();
        $definition['variables'] = [
            ['name' => 'A', 'env_variable' => 'A', 'user_viewable' => true, 'user_editable' => false, 'rules' => 'nullable'],
            ['name' => 'B', 'env_variable' => 'B', 'user_viewable' => 1, 'user_editable' => 0, 'rules' => 'nullable'],
            ['name' => 'C', 'env_variable' => 'C', 'user_viewable' => 'true', 'user_editable' => 'false', 'rules' => 'nullable'],
        ];

        $template = (new TemplateImporter)->import($definition);

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
        $definition = $this->definition(['startup' => './start.sh -config serverconfig.txt']);
        unset($definition['config']['files']);

        $template = (new TemplateImporter)->import($definition);

        $this->assertFalse($template->rcon_supported);
    }

    public function test_it_flags_a_definition_that_would_run_better_natively(): void
    {
        $importer = new TemplateImporter;
        $importer->import($this->definition());

        $this->assertNotEmpty($importer->warnings);
        $this->assertStringContainsString('SteamCMD', implode(' ', $importer->warnings));
    }

    public function test_it_rejects_something_that_is_not_a_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TemplateImporter)->import(['hello' => 'world']);
    }

    // ---------------------------------------------------------------- ports

    /**
     * The definition format has no port field, so the ports hide in the variables: an
     * egg that wants a second listener has no other way to hand the number to
     * its startup command.
     */
    public function test_it_reads_a_port_set_out_of_the_variables(): void
    {
        $definition = $this->definition(['variables' => [
            ['name' => 'Port', 'env_variable' => 'SERVER_PORT', 'default_value' => '25565', 'rules' => 'required|integer'],
            ['name' => 'RCON', 'env_variable' => 'RCON_PORT', 'default_value' => '25575', 'rules' => 'required|integer'],
            ['name' => 'Query', 'env_variable' => 'QUERY_PORT', 'default_value' => '25565', 'rules' => 'required|integer'],
        ]]);

        $template = (new TemplateImporter)->import($definition);
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
        $definition = $this->definition([
            'ports' => ['game' => 8211, 'rcon' => 25575],
            'variables' => [
                ['name' => 'Port', 'env_variable' => 'SERVER_PORT', 'default_value' => '27015', 'rules' => 'required|integer'],
            ],
        ]);

        $template = (new TemplateImporter)->import($definition);

        $this->assertSame(8211, (int) $template->canonicalGamePort());
        $this->assertCount(2, $template->ports);
    }

    /**
     * A definition whose SERVER_PORT default is blank is saying "the panel picks
     * this", which is exactly the case we must not invent a canonical port for.
     */
    public function test_a_definition_that_never_names_a_port_gets_no_port_set(): void
    {
        $importer = new TemplateImporter;
        $definition = $this->definition(['variables' => [
            ['name' => 'Port', 'env_variable' => 'SERVER_PORT', 'default_value' => '', 'rules' => 'nullable|integer'],
        ]]);

        $template = $importer->import($definition);

        $this->assertCount(0, $template->ports);
        $this->assertNull($template->default_port);
        $this->assertStringContainsString('never says which ports', implode(' ', $importer->warnings));
    }

    /**
     * The app id lives in a variable, not in the script.
     *
     * Community steamcmd definitions write `+app_update ${SRCDS_APPID}` and declare the
     * number as a default on a variable called "App ID". The extractor used to
     * read the script alone, so it returned null for nearly every real egg in
     * the catalogue while the id sat one key away.
     */
    public function test_the_app_id_is_read_from_the_variables(): void
    {
        $template = (new TemplateImporter)->import($this->definition([
            'name' => 'ARK Ascended',
            'scripts' => ['installation' => ['script' => "#!/bin/bash\n./steamcmd.sh +app_update \${SRCDS_APPID} validate\n"]],
            'variables' => [
                ['name' => 'App ID', 'env_variable' => 'SRCDS_APPID', 'default_value' => '2430930',
                    'user_viewable' => true, 'user_editable' => false, 'rules' => 'required|string'],
            ],
        ]));

        $this->assertSame(2430930, $template->steam_app_id);
    }

    /** A literal in the script still works when there is no variable to read. */
    public function test_the_script_is_still_read_as_a_fallback(): void
    {
        $template = (new TemplateImporter)->import($this->definition());

        $this->assertSame(302550, $template->steam_app_id);
    }

    /**
     * Steam credentials must never become template variables.
     *
     * Every steamcmd egg in the community catalogue logs in with
     * +login ${STEAM_USER} ${STEAM_PASS} ${STEAM_AUTH} and declares those as
     * ordinary variables. Imported as-is, a real Steam password would sit in
     * server_variables in plain text, once per server, editable by the client
     * on the Startup tab. Importing the catalogue would do that 127 times.
     */
    public function test_steam_credentials_become_an_account_binding(): void
    {
        $template = (new TemplateImporter)->import($this->definition([
            'name' => 'Some Paid Game',
            'variables' => [
                ['name' => 'Steam User', 'env_variable' => 'STEAM_USER', 'default_value' => '',
                    'user_viewable' => true, 'user_editable' => true, 'rules' => 'nullable|string'],
                ['name' => 'Steam Pass', 'env_variable' => 'STEAM_PASS', 'default_value' => '',
                    'user_viewable' => true, 'user_editable' => true, 'rules' => 'nullable|string'],
                ['name' => 'Steam Auth', 'env_variable' => 'STEAM_AUTH', 'default_value' => '',
                    'user_viewable' => true, 'user_editable' => true, 'rules' => 'nullable|string'],
                ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '20',
                    'user_viewable' => true, 'user_editable' => true, 'rules' => 'required|integer'],
            ],
        ]));

        $names = $template->variables->pluck('env_variable')->all();

        $this->assertContains('MAX_PLAYERS', $names, 'ordinary variables still import');
        foreach (['STEAM_USER', 'STEAM_PASS', 'STEAM_AUTH'] as $credential) {
            $this->assertNotContains($credential, $names);
        }

        $this->assertTrue((bool) $template->requires_steam_account);
        $this->assertFalse((bool) $template->steam_anonymous);
    }

    /** An anonymous egg is left alone: most dedicated servers need no account. */
    public function test_an_anonymous_definition_needs_no_steam_account(): void
    {
        $template = (new TemplateImporter)->import($this->definition());

        $this->assertFalse((bool) $template->requires_steam_account);
        $this->assertTrue((bool) $template->steam_anonymous);
    }

}
