<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Editions gate what can be CREATED, and nothing else.
 *
 * The rule that matters most here is the one about what a gate must never do.
 * A licence that lapses, a limit that is reached, a game that is not included:
 * none of them may stop a server, suspend anything, or lock somebody out of the
 * panel. Somebody's Minecraft world does not go offline because a card expired,
 * and the person whose server has fallen over can always get back in to restart
 * it. Several tests here exist only to pin that.
 */
class EditionGateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // No key, no network: every test here runs on the free edition unless it
        // says otherwise. Cached so nothing reaches out to scriptgain.com.
        // Without this the setup middleware redirects every request and the
        // gates never get a chance to be the reason anything was refused.
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        Cache::forget('licence.status');
        $this->onEdition('free');

        $this->admin = User::create([
            'name' => 'Allen', 'email' => 'admin@example.test',
            'password' => 'secret-secret', 'role' => 'admin',
        ]);
    }

    /** Pretend a licence resolved to this edition, without touching the network. */
    private function onEdition(string $edition): void
    {
        Cache::put('licence.status', [
            'state' => $edition === 'free' ? 'unlicensed' : 'valid',
            'ok' => $edition !== 'free',
            'licence' => $edition === 'free' ? null : ['edition' => $edition],
            'message' => 'test',
            'checked_at' => now()->toIso8601String(),
        ], now()->addHour());
    }

    private function location(): Location
    {
        return Location::firstOrCreate(['short' => 'lax'], ['name' => 'Los Angeles']);
    }

    private function node(string $name = 'n1'): Node
    {
        return Node::create([
            'name' => $name, 'location_id' => $this->location()->id,
            'scheme' => 'http', 'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
            'memory' => 64000, 'disk' => 500000, 'cpu' => 800,
        ]);
    }

    private function template(string $gameSlug, array $overrides = []): Template
    {
        $game = Game::firstOrCreate(['slug' => $gameSlug], ['name' => Str::headline($gameSlug)]);

        return Template::create(array_merge([
            'game_id' => $game->id, 'name' => 'T'.Str::random(5),
            'runtime' => 'docker', 'startup' => 'run',
        ], $overrides));
    }

    private function server(Node $node, Template $template): Server
    {
        return Server::create([
            'name' => 'S'.Str::random(4), 'owner_id' => $this->admin->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 512, 'disk' => 1024, 'cpu' => 50, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);
    }

    // ------------------------------------------------------------- the model

    public function test_the_free_edition_is_what_an_install_with_no_key_runs_as(): void
    {
        $this->assertSame('free', Edition::current());
        $this->assertSame('Self-Hosted', Edition::label());
    }

    /**
     * The vendor signs a features map, and the edition rides in it. That is the
     * mechanism scriptgain already had; inventing a second field for the same
     * thing would leave two places to keep in step.
     */
    public function test_the_edition_is_read_from_the_signed_features_map(): void
    {
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true,
            'licence' => ['valid' => true, 'features' => ['edition' => 'pro', 'seats' => 5]],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $this->assertSame('pro', Edition::current());
    }

    /** A vendor that names it directly still works. */
    public function test_a_top_level_edition_field_is_still_honoured(): void
    {
        $this->onEdition('plus');

        $this->assertSame('plus', Edition::current());
    }

    public function test_a_verified_licence_names_the_edition(): void
    {
        $this->onEdition('pro');

        $this->assertSame('pro', Edition::current());
        $this->assertTrue(Edition::atLeast('basic'));
        $this->assertTrue(Edition::atLeast('pro'));
        $this->assertFalse(Edition::atLeast('plus'));
    }

    /**
     * A licence that verifies but names no edition still belongs to somebody who
     * paid. Refusing to grant anything would punish a paying customer for a gap
     * at the vendor's end.
     */
    public function test_a_valid_licence_with_no_edition_named_gets_the_benefit_of_the_doubt(): void
    {
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['valid' => true],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $this->assertSame(config('editions.licensed_default'), Edition::current());
        $this->assertNotSame('free', Edition::current());
    }

    /**
     * Grace counts as licensed. The customer has paid and the vendor being
     * unreachable is not their problem to pay for.
     */
    public function test_the_grace_period_keeps_the_paid_edition(): void
    {
        Cache::put('licence.status', [
            'state' => 'grace', 'ok' => true, 'licence' => ['edition' => 'plus'],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $this->assertSame('plus', Edition::current());
    }

    /** An edition the config does not know about must not unlock everything. */
    public function test_an_unknown_edition_falls_back_to_free(): void
    {
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['edition' => 'enterprise-ultra'],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $this->assertSame('free', Edition::current());
    }

    // -------------------------------------------------------------- the games

    /**
     * Every game is free, deliberately.
     *
     * Withholding games was the wrong line: somebody told to pay before they
     * can run Rust has not yet seen what this panel is worth. Scale and the
     * integration features separate the editions instead.
     */
    public function test_every_game_is_available_on_the_free_edition(): void
    {
        foreach (['minecraft', 'palworld', 'valheim', 'rust', 'counter-strike-2', 'ark-survival-ascended'] as $slug) {
            $this->assertTrue(
                Edition::allowsTemplate($this->template($slug)),
                $slug.' must be runnable on the free edition',
            );
        }
    }

    /** Voice servers are games as far as this is concerned, so they are free too. */
    public function test_voice_servers_are_available_on_the_free_edition(): void
    {
        $this->assertTrue(Edition::allowsTemplate($this->template('teamspeak')));
        $this->assertTrue(Edition::allowsTemplate($this->template('mumble')));
    }

    /**
     * Importing a template is free too.
     *
     * The server count already catches everybody a gate here would have: anyone
     * running this commercially is past five servers on their first day, so
     * gating the import only ever landed on somebody adding one game for their
     * friends, and made the panel a walled garden exactly where the alternative
     * is not.
     */
    public function test_importing_a_template_is_free(): void
    {
        $imported = $this->template('minecraft', ['imported_at' => now()]);

        $this->assertTrue(Edition::allowsTemplate($imported));
        $this->assertTrue(Edition::allows('templates.import'));
    }

    /**
     * Everything except the server count is free, and this is the test that
     * says so. If a feature ever stops being available on free, this fails and
     * whoever did it has to mean it.
     */
    public function test_every_feature_is_available_on_the_free_edition(): void
    {
        foreach (array_keys((array) config('editions.features')) as $feature) {
            $this->assertTrue(Edition::allows($feature), $feature.' must be available on the free edition');
            $this->assertSame('free', Edition::cheapestWith($feature));
        }

        $this->assertSame('free', Edition::cheapestWithGame(Game::firstOrCreate(['slug' => 'rust'], ['name' => 'Rust'])));
    }

    // ------------------------------------------------------------- the limits

    /**
     * Self-hosted has no ceiling at all. The limits belong to the hosted plans,
     * where the panel counting the servers is one we run and the person being
     * counted cannot edit it.
     */
    public function test_self_hosted_has_no_server_limit(): void
    {
        $node = $this->node();
        $template = $this->template('minecraft');

        for ($i = 0; $i < 40; $i++) {
            $this->server($node, $template);
        }

        $this->assertNull(Edition::limit('servers'));
        $this->assertTrue(Edition::roomForServer(), 'self-hosted must never refuse another server');
    }

    /** The hosted plans do have one, and it bites. */
    public function test_a_hosted_plan_refuses_the_next_one_and_says_what_to_do(): void
    {
        $this->onEdition('basic');

        $node = $this->node();
        $template = $this->template('minecraft');

        for ($i = 0; $i < Edition::limit('servers'); $i++) {
            $this->server($node, $template);
        }
        $this->assertFalse(Edition::roomForServer());

        $response = $this->actingAs($this->admin)->post(route('admin.servers.store'), [
            'name' => 'One Too Many',
            'owner_id' => $this->admin->id,
            'node_id' => $node->id,
            'template_id' => $template->id,
            'memory' => 512, 'disk' => 1024, 'cpu' => 50,
            'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);

        // Note the gate runs after validation, so a form has to be otherwise
        // valid before it is told about the edition. That is the right order:
        // being told "you need Pro" and then also "the swap field is required"
        // is worse than being told one thing at a time.
        $response->assertSessionHasErrors('template_id');
        $this->assertStringContainsString('edition covers', session('errors')->first('template_id'));
        $this->assertSame(Edition::limit('servers'), Server::count(), 'the refused server must not have been created');
    }

    public function test_a_larger_hosted_plan_raises_the_ceiling(): void
    {
        $node = $this->node();
        $template = $this->template('minecraft');
        for ($i = 0; $i < 26; $i++) {
            $this->server($node, $template);
        }

        $this->onEdition('basic');
        $this->assertFalse(Edition::roomForServer(), 'basic covers 25');

        $this->onEdition('pro');
        $this->assertTrue(Edition::roomForServer(), 'pro covers 250');
    }

    /**
     * Nodes are not limited on any edition.
     *
     * Somebody who wants a second machine has a reason for it, and charging for
     * the privilege of running your own hardware is a strange thing to sell.
     * The servers running on those machines are what is counted.
     */
    public function test_nodes_are_not_limited(): void
    {
        $this->node('first');
        $this->node('second');
        $this->node('third');

        $this->assertTrue(Edition::roomForNode());
        $this->assertNull(Edition::limit('nodes'), 'nodes are unlimited on every edition');

        $this->actingAs($this->admin)->post(route('admin.nodes.store'), [
            'name' => 'fourth', 'location_id' => $this->location()->id,
            'connection_mode' => 'direct', 'scheme' => 'http', 'fqdn' => '10.0.0.4',
            'daemon_port' => 8942, 'daemon_base' => '/var/lib/gamemgr/volumes',
            'sftp_port' => 2022, 'memory' => 8000, 'disk' => 100000, 'cpu' => 400,
            'upload_size' => 100, 'runtimes' => ['docker' => '1'],  // posted as a map by the toggles, not a list
            'memory_overallocate' => 0, 'disk_overallocate' => 0, 'cpu_overallocate' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, Node::count());
    }

    // ------------------------------------------------------------- the rules

    /**
     * The most important test here. Reaching a limit must not touch anything
     * that already exists: no suspension, no stopping, no deletion.
     */
    public function test_being_over_the_limit_never_touches_what_is_already_running(): void
    {
        $node = $this->node();
        $template = $this->template('minecraft');

        // Deliberately past the free ceiling, as an install that has downgraded
        // would be.
        $servers = [];
        for ($i = 0; $i < 30; $i++) {
            $servers[] = $this->server($node, $template);
        }
        // A hosted install that has downgraded is over its ceiling.
        $this->onEdition('basic');

        $this->assertFalse(Edition::roomForServer());

        foreach ($servers as $server) {
            $fresh = $server->fresh();
            $this->assertNotNull($fresh, 'a server was deleted by a licence check');
            $this->assertFalse($fresh->isSuspended(), 'a server was suspended by a licence check');
        }
        $this->assertSame(30, Server::count());
    }

    /** And the panel stays usable, which is what stops a licence problem becoming an outage. */
    public function test_an_over_limit_install_can_still_be_administered(): void
    {
        $node = $this->node();
        $template = $this->template('minecraft');
        $server = $this->server($node, $template);
        for ($i = 0; $i < 8; $i++) {
            $this->server($node, $template);
        }

        // Not the dashboard: it carries a pre-existing MySQL-only DATE_FORMAT
        // query that 500s under sqlite, which has nothing to do with editions
        // and would make this test fail for the wrong reason.
        $this->actingAs($this->admin)->get(route('admin.servers.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.nodes.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('server.console', $server))->assertOk();
        $this->actingAs($this->admin)->get(route('settings.licence.edit'))->assertOk();
    }

    public function test_the_licence_page_renders_on_every_edition(): void
    {
        foreach (array_keys(Edition::all()) as $edition) {
            $this->onEdition($edition);
            $this->actingAs($this->admin)
                ->get(route('settings.licence.edit'))
                ->assertOk()
                ->assertSee(Edition::label($edition).' edition');
        }
    }
}
