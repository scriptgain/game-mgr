<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Location;
use App\Models\Mod;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\User;
use App\Services\Mods\ModTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Mods tab against a faked Modrinth.
 *
 * Four things have to be true and none of them are obvious from reading the
 * happy path:
 *
 *   1. A Paper server is never offered a Forge mod. The filtering happens in
 *      the facets sent to Modrinth, so the test asserts on the request, not on
 *      a response somebody wrote to pass.
 *
 *   2. A file whose digest does not match what the API published never reaches
 *      the node. A jar is executed at boot; this is the one check that cannot
 *      be a warning.
 *
 *   3. Modrinth being down degrades the page. It does not 500 it, and it does
 *      not empty the list of what is already installed.
 *
 *   4. Disabling renames rather than deletes, because "turn this off" and
 *      "throw this away" are different requests and only one of them is
 *      reversible.
 */
class ModInstallTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Server $paper;

    private Server $forge;

    /** The bytes every faked download serves, and the digests they really have. */
    private string $jar = 'PK'."\x03\x04".'pretend this is a plugin jar';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret1234', 'role' => 'client',
        ]);

        $location = Location::create(['short' => 'test', 'name' => 'Test']);
        $node = Node::create([
            'name' => 'test-node', 'location_id' => $location->id, 'fqdn' => '127.0.0.1',
            'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'runtimes' => ['docker'],
        ]);
        $game = Game::create(['name' => 'Minecraft']);

        $this->paper = $this->server($node, $this->template($game, 'Paper', 'PAPER', '1.21.4'), 'Survival');
        $this->forge = $this->server($node, $this->template($game, 'Forge', 'FORGE', '1.20.1'), 'Modded');
    }

    private function template(Game $game, string $name, string $type, string $version): Template
    {
        $template = Template::create([
            'game_id' => $game->id, 'name' => $name, 'runtime' => 'docker',
            'mod_sources' => ['modrinth', 'curseforge'],
        ]);

        TemplateVariable::create(['template_id' => $template->id, 'name' => 'Server Type', 'env_variable' => 'TYPE', 'default_value' => $type]);
        TemplateVariable::create(['template_id' => $template->id, 'name' => 'Minecraft Version', 'env_variable' => 'VERSION', 'default_value' => $version]);

        return $template;
    }

    private function server(Node $node, Template $template, string $name): Server
    {
        return Server::create([
            'name' => $name, 'owner_id' => $this->owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker',
            'memory' => 2048, 'disk' => 10240, 'cpu' => 200,
        ]);
    }

    // ------------------------------------------------------------- catalogue

    /**
     * A search hit shaped the way Modrinth really shapes one, trimmed to the
     * fields this panel reads.
     */
    private function hit(string $id, string $slug, string $title, string $type = 'mod'): array
    {
        return [
            'project_id' => $id, 'slug' => $slug, 'title' => $title,
            'description' => $title.' does a thing.', 'author' => 'somebody',
            'downloads' => 1234, 'icon_url' => null, 'project_type' => $type,
            'categories' => ['paper'], 'versions' => ['1.21.3', '1.21.4'],
        ];
    }

    /** One version, with a file whose digests match $this->jar for real. */
    private function version(array $loaders = ['paper', 'spigot', 'bukkit'], string $number = '5.5.71', array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'b0mk8uS6',
            'project_id' => 'Vebnzrzj',
            'name' => 'v'.$number,
            'version_number' => $number,
            'version_type' => 'release',
            'loaders' => $loaders,
            'game_versions' => ['1.21.4'],
            'files' => [[
                'url' => 'https://cdn.modrinth.com/data/Vebnzrzj/versions/b0mk8uS6/LuckPerms-Bukkit-'.$number.'.jar',
                'filename' => 'LuckPerms-Bukkit-'.$number.'.jar',
                'primary' => true,
                'size' => strlen($this->jar),
                'hashes' => ['sha1' => sha1($this->jar), 'sha512' => hash('sha512', $this->jar)],
            ]],
        ], $overrides);
    }

    /**
     * Every call the install path makes, faked. Order matters: the version
     * endpoint lives under the project endpoint's path, so its pattern has to
     * be registered first or the project stub swallows it.
     */
    private function fakeModrinth(array $versions, array $hits = [], ?string $body = null): void
    {
        Http::fake([
            'api.modrinth.com/v2/search*' => Http::response(['hits' => $hits, 'total_hits' => count($hits), 'offset' => 0, 'limit' => 20]),
            'api.modrinth.com/v2/project/*/version*' => Http::response($versions),
            'api.modrinth.com/v2/project/*/members' => Http::response([
                ['role' => 'Developer', 'user' => ['username' => 'somebody-else']],
                ['role' => 'Owner', 'user' => ['username' => 'Luck']],
            ]),
            'api.modrinth.com/v2/project/*' => Http::response([
                'id' => 'Vebnzrzj', 'slug' => 'luckperms', 'title' => 'LuckPerms',
                'description' => 'Permissions, done properly.', 'project_type' => 'mod',
            ]),
            'cdn.modrinth.com/*' => Http::response($body ?? $this->jar),
            '*/api/servers/*/files/upload*' => Http::response(['ok' => true, 'path' => '/plugins/x.jar', 'bytes' => strlen($this->jar)]),
            '*' => Http::response(['ok' => true]),
        ]);
    }

    // ------------------------------------------------------------------ tests

    public function test_search_is_narrowed_to_the_loader_and_version_the_server_runs(): void
    {
        $this->fakeModrinth([], [$this->hit('Vebnzrzj', 'luckperms', 'LuckPerms')]);

        $this->actingAs($this->owner)
            ->get(route('server.mods.browse', $this->paper).'?q=permissions')
            ->assertOk()
            ->assertSee('LuckPerms')
            ->assertSee('Minecraft 1.21.4');

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/v2/search')) {
                return false;
            }

            $facets = urldecode((string) $request->data()['facets']);

            // The loaders a Paper server can load, and nothing else. A Forge
            // only mod cannot come back through a facet that never asks for it.
            return str_contains($facets, 'categories:paper')
                && str_contains($facets, 'categories:spigot')
                && str_contains($facets, 'categories:bukkit')
                && ! str_contains($facets, 'categories:forge')
                && str_contains($facets, 'versions:1.21.4');
        });
    }

    public function test_the_directory_follows_the_loader_rather_than_a_hardcoded_guess(): void
    {
        $forge = ModTarget::for($this->forge);
        $paper = ModTarget::for($this->paper);

        $this->assertSame(['forge'], $forge->loaders);
        $this->assertSame('mods', $forge->directory);
        $this->assertSame('1.20.1', $forge->gameVersion);

        $this->assertSame(['paper', 'spigot', 'bukkit'], $paper->loaders);
        $this->assertSame('plugins', $paper->directory);

        // A project that publishes a Bukkit build and a Fabric build under one
        // id is placed by the version that was chosen, not by the project.
        $this->assertSame('mods', $forge->directoryFor(['forge']));
        $this->assertSame('plugins', $paper->directoryFor(['paper', 'fabric']));

        // A file that shares no loader with the server is refused rather than
        // dropped into whichever directory the profile happens to name.
        $this->assertNull($paper->directoryFor(['fabric']));

        // Nothing declared at all still resolves, because "plugins" for a Paper
        // server is not a guess.
        $this->assertSame('plugins', $paper->directoryFor([]));
    }

    public function test_an_unpinned_minecraft_version_is_reported_rather_than_faked(): void
    {
        // LATEST is the Paper template default and genuinely means "whatever is
        // newest at boot". Sending versions:LATEST to Modrinth matches nothing.
        $this->paper->template->variables()->where('env_variable', 'VERSION')->update(['default_value' => 'LATEST']);
        $this->paper->refresh();

        $target = ModTarget::for($this->paper);

        $this->assertFalse($target->versionKnown());
        $this->assertNull($target->gameVersion);

        $this->fakeModrinth([], [$this->hit('Vebnzrzj', 'luckperms', 'LuckPerms')]);

        $this->actingAs($this->owner)
            ->get(route('server.mods.browse', $this->paper).'?q=permissions')
            ->assertOk()
            ->assertSee('Minecraft version is not pinned', false);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/v2/search')) {
                return false;
            }

            return ! str_contains(urldecode((string) $request->data()['facets']), 'versions:');
        });
    }

    public function test_a_modpack_is_dropped_from_the_results(): void
    {
        $this->fakeModrinth([], [
            $this->hit('Vebnzrzj', 'luckperms', 'LuckPerms'),
            $this->hit('pack1234', 'all-the-mods', 'All The Mods 9', 'modpack'),
        ]);

        $this->actingAs($this->owner)
            ->get(route('server.mods.browse', $this->paper).'?q=mods')
            ->assertOk()
            ->assertSee('LuckPerms')
            ->assertDontSee('All The Mods 9');
    }

    public function test_installing_downloads_verifies_and_writes_the_jar_to_the_node(): void
    {
        $this->fakeModrinth([$this->version()]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => 'Vebnzrzj'])
            ->assertRedirect(route('server.mods', $this->paper))
            ->assertSessionHas('status');

        $mod = Mod::where('server_id', $this->paper->id)->first();

        $this->assertNotNull($mod);
        $this->assertSame('modrinth', $mod->source);
        $this->assertSame('Vebnzrzj', $mod->remote_id);
        $this->assertSame('Luck', $mod->author);
        $this->assertSame('5.5.71', $mod->version);
        $this->assertSame('/plugins/LuckPerms-Bukkit-5.5.71.jar', $mod->path);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/files/upload')
            && str_contains($r->url(), rawurlencode('/plugins/LuckPerms-Bukkit-5.5.71.jar')));
    }

    public function test_a_file_that_fails_its_checksum_never_reaches_the_node(): void
    {
        // The API publishes the digest of $this->jar; the CDN serves something
        // else. That is exactly the case the check exists for.
        $this->fakeModrinth([$this->version()], body: 'this is not the jar that was published');

        $this->actingAs($this->owner)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => 'Vebnzrzj'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('mods', 0);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/files/upload'));
    }

    public function test_a_file_with_no_published_checksum_is_refused(): void
    {
        $version = $this->version();
        $version['files'][0]['hashes'] = [];

        $this->fakeModrinth([$version]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => 'Vebnzrzj'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('mods', 0);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/files/upload'));
    }

    public function test_a_file_larger_than_the_cap_is_refused_before_it_is_fetched(): void
    {
        config(['mods.max_bytes' => 1024]);

        $version = $this->version();
        $version['files'][0]['size'] = 90_000_000;

        $this->fakeModrinth([$version]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => 'Vebnzrzj'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('mods', 0);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'cdn.modrinth.com'));
    }

    public function test_a_file_hosted_somewhere_other_than_modrinth_is_refused(): void
    {
        $version = $this->version();
        $version['files'][0]['url'] = 'https://example.invalid/evil.jar';

        $this->fakeModrinth([$version]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => 'Vebnzrzj'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('mods', 0);
    }

    public function test_a_project_with_no_compatible_version_is_refused(): void
    {
        $this->fakeModrinth([]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => 'Vebnzrzj'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('mods', 0);
    }

    public function test_a_timeout_degrades_the_pages_instead_of_breaking_them(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $mod = Mod::create([
            'server_id' => $this->paper->id, 'source' => 'modrinth', 'remote_id' => 'Vebnzrzj',
            'name' => 'LuckPerms', 'slug' => 'luckperms', 'version' => '5.5.70',
            'path' => '/plugins/LuckPerms-Bukkit-5.5.70.jar', 'enabled' => true,
        ]);

        // The installed list is a database read and must not care.
        $this->actingAs($this->owner)
            ->get(route('server.mods', $this->paper))
            ->assertOk()
            ->assertSee('LuckPerms');

        // The browse screen says so rather than showing an empty catalogue,
        // which would read as "there is nothing called that".
        $this->actingAs($this->owner)
            ->get(route('server.mods.browse', $this->paper).'?q=permissions')
            ->assertOk()
            ->assertSee('Modrinth Did Not Answer');

        // And an update against a dead API changes nothing rather than half
        // removing the mod that is installed and working.
        $this->actingAs($this->owner)
            ->post(route('server.mods.update', [$this->paper, $mod]))
            ->assertSessionHas('error');

        $this->assertSame('5.5.70', $mod->fresh()->version);
    }

    public function test_disabling_renames_the_file_rather_than_deleting_it(): void
    {
        $this->fakeModrinth([]);

        $mod = Mod::create([
            'server_id' => $this->paper->id, 'source' => 'modrinth', 'remote_id' => 'Vebnzrzj',
            'name' => 'LuckPerms', 'slug' => 'luckperms', 'version' => '5.5.71',
            'path' => '/plugins/LuckPerms-Bukkit-5.5.71.jar', 'enabled' => true,
        ]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.toggle', [$this->paper, $mod]))
            ->assertSessionHas('status');

        $mod->refresh();

        $this->assertFalse($mod->enabled);
        $this->assertSame('/plugins/LuckPerms-Bukkit-5.5.71.jar.disabled', $mod->path);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/files/rename')
            && $r['to'] === '/plugins/LuckPerms-Bukkit-5.5.71.jar.disabled');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/files/delete'));

        // And back on again, with the suffix taken off.
        $this->actingAs($this->owner)
            ->post(route('server.mods.toggle', [$this->paper, $mod]), ['enabled' => '1'])
            ->assertSessionHas('status');

        $this->assertTrue($mod->fresh()->enabled);
        $this->assertSame('/plugins/LuckPerms-Bukkit-5.5.71.jar', $mod->fresh()->path);
    }

    public function test_removing_deletes_the_file_and_the_row(): void
    {
        $this->fakeModrinth([]);

        $mod = Mod::create([
            'server_id' => $this->paper->id, 'source' => 'modrinth', 'remote_id' => 'Vebnzrzj',
            'name' => 'LuckPerms', 'slug' => 'luckperms', 'version' => '5.5.71',
            'path' => '/plugins/LuckPerms-Bukkit-5.5.71.jar', 'enabled' => true,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('server.mods.destroy', [$this->paper, $mod]))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('mods', 0);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/files/delete')
            && $r['paths'] === ['/plugins/LuckPerms-Bukkit-5.5.71.jar']);
    }

    public function test_updating_replaces_the_file_and_removes_the_old_one(): void
    {
        $this->fakeModrinth([$this->version(number: '5.5.72')]);

        $mod = Mod::create([
            'server_id' => $this->paper->id, 'source' => 'modrinth', 'remote_id' => 'Vebnzrzj',
            'name' => 'LuckPerms', 'slug' => 'luckperms', 'version' => '5.5.71', 'latest_version' => '5.5.72',
            'path' => '/plugins/LuckPerms-Bukkit-5.5.71.jar', 'enabled' => true,
        ]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.update', [$this->paper, $mod]))
            ->assertSessionHas('status');

        $mod->refresh();

        $this->assertSame('5.5.72', $mod->version);
        $this->assertSame('/plugins/LuckPerms-Bukkit-5.5.72.jar', $mod->path);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/files/delete')
            && $r['paths'] === ['/plugins/LuckPerms-Bukkit-5.5.71.jar']);
    }

    public function test_a_subuser_without_install_cannot_install(): void
    {
        $this->fakeModrinth([$this->version()]);

        $stranger = User::create([
            'name' => 'Stranger', 'email' => 'stranger@test.local', 'password' => 'secret1234', 'role' => 'client',
        ]);

        $this->actingAs($stranger)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => 'Vebnzrzj'])
            ->assertForbidden();

        $this->assertDatabaseCount('mods', 0);
    }

    public function test_a_project_id_that_is_not_one_is_rejected(): void
    {
        $this->fakeModrinth([$this->version()]);

        $this->actingAs($this->owner)
            ->post(route('server.mods.store', $this->paper), ['source' => 'modrinth', 'project' => '../../etc/passwd'])
            ->assertSessionHasErrors('project');

        $this->assertDatabaseCount('mods', 0);
    }
}
