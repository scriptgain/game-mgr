<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\Setting;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The MCJars type and version picker, with the HTTP client faked.
 *
 * The behaviour worth defending is not "the dropdown has Paper in it". It is
 * that a third party service being slow, down or wrong never costs anybody a
 * server. So the happy path is one test and the four ways MCJars can fail are
 * four more, and every one of them asserts the form still works.
 */
class McJarsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Template $minecraft;

    private Template $palworld;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret1234', 'role' => 'admin']);
        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret1234', 'role' => 'client']);

        $location = Location::create(['short' => 'test', 'name' => 'Test']);
        $node = Node::create([
            'name' => 'test-node', 'location_id' => $location->id, 'fqdn' => '127.0.0.1',
            'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'runtimes' => ['docker'],
        ]);

        $mc = Game::create(['name' => 'Minecraft', 'slug' => 'minecraft-test']);

        $this->minecraft = Template::create([
            'game_id' => $mc->id, 'name' => 'Paper', 'runtime' => 'docker',
            'docker_images' => ['Latest' => 'itzg/minecraft-server:latest'],
            'startup' => 'exec /start',
            'mcjars' => [
                'type_variable' => 'TYPE',
                'version_variable' => 'VERSION',
                'builds' => ['PAPER' => 'PAPER_BUILD', 'PURPUR' => 'PURPUR_BUILD', 'VANILLA' => null],
            ],
        ]);

        $this->variable($this->minecraft, 'Server Type', 'TYPE', 'PAPER', 'required|in:PAPER,PURPUR,VANILLA');
        $this->variable($this->minecraft, 'Minecraft Version', 'VERSION', 'LATEST', 'required|string|max:40');
        $this->variable($this->minecraft, 'Paper Build', 'PAPER_BUILD', '', 'nullable|string|max:40');
        $this->variable($this->minecraft, 'Purpur Build', 'PURPUR_BUILD', '', 'nullable|string|max:40');
        $this->variable($this->minecraft, 'Max Players', 'MAX_PLAYERS', '20', 'required|integer|between:1,200');

        // The control group. Nothing about this template says Minecraft, so
        // nothing about it may grow a Minecraft version picker.
        $pal = Game::create(['name' => 'Palworld', 'slug' => 'palworld-test']);
        $this->palworld = Template::create([
            'game_id' => $pal->id, 'name' => 'Palworld', 'runtime' => 'steamcmd', 'startup' => './PalServer.sh',
        ]);
        $this->variable($this->palworld, 'Server Name', 'SERVER_NAME', 'Pals', 'required|string|max:60');

        $this->server = Server::create([
            'name' => 'Test Server', 'owner_id' => $this->owner->id, 'node_id' => $node->id,
            'template_id' => $this->minecraft->id, 'runtime' => 'docker',
            'memory' => 2048, 'disk' => 10240, 'cpu' => 200,
        ]);

        $allocation = Allocation::create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => 25565, 'server_id' => $this->server->id]);
        $this->server->update(['allocation_id' => $allocation->id]);

        foreach ($this->minecraft->variables as $variable) {
            ServerVariable::create([
                'server_id' => $this->server->id,
                'template_variable_id' => $variable->id,
                'value' => $variable->default_value,
            ]);
        }

        // Every test starts from a cold cache, or the second test in the file
        // would be asserting against the first one's answers.
        Cache::flush();
    }

    private function variable(Template $template, string $name, string $env, string $default, string $rules): TemplateVariable
    {
        return TemplateVariable::create([
            'template_id' => $template->id, 'name' => $name, 'env_variable' => $env,
            'default_value' => $default, 'rules' => $rules,
            'user_viewable' => true, 'user_editable' => true,
        ]);
    }

    // ------------------------------------------------------------ the fakes

    /** MCJars answering exactly as the live service does. */
    private function fakeMcJars(): void
    {
        Http::fake([
            '*/api/v2/types' => Http::response([
                'success' => true,
                'types' => [
                    'recommended' => [
                        'VANILLA' => $this->typeInfo('Vanilla', 'The official Minecraft server software.'),
                        'PAPER' => $this->typeInfo('Paper', 'High performance Spigot fork.'),
                        'PURPUR' => $this->typeInfo('Purpur', 'Paper fork with more configuration.'),
                    ],
                    'established' => [
                        'FORGE' => $this->typeInfo('Forge', 'The original mod loader.'),
                    ],
                ],
            ], 200),
            '*/api/v3/builds/types/PAPER/versions/*' => Http::response([
                'builds' => ['total' => 2, 'per_page' => 100, 'page' => 1, 'data' => [
                    ['uuid' => 'a', 'version_id' => '1.21.8', 'project_version_id' => null, 'type' => 'PAPER',
                        'experimental' => false, 'name' => '#60', 'installation' => [], 'changes' => ['Fixed a thing'], 'created' => '2025-09-06T23:50:11.982Z'],
                    ['uuid' => 'b', 'version_id' => '1.21.8', 'project_version_id' => null, 'type' => 'PAPER',
                        'experimental' => false, 'name' => '#59', 'installation' => [], 'changes' => [], 'created' => '2025-09-06T23:03:20.324Z'],
                ]],
            ], 200),
            // The query string is part of what Http::fake matches on, and an
            // unmatched pattern is let through to the real network, so the
            // trailing wildcard is not cosmetic: without it this test suite
            // quietly calls mcjars.app for real.
            '*/api/v3/builds/types/*/versions?*' => Http::response([
                'versions' => ['total' => 3, 'per_page' => 200, 'page' => 1, 'data' => [
                    ['id' => '1.21.8', 'type' => 'RELEASE', 'supported' => true, 'java' => 21, 'builds' => 60,
                        'created' => '2025-07-17T12:04:02Z', 'latest' => ['name' => '#60', 'project_version_id' => null]],
                    ['id' => '1.21.7', 'type' => 'RELEASE', 'supported' => true, 'java' => 21, 'builds' => 12,
                        'created' => '2025-06-17T12:04:02Z', 'latest' => ['name' => '#12', 'project_version_id' => null]],
                    ['id' => '26.2-rc-2', 'type' => 'SNAPSHOT', 'supported' => true, 'java' => 25, 'builds' => 8,
                        'created' => '2026-06-12T11:32:28Z', 'latest' => ['name' => '#9', 'project_version_id' => null]],
                ]],
            ], 200),
            // Backstop. Nothing in this file may ever reach the real service.
            '*' => Http::response(['unmatched' => true], 200),
        ]);
    }

    private function typeInfo(string $name, string $description): array
    {
        return [
            'name' => $name, 'icon' => 'https://s3.mcjars.app/icons/x.png', 'color' => '#444444',
            'homepage' => 'https://example.test', 'deprecated' => false, 'experimental' => false,
            'description' => $description, 'categories' => [], 'compatibility' => [],
            'builds' => 5654, 'versions' => ['minecraft' => 100, 'project' => 0],
        ];
    }

    // ----------------------------------------------------------- happy path

    public function test_the_create_wizard_offers_the_types_and_versions_mcjars_lists(): void
    {
        $this->fakeMcJars();

        $response = $this->actingAs($this->admin)->get(route('admin.servers.create'));

        $response->assertOk();
        $response->assertSee('Server Software');
        // The type select is drawn from the catalogue, using its names.
        $response->assertSee('>Purpur</option>', false);
        $response->assertSee('>Vanilla</option>', false);
        // Forge is in the catalogue but not in this template's document, so it
        // must not be offered: the Config tab here describes Bukkit files.
        $response->assertDontSee('>Forge</option>', false);
        // The version list travels with the page for the type that opens.
        $response->assertSee('1.21.8');
        $response->assertSee('26.2-rc-2');
    }

    public function test_the_startup_tab_offers_the_picker_and_keeps_the_other_variables(): void
    {
        $this->fakeMcJars();

        $response = $this->actingAs($this->owner)->get(route('server.startup', $this->server));

        $response->assertOk();
        $response->assertSee('Server Software');
        $response->assertSee('Show Snapshots And Pre-Releases');
        // Everything the picker does not own still renders as it always did.
        $response->assertSee('Max Players');
    }

    public function test_the_lookup_endpoints_answer_with_versions_and_builds(): void
    {
        $this->fakeMcJars();

        $versions = $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PURPUR']));
        $versions->assertOk();
        $versions->assertJsonPath('ok', true);
        $versions->assertJsonPath('versions.0.id', '1.21.8');
        $versions->assertJsonPath('versions.2.channel', 'SNAPSHOT');

        $builds = $this->actingAs($this->admin)->getJson(route('minecraft.builds', ['type' => 'PAPER', 'version' => '1.21.8']));
        $builds->assertOk();
        $builds->assertJsonPath('ok', true);
        // "#60" is what MCJars calls the build; PAPER_BUILD wants "60".
        $builds->assertJsonPath('builds.0.value', '60');
        $builds->assertJsonPath('builds.0.label', '#60');
    }

    public function test_a_fabric_style_build_is_pinned_by_its_loader_version_not_a_number(): void
    {
        Http::fake([
            '*/api/v3/builds/types/FABRIC/versions/*' => Http::response([
                'builds' => ['data' => [
                    ['uuid' => 'c', 'version_id' => '1.21.8', 'project_version_id' => '0.19.3',
                        'type' => 'FABRIC', 'experimental' => false, 'name' => '0.19.3', 'installation' => [], 'changes' => []],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('minecraft.builds', ['type' => 'FABRIC', 'version' => '1.21.8']));

        $response->assertJsonPath('builds.0.value', '0.19.3');
    }

    // -------------------------------------------------------- only minecraft

    public function test_a_template_without_an_mcjars_document_gets_no_picker(): void
    {
        $this->fakeMcJars();

        $this->assertNull($this->palworld->mcjarsPicker());

        $response = $this->actingAs($this->admin)->get(route('admin.servers.create'));

        $response->assertOk();
        // One picker on the page, for the one template that asked for it.
        $this->assertSame(1, substr_count($response->getContent(), 'Server Software'));
    }

    // ------------------------------------------------------------- failures

    public function test_a_timeout_leaves_the_wizard_working_with_plain_text_boxes(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out after 4000 milliseconds'));

        $response = $this->actingAs($this->admin)->get(route('admin.servers.create'));

        $response->assertOk();
        $response->assertSee('Live Version List Unavailable');
        // The three variables the picker would have taken over are still on the
        // page as their own controls, so a server can still be created.
        $response->assertSee('Minecraft Version');
        $response->assertSee('Paper Build');
        $response->assertSee('name="variables['.$this->minecraft->variables->firstWhere('env_variable', 'VERSION')->id.']"', false);
    }

    public function test_a_malformed_response_is_treated_as_no_answer_at_all(): void
    {
        // 200, JSON, and nothing like the documented shape.
        Http::fake(['*' => Http::response(['hello' => 'world'], 200)]);

        $response = $this->actingAs($this->admin)->get(route('admin.servers.create'));

        $response->assertOk();
        $response->assertSee('Live Version List Unavailable');
        $response->assertDontSee('Show Snapshots And Pre-Releases');
    }

    public function test_a_server_error_from_mcjars_is_not_an_error_from_the_panel(): void
    {
        Http::fake(['*' => Http::response('upstream exploded', 503)]);

        $this->actingAs($this->owner)->get(route('server.startup', $this->server))->assertOk();

        $lookup = $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PAPER']));
        $lookup->assertOk();
        $lookup->assertJsonPath('ok', false);
        $lookup->assertJsonPath('versions', []);
    }

    public function test_an_unknown_type_is_refused_before_it_reaches_mcjars(): void
    {
        Http::fake();

        // A path traversal dressed up as a server type. It never leaves the
        // panel, and the answer is the same ok:false the browser already knows
        // how to fall back from.
        $response = $this->actingAs($this->admin)
            ->getJson(route('minecraft.versions', ['type' => '../../etc/passwd']));

        $response->assertOk();
        $response->assertJsonPath('ok', false);

        $this->actingAs($this->admin)
            ->getJson(route('minecraft.builds', ['type' => 'PAPER', 'version' => '../secrets']))
            ->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------- caching

    public function test_a_version_list_is_fetched_once_and_then_served_from_cache(): void
    {
        $this->fakeMcJars();

        $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PAPER']))->assertOk();
        $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PAPER']))->assertOk();
        $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PAPER']))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_the_last_good_answer_is_served_when_mcjars_stops_answering(): void
    {
        $this->fakeMcJars();
        $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PAPER']))->assertOk();

        // The fresh copies expire, both the assembled list and the page it was
        // built from, MCJars falls over, and the panel still serves the
        // dropdown it had rather than dropping the operator back to a text box.
        Cache::forget('mcjars:v1:versions:PAPER');
        Cache::forget('mcjars:v1:versions:PAPER:p1');
        Http::fake(fn () => throw new ConnectionException('gone'));

        $again = $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PAPER']));

        $again->assertJsonPath('ok', true);
        $again->assertJsonPath('versions.0.id', '1.21.8');
    }

    public function test_a_failure_is_remembered_briefly_so_a_dead_service_is_not_hammered(): void
    {
        Http::fake(fn () => throw new ConnectionException('gone'));

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->admin)->getJson(route('minecraft.versions', ['type' => 'PAPER']))->assertOk();
        }

        // One attempt, plus the single retry the client is allowed. Not ten.
        $this->assertLessThanOrEqual(2, count(Http::recorded()));
    }

    // ------------------------------------------------------------ the values

    public function test_a_chosen_type_and_version_reach_server_variables(): void
    {
        $this->fakeMcJars();

        $type = $this->minecraft->variables->firstWhere('env_variable', 'TYPE');
        $version = $this->minecraft->variables->firstWhere('env_variable', 'VERSION');
        $build = $this->minecraft->variables->firstWhere('env_variable', 'PURPUR_BUILD');
        $paperBuild = $this->minecraft->variables->firstWhere('env_variable', 'PAPER_BUILD');

        $response = $this->actingAs($this->admin)->put(route('server.startup.update', $this->server), [
            'variables' => [
                $type->id => 'PURPUR',
                $version->id => '1.21.8',
                $build->id => '2568',
                // The inactive family's field keeps posting what it held, which
                // is exactly what the hidden inputs in the picker do.
                $paperBuild->id => '',
            ],
        ]);

        $response->assertRedirect();

        $this->assertSame('PURPUR', ServerVariable::where('server_id', $this->server->id)->where('template_variable_id', $type->id)->value('value'));
        $this->assertSame('1.21.8', ServerVariable::where('server_id', $this->server->id)->where('template_variable_id', $version->id)->value('value'));
        $this->assertSame('2568', ServerVariable::where('server_id', $this->server->id)->where('template_variable_id', $build->id)->value('value'));

        // And it reaches the daemon, which is the only place any of it matters.
        $env = $this->server->fresh()->load('template.variables', 'variables.variable')->environment();
        $this->assertSame('PURPUR', $env['TYPE']);
        $this->assertSame('1.21.8', $env['VERSION']);
        $this->assertSame('2568', $env['PURPUR_BUILD']);
    }

    public function test_the_chosen_software_is_shown_without_opening_a_form(): void
    {
        $this->fakeMcJars();

        $type = $this->minecraft->variables->firstWhere('env_variable', 'TYPE');
        $version = $this->minecraft->variables->firstWhere('env_variable', 'VERSION');
        $build = $this->minecraft->variables->firstWhere('env_variable', 'PAPER_BUILD');

        ServerVariable::where('server_id', $this->server->id)->where('template_variable_id', $type->id)->update(['value' => 'PAPER']);
        ServerVariable::where('server_id', $this->server->id)->where('template_variable_id', $version->id)->update(['value' => '1.21.8']);
        ServerVariable::where('server_id', $this->server->id)->where('template_variable_id', $build->id)->update(['value' => '60']);

        $summary = $this->server->fresh()->load('template.variables', 'variables.variable')->minecraft();
        $this->assertSame(['type' => 'PAPER', 'version' => '1.21.8', 'build' => '60'], $summary);

        $response = $this->actingAs($this->admin)->get(route('admin.servers.show', $this->server));
        $response->assertOk();
        $response->assertSee('Server Software');
        $response->assertSee('Pinned to build 60');
    }

    public function test_a_non_minecraft_server_has_no_software_summary(): void
    {
        $this->server->update(['template_id' => $this->palworld->id]);

        $this->assertNull($this->server->fresh()->load('template.variables', 'variables.variable')->minecraft());
    }
}
