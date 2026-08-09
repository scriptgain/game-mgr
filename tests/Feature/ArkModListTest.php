<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\Setting;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\User;
use App\Services\Mods\ModInstaller;
use App\Services\Mods\ModTarget;
use App\Services\Mods\Sources\CurseForgeSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ARK, which breaks both assumptions the mod installer was built on.
 *
 * The first: that CurseForge means Minecraft. It does not, and the client
 * hardcoded gameId 432, so an ARK owner searching for a structures mod was
 * shown Minecraft mods with nothing to say anything was wrong.
 *
 * The second: that installing a mod ends with a file somewhere. Wildcard moved
 * ASA modding to CurseForge and switched distribution off, so every ASA mod
 * answers `allowModDistribution: false` and there is no file for anyone to
 * fetch. The server takes a list of ids in MOD_IDS and downloads them itself.
 *
 * Both are pinned here because I got both wrong first time, and the TODO said
 * to build .z unpacking for a game we do not even ship a template for.
 */
class ArkModListTest extends TestCase
{
    use RefreshDatabase;

    private Server $ark;

    private TemplateVariable $modIds;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        Setting::putSecret('mods_curseforge_key', 'test-key');

        $owner = User::create(['name' => 'O', 'email' => 'o@test.local', 'password' => 'secret1234', 'role' => 'user']);
        $location = Location::create(['short' => 'lax', 'name' => 'LA']);
        $node = Node::create([
            'name' => 'lax1', 'location_id' => $location->id, 'fqdn' => '10.0.0.1',
            'daemon_port' => 8942, 'memory' => 16384, 'disk' => 102400, 'cpu' => 800, 'runtimes' => ['docker'],
        ]);
        $game = Game::create(['name' => 'ARK: Survival Ascended']);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'ARK ASA', 'runtime' => 'docker',
            'steam_app_id' => 2430930, 'curseforge_game_id' => 83374,
            'mod_sources' => ['curseforge'],
        ]);
        $this->modIds = TemplateVariable::create([
            'template_id' => $template->id, 'name' => 'Mod IDs', 'env_variable' => 'MOD_IDS',
            'default_value' => '', 'rules' => 'nullable|string', 'user_viewable' => true, 'user_editable' => true,
        ]);

        $this->ark = Server::create([
            'name' => 'Island', 'owner_id' => $owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker',
            'memory' => 8192, 'disk' => 40960, 'cpu' => 300,
        ]);
    }

    private function target(): ModTarget
    {
        return ModTarget::for($this->ark->fresh()->load('template.variables'));
    }

    /** The bug: CurseForge is not Minecraft-only. */
    public function test_an_ark_server_searches_ark_and_not_minecraft(): void
    {
        $seen = null;
        Http::fake(function ($request) use (&$seen) {
            $seen = $request->url();

            return Http::response(['data' => []], 200);
        });

        app(CurseForgeSource::class)->search('structures', $this->target());

        $this->assertStringContainsString('gameId=83374', (string) $seen);
        $this->assertStringNotContainsString('gameId=432', (string) $seen);
        // Loader and class describe Minecraft. Sent for another game they
        // filter everything out, which reads as "no results" rather than a bug.
        $this->assertStringNotContainsString('classId', (string) $seen);
        $this->assertStringNotContainsString('modLoaderType', (string) $seen);
    }

    /** ARK has no Minecraft loader and must not be refused for lacking one. */
    public function test_curseforge_serves_ark_despite_it_having_no_mod_loader(): void
    {
        $target = $this->target();

        $this->assertNull($target->loader, 'ARK has no Minecraft loader, by definition');
        $this->assertTrue(app(CurseForgeSource::class)->supports($target));
        $this->assertTrue(app(CurseForgeSource::class)->managesByList($target));
    }

    public function test_installing_writes_the_id_into_mod_ids_and_downloads_nothing(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'id' => 940975, 'name' => 'Cybers Structures QoL+', 'slug' => 'cybers-structures',
            'summary' => 'Structures.', 'downloadCount' => 27653335,
            'allowModDistribution' => false,
            'links' => ['websiteUrl' => 'https://www.curseforge.com/ark-survival-ascended/mods/cybers-structures'],
        ]], 200)]);

        $result = app(ModInstaller::class)->install(
            $this->ark->fresh()->load('node', 'template.variables'),
            $this->target(),
            'curseforge',
            '940975',
        );

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame('940975', $this->ark->fresh()->environment()['MOD_IDS']);

        $mod = $result['mod'];
        $this->assertSame('MOD_IDS', $mod->path, 'the "path" for a listed mod is the variable, not a file');
        $this->assertFalse($mod->verified, 'nothing was downloaded, so nothing was checked');
        $this->assertSame(0, $mod->bytes);

        // The thing that must NOT have happened: no file fetched from a CDN.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'forgecdn.net'));
    }

    /** A second mod appends rather than replacing the first. */
    public function test_a_second_mod_joins_the_list(): void
    {
        ServerVariable::create([
            'server_id' => $this->ark->id, 'template_variable_id' => $this->modIds->id, 'value' => '111111',
        ]);

        Http::fake(['*' => Http::response(['data' => [
            'id' => 940975, 'name' => 'Second', 'slug' => 'second', 'allowModDistribution' => false,
        ]], 200)]);

        app(ModInstaller::class)->install(
            $this->ark->fresh()->load('node', 'template.variables'),
            $this->target(),
            'curseforge',
            '940975',
        );

        $this->assertSame('111111,940975', $this->ark->fresh()->environment()['MOD_IDS']);
    }

    /** Removing edits the list back rather than trying to delete a file. */
    public function test_removing_takes_it_out_of_the_list(): void
    {
        ServerVariable::create([
            'server_id' => $this->ark->id, 'template_variable_id' => $this->modIds->id, 'value' => '111111,940975',
        ]);

        $mod = \App\Models\Mod::create([
            'server_id' => $this->ark->id, 'source' => 'curseforge', 'remote_id' => '940975',
            'name' => 'Cybers', 'slug' => 'cybers', 'version' => 'managed by the server',
            'latest_version' => 'managed by the server', 'path' => 'MOD_IDS', 'bytes' => 0,
            'verified' => false, 'enabled' => true, 'installed_at' => now(), 'checked_at' => now(),
        ]);

        $result = app(ModInstaller::class)->remove($this->ark->fresh()->load('node', 'template.variables'), $mod);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame('111111', $this->ark->fresh()->environment()['MOD_IDS']);
        $this->assertDatabaseMissing('mods', ['id' => $mod->id]);
    }

    /** A Minecraft server still downloads a file, and still respects the flag. */
    public function test_minecraft_is_unaffected_and_still_refuses_undistributable_mods(): void
    {
        $game = Game::create(['name' => 'Minecraft']);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Forge', 'runtime' => 'docker',
            'curseforge_game_id' => 432, 'mod_sources' => ['curseforge'],
        ]);
        $mc = Server::create([
            'name' => 'MC', 'owner_id' => $this->ark->owner_id, 'node_id' => $this->ark->node_id,
            'template_id' => $template->id, 'runtime' => 'docker',
            'memory' => 4096, 'disk' => 20480, 'cpu' => 200,
        ]);

        $target = ModTarget::for($mc->fresh()->load('template.variables'));

        $this->assertTrue($target->curseForgeIsMinecraft());
        $this->assertFalse(app(CurseForgeSource::class)->managesByList($target),
            'a Minecraft server must still download files, not write a list');
    }
}
