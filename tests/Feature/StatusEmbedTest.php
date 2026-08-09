<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\StatusPage;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The status page, and the two other shapes of it people asked for: JSON so
 * they can build their own widget, and a bare card for an iframe on their site.
 *
 * The contract being tested is not "it returns data". It is that all three
 * obey the SAME switches. A page that hides the player count and then hands it
 * out in JSON has not hidden anything, and JSON is the easy one to forget.
 */
class StatusEmbedTest extends TestCase
{
    use RefreshDatabase;

    private StatusPage $page;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $owner = User::create(['name' => 'O', 'email' => 'o@test.local', 'password' => 'secret1234', 'role' => 'user']);
        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $node = Node::create([
            'name' => 'lax1', 'location_id' => $location->id, 'fqdn' => '10.0.0.1',
            'daemon_port' => 8942, 'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'runtimes' => ['docker'],
        ]);
        $game = Game::create(['name' => 'Minecraft']);
        $template = Template::create(['game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker']);

        $this->server = Server::create([
            'name' => 'Survival', 'owner_id' => $owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker',
            'memory' => 2048, 'disk' => 10240, 'cpu' => 100,
        ]);

        $allocation = Allocation::create([
            'node_id' => $node->id, 'ip' => '10.0.0.1', 'port' => 25565, 'server_id' => $this->server->id,
        ]);
        $this->server->forceFill([
            'allocation_id' => $allocation->id,
            'power_state' => 'running',
            'cached_players' => 7,
            'cached_max_players' => 20,
        ])->save();

        $this->page = StatusPage::create([
            'server_id' => $this->server->id, 'slug' => 'survival', 'headline' => 'Survival Status',
            'is_public' => true, 'show_players' => true, 'show_address' => true,
            'show_uptime' => true, 'show_version' => true,
        ]);
    }

    public function test_the_json_carries_the_facts_and_can_be_read_from_any_origin(): void
    {
        $response = $this->get('/status/survival.json')->assertOk();

        $response->assertJsonPath('name', 'Survival Status')
            ->assertJsonPath('online', true)
            ->assertJsonPath('status', 'online')
            ->assertJsonPath('players.online', 7)
            ->assertJsonPath('players.max', 20)
            ->assertJsonPath('address', '10.0.0.1:25565')
            ->assertJsonPath('running', 'Paper');

        // Without this a widget on the owner's own site cannot read it at all.
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }

    /** Hidden on the page means hidden everywhere, or it was never hidden. */
    public function test_the_switches_apply_to_the_json_too(): void
    {
        $this->page->update(['show_players' => false, 'show_address' => false, 'show_version' => false]);

        $this->get('/status/survival.json')
            ->assertOk()
            ->assertJsonMissingPath('players')
            ->assertJsonMissingPath('address')
            ->assertJsonMissingPath('connect')
            ->assertJsonMissingPath('running')
            // Still says whether it is up, which is the point of the page.
            ->assertJsonPath('online', true);
    }

    public function test_the_embed_renders_and_may_be_framed(): void
    {
        $response = $this->get('/status/survival/embed')->assertOk();

        $response->assertSee('Survival Status')->assertSee('10.0.0.1:25565');

        // The one thing an embed is for. Sent as frame-ancestors rather than by
        // clearing X-Frame-Options, because a browser seeing both obeys the CSP.
        $this->assertStringContainsString(
            'frame-ancestors',
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_the_embed_theme_is_pinnable_and_otherwise_follows_the_visitor(): void
    {
        $this->get('/status/survival/embed?theme=dark')->assertOk()->assertSee('data-theme="dark"', false);
        $this->get('/status/survival/embed')->assertOk()->assertSee('data-theme="auto"', false);
        // A junk theme falls back rather than reflecting itself into the page.
        $this->get('/status/survival/embed?theme=%22onload%3D')->assertOk()->assertSee('data-theme="auto"', false);
    }

    /**
     * An unpublished page must 404 in every shape, including the two new ones.
     * Answering on .json when the page is off would confirm the server exists
     * to anybody guessing slugs, which is exactly what the toggle prevents.
     */
    public function test_an_unpublished_page_is_a_404_everywhere(): void
    {
        $this->page->update(['is_public' => false]);

        $this->get('/status/survival')->assertNotFound();
        $this->get('/status/survival.json')->assertNotFound();
        $this->get('/status/survival/embed')->assertNotFound();
    }
}
