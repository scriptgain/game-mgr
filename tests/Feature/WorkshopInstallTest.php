<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\Mods\ModInstaller;
use App\Services\Mods\ModTarget;
use App\Services\Mods\Sources\WorkshopSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Steam Workshop, which is the odd one out: the panel never sees the file.
 *
 * Valve serves Workshop content only to an authenticated Steam client, so
 * steamcmd on the node fetches it and the panel records where it went. What is
 * worth pinning is exactly that: the install must reach the NODE, and the row
 * must not claim a checksum nobody could have checked.
 */
class WorkshopInstallTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $owner = User::create(['name' => 'O', 'email' => 'o@test.local', 'password' => 'secret1234', 'role' => 'user']);
        $location = Location::create(['short' => 'lax', 'name' => 'LA']);
        $node = Node::create([
            'name' => 'lax1', 'location_id' => $location->id, 'fqdn' => '10.0.0.1',
            'daemon_port' => 8942, 'memory' => 8192, 'disk' => 51200, 'cpu' => 400,
            'runtimes' => ['steamcmd'],
        ]);
        $game = Game::create(['name' => 'Counter-Strike 2']);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'CS2', 'runtime' => 'steamcmd',
            'steam_app_id' => 730, 'mod_sources' => ['workshop'],
        ]);

        $this->server = Server::create([
            'name' => 'Scrims', 'owner_id' => $owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'steamcmd',
            'memory' => 2048, 'disk' => 10240, 'cpu' => 100,
        ]);
        $allocation = Allocation::create([
            'node_id' => $node->id, 'ip' => '10.0.0.1', 'port' => 27015, 'server_id' => $this->server->id,
        ]);
        $this->server->forceFill(['allocation_id' => $allocation->id])->save();
    }

    /** A bare id, or whatever they pasted out of the address bar. */
    public function test_an_item_is_recognised_from_an_id_or_a_whole_url(): void
    {
        $this->assertSame('3070284530', WorkshopSource::cleanId('3070284530'));
        $this->assertSame('3070284530', WorkshopSource::cleanId(
            'https://steamcommunity.com/sharedfiles/filedetails/?id=3070284530'
        ));
        $this->assertNull(WorkshopSource::cleanId('../../etc/passwd'));
        $this->assertNull(WorkshopSource::cleanId('not-an-id'));
    }

    public function test_the_install_goes_to_the_node_and_is_never_called_verified(): void
    {
        Http::fake([
            // Steam, answering with the item's details. No key involved.
            '*GetPublishedFileDetails*' => Http::response([
                'response' => ['publishedfiledetails' => [[
                    'result' => 1,
                    'publishedfileid' => '3070284530',
                    'title' => 'de_cbble',
                    'short_description' => 'A map.',
                    'time_updated' => 1735689600,
                ]]],
            ], 200),
            // The node, reporting where steamcmd put it.
            '*workshop/install*' => Http::response([
                'ok' => true,
                'path' => '/steamapps/workshop/content/730/3070284530',
            ], 200),
        ]);

        $result = app(ModInstaller::class)->install(
            $this->server->fresh()->load('node', 'template'),
            ModTarget::for($this->server->fresh()->load('template.variables')),
            'workshop',
            '3070284530',
        );

        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $mod = $result['mod'];
        $this->assertSame('workshop', $mod->source);
        $this->assertSame('de_cbble', $mod->name);
        $this->assertSame('/steamapps/workshop/content/730/3070284530', $mod->path);

        // The whole point: the panel never saw the bytes, so it must not imply
        // it checked them.
        $this->assertFalse($mod->verified);

        // And it really went to the node rather than being downloaded here.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/workshop/install')
            && $request['app_id'] === 730
            && $request['item_id'] === 3070284530);
    }

    /** A template with no Steam app id has no Workshop to fetch from. */
    public function test_a_template_with_no_steam_app_id_is_refused(): void
    {
        Http::fake(['*GetPublishedFileDetails*' => Http::response([
            'response' => ['publishedfiledetails' => [[
                'result' => 1, 'publishedfileid' => '1', 'title' => 'x', 'time_updated' => 1,
            ]]],
        ], 200)]);

        $this->server->template->forceFill(['steam_app_id' => 0])->save();

        $result = app(ModInstaller::class)->install(
            $this->server->fresh()->load('node', 'template'),
            ModTarget::for($this->server->fresh()->load('template.variables')),
            'workshop',
            '1',
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('no Steam app id', $result['error']);
    }
}
