<?php

namespace Tests\Feature;

use App\Jobs\InstallServer;
use App\Models\Allocation;
use App\Models\ApiToken;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The provisioning lifecycle a billing system drives, end to end.
 *
 * These assert the things that were quietly untrue before: that suspending
 * actually stops the server, that terminating actually tells the node, and that
 * changing the package does something a container will honour.
 */
class ApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Node $node;

    private Template $template;

    /**
     * Http::fake merges stubs rather than replacing them, so a second fake in a
     * test never wins against the one set up here. This flag is how a test says
     * "the node is down" instead.
     */
    private bool $nodeIsDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake(fn () => $this->nodeIsDown
            ? Http::response('', 500)
            : Http::response(['ok' => true, 'state' => 'offline'], 200));
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['edition' => 'plus'],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);

        $plain = 'gm_'.Str::random(48);
        ApiToken::create([
            'user_id' => $admin->id, 'name' => 'WHMCS',
            'token' => hash('sha256', $plain), 'scope' => 'application',
        ]);
        $this->token = $plain;

        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $this->node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
            'memory' => 64000, 'disk' => 500000, 'cpu' => 800, 'public' => true,
        ]);
        $this->node->forceFill(['daemon_secret' => 'secret'])->save();

        $game = Game::create(['name' => 'Minecraft', 'slug' => 'minecraft']);
        $this->template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker',
            'startup' => 'run', 'default_port' => 25565, 'default_protocol' => 'tcp',
        ]);

        foreach (range(25565, 25570) as $port) {
            Allocation::create(['node_id' => $this->node->id, 'ip' => '10.0.0.1', 'port' => $port]);
        }
    }

    /** Named apiCall, not call: TestCase already has a call() and it is public. */
    private function apiCall(string $method, string $uri, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->json($method, $uri, $body);
    }

    private function server(array $overrides = []): Server
    {
        $owner = User::create([
            'name' => 'Customer', 'email' => 'c'.Str::random(5).'@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);

        return Server::create(array_merge([
            'name' => 'Survival', 'owner_id' => $owner->id, 'node_id' => $this->node->id,
            'template_id' => $this->template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 1024, 'disk' => 4096, 'cpu' => 100, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ], $overrides));
    }

    // -------------------------------------------------------------- the shape

    public function test_a_list_carries_the_pterodactyl_envelope(): void
    {
        $this->server();

        $this->apiCall('GET', '/api/application/servers')
            ->assertOk()
            ->assertJsonPath('object', 'list')
            ->assertJsonPath('data.0.object', 'server')
            ->assertJsonStructure([
                'object',
                'data' => [['object', 'attributes' => ['id', 'identifier', 'name', 'limits', 'feature_limits']]],
                'meta' => ['pagination' => ['total', 'count', 'per_page', 'current_page', 'total_pages']],
            ]);
    }

    public function test_one_object_carries_the_envelope_too(): void
    {
        $server = $this->server();

        $this->apiCall('GET', '/api/application/servers/'.$server->uuid_short)
            ->assertOk()
            ->assertJsonPath('object', 'server')
            ->assertJsonPath('attributes.name', 'Survival');
    }

    // ------------------------------------------------------- the lifecycle

    public function test_a_customer_account_can_be_created(): void
    {
        $this->apiCall('POST', '/api/application/users', [
            'name' => 'New Customer',
            'email' => 'new@customer.test',
            'password' => 'a-good-long-password',
        ])
            ->assertCreated()
            ->assertJsonPath('object', 'user')
            ->assertJsonPath('attributes.email', 'new@customer.test')
            ->assertJsonPath('attributes.role', 'client');

        // The username is derived rather than demanded, so a billing system
        // does not have to invent one.
        $this->assertNotNull(User::where('email', 'new@customer.test')->first()->username);
    }

    public function test_a_server_can_be_created_and_is_queued_for_install(): void
    {
        $owner = User::create([
            'name' => 'Customer', 'email' => 'buyer@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);

        $response = $this->apiCall('POST', '/api/application/servers', [
            'name' => 'Their Server',
            'owner_id' => $owner->id,
            'template_id' => $this->template->id,
            'node_id' => $this->node->id,
            'memory' => 2048, 'disk' => 8192, 'cpu' => 200,
        ])->assertCreated();

        $response->assertJsonPath('attributes.name', 'Their Server')
            ->assertJsonPath('attributes.limits.memory', 2048);

        $server = Server::where('name', 'Their Server')->first();
        $this->assertSame('installing', $server->status);
        $this->assertNotNull($server->allocation_id, 'a created server must hold the port it was given');
        Queue::assertPushed(InstallServer::class);
    }

    /** The one that was untrue: suspending has to stop the game. */
    public function test_suspending_stops_the_server_and_not_just_the_panel(): void
    {
        $server = $this->server(['power_state' => 'running']);

        $this->apiCall('POST', '/api/application/servers/'.$server->uuid_short.'/suspend')
            ->assertNoContent();

        $this->assertTrue($server->fresh()->isSuspended());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/power')
            && ($request->data()['action'] ?? null) === 'stop');
    }

    /** And unsuspending must NOT start it again. */
    public function test_unsuspending_does_not_start_the_server(): void
    {
        $server = $this->server(['status' => 'suspended', 'power_state' => 'offline']);

        $this->apiCall('POST', '/api/application/servers/'.$server->uuid_short.'/unsuspend')
            ->assertNoContent();

        $this->assertFalse($server->fresh()->isSuspended());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/power')
            && ($request->data()['action'] ?? null) === 'start');
    }

    public function test_changing_the_package_writes_the_limits(): void
    {
        $server = $this->server(['power_state' => 'offline']);

        $this->apiCall('PATCH', '/api/application/servers/'.$server->uuid_short.'/build', [
            'memory' => 4096, 'disk' => 20480, 'cpu' => 400,
        ])
            ->assertOk()
            ->assertJsonPath('attributes.limits.memory', 4096)
            ->assertJsonPath('meta.restarted', false);

        $this->assertSame(4096, $server->fresh()->memory);
    }

    /** A running server is restarted, because a container keeps the limits it was made with. */
    public function test_changing_the_package_on_a_running_server_restarts_it(): void
    {
        $server = $this->server(['power_state' => 'running']);

        $this->apiCall('PATCH', '/api/application/servers/'.$server->uuid_short.'/build', ['memory' => 4096])
            ->assertOk()
            ->assertJsonPath('meta.restarted', true);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/power') && ($r->data()['action'] ?? null) === 'start');
    }

    public function test_reinstall_can_ask_for_a_wipe(): void
    {
        $server = $this->server();

        $this->apiCall('POST', '/api/application/servers/'.$server->uuid_short.'/reinstall', ['wipe' => true])
            ->assertNoContent();

        Queue::assertPushed(InstallServer::class, fn (InstallServer $job) => $job->wipe === true);
    }

    /** Terminating has to tell the node, or the files stay there forever. */
    public function test_terminating_tells_the_node_and_frees_the_ports(): void
    {
        $server = $this->server();
        $allocation = Allocation::where('node_id', $this->node->id)->first();
        $allocation->update(['server_id' => $server->id, 'role' => 'game']);

        $this->apiCall('DELETE', '/api/application/servers/'.$server->uuid_short)
            ->assertNoContent()
            ->assertHeader('X-GameMGR-Node-Confirmed', 'true');

        $this->assertNull(Server::find($server->id));
        $this->assertNull($allocation->fresh()->server_id, 'the port must go back to the pool');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/api/servers/'.$server->uuid));
    }

    /** A node that is down must not make a customer impossible to terminate. */
    public function test_terminating_still_works_when_the_node_is_unreachable(): void
    {
        $this->nodeIsDown = true;
        $server = $this->server();

        $this->apiCall('DELETE', '/api/application/servers/'.$server->uuid_short)
            ->assertNoContent()
            ->assertHeader('X-GameMGR-Node-Confirmed', 'false');

        $this->assertNull(Server::find($server->id));
    }

    // --------------------------------------------------------------- accounts

    public function test_an_account_that_still_owns_servers_is_not_deleted(): void
    {
        $server = $this->server();

        $this->apiCall('DELETE', '/api/application/users/'.$server->owner_id)
            ->assertStatus(409)
            ->assertJsonPath('servers', 1);

        $this->assertNotNull(User::find($server->owner_id));
    }

    // -------------------------------------------------------------------- sso

    public function test_a_sign_in_link_works_once_and_only_once(): void
    {
        $server = $this->server();

        $url = $this->apiCall('POST', '/api/application/users/'.$server->owner_id.'/sso')
            ->assertOk()
            ->assertJsonPath('object', 'sso')
            ->json('attributes.url');

        $this->assertStringContainsString('/sso/', $url);

        // First use signs them in.
        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::find($server->owner_id));

        // Second use is refused: the link is a credential in a URL, and those
        // end up in history and referrer headers.
        $this->post(route('logout'));
        $this->get($url)->assertForbidden();
    }

    public function test_a_tampered_sign_in_link_is_refused(): void
    {
        $server = $this->server();
        $url = $this->apiCall('POST', '/api/application/users/'.$server->owner_id.'/sso')->json('attributes.url');

        $this->get($url.'&extra=1')->assertForbidden();
    }
}
