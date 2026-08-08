<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\Setting;
use App\Models\Subuser;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Config tab end to end, driven the way a customer drives it.
 *
 * The three states a template can put a setting in are the point of this file:
 * editable, visible but locked, and hidden altogether. Getting the last one
 * wrong hands a customer the RCON password the panel authenticates with, and
 * getting the middle one wrong lets them type over a value the allocation owns.
 */
class ConfigEditorTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private User $owner;

    private User $reader;

    private User $stranger;

    private User $admin;

    private const PROPERTIES = "#Minecraft server properties\nmotd=Old\nmax-players=20\npvp=true\n"
        ."level-name=world\nrcon.password=hunter2\n# hand added\nmy-plugin.key=keep me\n";

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $this->reader = User::create(['name' => 'Reader', 'email' => 'reader@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $this->stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret1234', 'role' => 'admin']);

        $location = Location::create(['short' => 'test', 'name' => 'Test']);
        $node = Node::create([
            'name' => 'test-node', 'location_id' => $location->id, 'fqdn' => '127.0.0.1',
            'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'runtimes' => ['docker'],
        ]);

        $game = Game::create(['name' => 'Minecraft', 'slug' => 'minecraft-test']);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Test Paper', 'runtime' => 'docker',
            'config_schema' => [[
                'file' => 'server.properties',
                'format' => 'properties',
                'label' => 'Server Properties',
                'settings' => [
                    ['key' => 'motd', 'name' => 'MOTD', 'env' => 'MOTD', 'rules' => 'required|string|max:60'],
                    ['key' => 'max-players', 'name' => 'Max Players', 'rules' => 'required|integer|between:1,200'],
                    ['key' => 'pvp', 'name' => 'PvP', 'rules' => 'required|in:true,false'],
                    // Visible, and nobody but an admin may type into it.
                    ['key' => 'level-name', 'name' => 'World Folder', 'rules' => 'required|string|max:60', 'user_editable' => false],
                    // Not visible at all: the panel authenticates with it.
                    ['key' => 'rcon.password', 'name' => 'RCON Password', 'rules' => 'nullable|alpha_dash|max:40', 'user_viewable' => false],
                ],
            ]],
        ]);

        TemplateVariable::create([
            'template_id' => $template->id, 'name' => 'MOTD', 'env_variable' => 'MOTD',
            'default_value' => 'Old', 'rules' => 'nullable|string|max:60',
        ]);

        $this->server = Server::create([
            'name' => 'Test Server', 'owner_id' => $this->owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);

        $allocation = Allocation::create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => 25565, 'server_id' => $this->server->id]);
        $this->server->update(['allocation_id' => $allocation->id]);

        Subuser::create([
            'server_id' => $this->server->id, 'user_id' => $this->reader->id,
            'permissions' => ['config.read'],
        ]);
    }

    /** The node, answering with a real server.properties and accepting writes. */
    private function fakeNode(?string $contents = self::PROPERTIES): void
    {
        Http::fake([
            '*/files/contents*' => $contents === null
                ? Http::response('not found', 404)
                : Http::response($contents, 200),
            '*/files/write' => Http::response(['ok' => true], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);
    }

    /** The body of the single write the panel sent to the node. */
    private function written(): ?string
    {
        foreach (Http::recorded() as [$request]) {
            /** @var Request $request */
            if (str_contains($request->url(), '/files/write')) {
                return $request->data()['content'] ?? null;
            }
        }

        return null;
    }

    // ------------------------------------------------------------ visibility

    public function test_a_client_sees_editable_and_locked_settings_but_never_a_hidden_one(): void
    {
        $this->fakeNode();

        $response = $this->actingAs($this->owner)->get(route('server.config', $this->server));

        $response->assertOk();
        $response->assertSee('Max Players');
        $response->assertSee('World Folder');
        // Hidden means hidden: not the label, not the key, not the value.
        $response->assertDontSee('RCON Password');
        $response->assertDontSee('rcon.password');
        $response->assertDontSee('hunter2');

        // Locked renders read only, so there is no input to type into.
        $response->assertDontSee('name="settings[c0_level_name_'.substr(sha1('level-name'), 0, 6).']" type="text"', false);
    }

    public function test_an_admin_sees_the_hidden_setting(): void
    {
        $this->fakeNode();

        $this->actingAs($this->admin)
            ->get(route('server.config', $this->server))
            ->assertOk()
            ->assertSee('RCON Password');
    }

    public function test_the_current_file_contents_are_what_the_form_shows(): void
    {
        $this->fakeNode("motd=From The File\nmax-players=7\npvp=false\nlevel-name=world\n");

        $this->actingAs($this->owner)
            ->get(route('server.config', $this->server))
            ->assertOk()
            ->assertSee('From The File')
            ->assertSee('value="7"', false);
    }

    // ----------------------------------------------------------- permissions

    public function test_permissions_are_enforced_per_action(): void
    {
        $this->fakeNode();

        // Read only: the page opens, the save does not.
        $this->actingAs($this->reader)->get(route('server.config', $this->server))->assertOk();
        $this->actingAs($this->reader)
            ->put(route('server.config.update', $this->server), ['settings' => []])
            ->assertForbidden();

        $this->actingAs($this->stranger)->get(route('server.config', $this->server))->assertForbidden();
    }

    public function test_a_suspended_server_can_be_read_but_not_written(): void
    {
        $this->fakeNode();
        $this->server->update(['status' => 'suspended']);

        $this->actingAs($this->owner)->get(route('server.config', $this->server))->assertOk();
        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), ['settings' => []])
            ->assertForbidden();
    }

    public function test_a_template_with_no_schema_has_no_config_tab(): void
    {
        $this->server->template->update(['config_schema' => null]);

        $this->actingAs($this->owner)
            ->get(route('server.config', $this->server))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- saving

    private function field(string $key): string
    {
        return 'c0_'.str_replace(['.', '-'], '_', $key).'_'.substr(sha1($key), 0, 6);
    }

    public function test_saving_changes_one_line_and_leaves_the_rest_alone(): void
    {
        $this->fakeNode();

        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), [
                'settings' => [
                    $this->field('motd') => 'Old',
                    $this->field('max-players') => '48',
                    $this->field('pvp') => 'true',
                ],
            ])
            ->assertRedirect(route('server.config', $this->server))
            ->assertSessionHas('status');

        $written = $this->written();

        $this->assertNotNull($written, 'Nothing was written to the node.');
        $this->assertStringContainsString("max-players=48\n", $written);
        // Untouched values, the hand added key and the comment all survive.
        $this->assertStringContainsString("motd=Old\n", $written);
        $this->assertStringContainsString("pvp=true\n", $written);
        $this->assertStringContainsString("# hand added\n", $written);
        $this->assertStringContainsString("my-plugin.key=keep me\n", $written);
        $this->assertStringContainsString("rcon.password=hunter2\n", $written);
    }

    public function test_a_locked_setting_is_ignored_even_when_it_is_posted(): void
    {
        $this->fakeNode();

        // The form never renders an input for this, so getting here means
        // somebody built the request by hand.
        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), [
                'settings' => [$this->field('level-name') => 'somewhere-else'],
            ])
            ->assertRedirect(route('server.config', $this->server));

        $this->assertNull($this->written(), 'A locked setting was written anyway.');
        $this->assertStringNotContainsString('somewhere-else', (string) $this->written());
    }

    public function test_a_hidden_setting_is_ignored_even_when_it_is_posted(): void
    {
        $this->fakeNode();

        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), [
                'settings' => [$this->field('rcon.password') => 'stolen'],
            ]);

        $this->assertNull($this->written(), 'A hidden setting was written anyway.');
    }

    public function test_validation_rejects_a_value_the_game_would_reject(): void
    {
        $this->fakeNode();

        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), [
                'settings' => [$this->field('max-players') => '9000'],
            ])
            ->assertSessionHasErrors('settings.'.$this->field('max-players'));

        $this->assertNull($this->written(), 'A rejected save still wrote to the node.');
    }

    public function test_a_setting_that_names_a_variable_writes_the_variable_too(): void
    {
        $this->fakeNode();

        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), [
                'settings' => [$this->field('motd') => 'A New Message'],
            ]);

        $variable = TemplateVariable::where('env_variable', 'MOTD')->firstOrFail();
        $stored = ServerVariable::where('server_id', $this->server->id)
            ->where('template_variable_id', $variable->id)
            ->first();

        // Otherwise the image rewrites server.properties from the environment
        // on the very restart that was supposed to apply the change.
        $this->assertNotNull($stored, 'The linked startup variable was not kept in step.');
        $this->assertSame('A New Message', $stored->value);
    }

    public function test_a_file_that_does_not_exist_yet_is_explained_rather_than_created(): void
    {
        $this->fakeNode(null);

        $this->actingAs($this->owner)
            ->get(route('server.config', $this->server))
            ->assertOk()
            ->assertSee('Not Written Yet');

        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), [
                'settings' => [$this->field('max-players') => '48'],
            ])
            ->assertSessionHas('error');

        $this->assertNull($this->written(), 'A missing file was created from the form.');
    }

    // ------------------------------------------------------- running servers

    public function test_a_save_marks_the_server_as_needing_a_restart(): void
    {
        $this->fakeNode();
        $this->server->update(['power_state' => 'running', 'last_started_at' => now()->subHour()]);

        $this->actingAs($this->owner)
            ->put(route('server.config.update', $this->server), [
                'settings' => [$this->field('max-players') => '48'],
            ])
            ->assertSessionHas('status');

        $this->assertTrue($this->server->fresh()->configNeedsRestart());

        // And the page says so rather than letting somebody believe it is live.
        $this->actingAs($this->owner)
            ->get(route('server.config', $this->server))
            ->assertSee('Changes Apply on Restart');
    }

    public function test_restarting_clears_the_restart_notice(): void
    {
        $this->server->update([
            'power_state' => 'running',
            'config_dirty_at' => now()->subHour(),
            'last_started_at' => now(),
        ]);

        $this->assertFalse($this->server->fresh()->configNeedsRestart());
    }

    public function test_an_offline_server_is_never_told_to_restart(): void
    {
        $this->server->update(['power_state' => 'offline', 'config_dirty_at' => now()]);

        $this->assertFalse($this->server->fresh()->configNeedsRestart());
    }
}
