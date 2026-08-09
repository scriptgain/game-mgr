<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Subuser;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The customer's own API.
 *
 * The property worth pinning is that this is not a second opinion about who may
 * do what. Every answer comes from ServerPolicy, the same class the web screens
 * and SFTP ask, so a subuser who cannot restart in the panel cannot restart
 * through a token either.
 */
class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Server $server;

    private Node $node;

    private Template $template;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn () => Http::response(['ok' => true, 'state' => 'running'], 200));
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $this->node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
        $this->node->forceFill(['daemon_secret' => 'secret'])->save();

        $game = Game::create(['name' => 'Minecraft', 'slug' => 'minecraft']);
        $this->template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker', 'startup' => 'run',
        ]);

        $this->owner = $this->user('owner@test.local');
        $this->server = $this->serverFor($this->owner);
    }

    private function user(string $email): User
    {
        return User::create([
            'name' => 'Somebody', 'email' => $email,
            'password' => 'secret1234', 'role' => 'client',
        ]);
    }

    private function serverFor(User $owner, array $overrides = []): Server
    {
        return Server::create(array_merge([
            'name' => 'Survival', 'owner_id' => $owner->id, 'node_id' => $this->node->id,
            'template_id' => $this->template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 1024, 'disk' => 4096, 'cpu' => 100, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
            'power_state' => 'running',
        ], $overrides));
    }

    private function tokenFor(User $user): string
    {
        $plain = 'gm_'.Str::random(48);
        ApiToken::create([
            'user_id' => $user->id, 'name' => 'Theirs',
            'token' => hash('sha256', $plain), 'scope' => 'client',
        ]);

        return $plain;
    }

    private function as(User $user, string $method, string $uri, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->json($method, $uri, $body);
    }

    public function test_a_customer_sees_only_their_own_servers(): void
    {
        $stranger = $this->user('stranger@test.local');
        $this->serverFor($stranger, ['name' => 'Not Theirs']);

        $response = $this->as($this->owner, 'GET', '/api/client/servers')->assertOk();

        $names = collect($response->json('data'))->pluck('attributes.name');
        $this->assertContains('Survival', $names);
        $this->assertNotContains('Not Theirs', $names, 'a customer must not see somebody else\'s server');
    }

    /** The client view is deliberately narrower than the admin one. */
    public function test_the_client_view_does_not_leak_where_the_server_lives(): void
    {
        $attributes = $this->as($this->owner, 'GET', '/api/client/servers/'.$this->server->uuid_short)
            ->assertOk()
            ->json('attributes');

        $this->assertArrayHasKey('identifier', $attributes);
        $this->assertArrayNotHasKey('node_id', $attributes, 'a customer has no business knowing which node they are on');
        $this->assertArrayNotHasKey('owner_id', $attributes);
    }

    public function test_an_owner_can_send_a_power_signal(): void
    {
        $this->as($this->owner, 'POST', '/api/client/servers/'.$this->server->uuid_short.'/power', [
            'signal' => 'restart',
        ])->assertNoContent();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/power'));
    }

    public function test_a_stranger_reaches_nothing(): void
    {
        $stranger = $this->user('stranger@test.local');

        $this->as($stranger, 'GET', '/api/client/servers/'.$this->server->uuid_short)->assertForbidden();
        $this->as($stranger, 'POST', '/api/client/servers/'.$this->server->uuid_short.'/power', [
            'signal' => 'stop',
        ])->assertForbidden();
    }

    /**
     * The point of the whole thing: the API asks the same policy the panel does.
     * This subuser may start but not stop, exactly as in the web screens.
     */
    public function test_a_subuser_gets_exactly_the_permissions_they_hold(): void
    {
        $friend = $this->user('friend@test.local');
        Subuser::create([
            'server_id' => $this->server->id,
            'user_id' => $friend->id,
            'permissions' => ['settings.read', 'control.start'],
        ]);

        $this->as($friend, 'GET', '/api/client/servers/'.$this->server->uuid_short)->assertOk();
        $this->as($friend, 'POST', '/api/client/servers/'.$this->server->uuid_short.'/power', [
            'signal' => 'start',
        ])->assertNoContent();

        // Not granted, so refused, exactly as in the panel.
        $this->as($friend, 'POST', '/api/client/servers/'.$this->server->uuid_short.'/power', [
            'signal' => 'stop',
        ])->assertForbidden();
    }

    public function test_a_suspended_server_refuses_power_signals(): void
    {
        $this->server->forceFill(['status' => 'suspended'])->save();

        $this->as($this->owner, 'POST', '/api/client/servers/'.$this->server->uuid_short.'/power', [
            'signal' => 'start',
        ])->assertForbidden();
    }

    public function test_an_unknown_signal_is_refused(): void
    {
        $this->as($this->owner, 'POST', '/api/client/servers/'.$this->server->uuid_short.'/power', [
            'signal' => 'obliterate',
        ])->assertStatus(422);
    }

    public function test_a_client_token_cannot_reach_the_application_scope(): void
    {
        $this->as($this->owner, 'GET', '/api/application/servers')->assertForbidden();
    }
}
