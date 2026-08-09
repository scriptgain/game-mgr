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
 * The client scope, across every feature.
 *
 * Two properties matter more than any individual endpoint, and both are checked
 * against the whole surface rather than one route: a stranger reaches nothing,
 * and a subuser gets exactly what they hold and nothing adjacent. Those are the
 * failures that turn into somebody else's server.
 */
class ClientScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Server $server;

    /** Every read endpoint, with the permission it should demand. */
    private const READS = [
        ['files', 'file.read'],
        ['backups', 'backup.read'],
        ['subusers', 'user.read'],
        ['network', 'allocation.read'],
        ['startup', 'startup.read'],
        ['schedules', 'schedule.read'],
        ['databases', 'database.read'],
        ['activity', 'activity.read'],
        ['mods', 'mod.read'],
        ['worlds', 'world.read'],
        ['players', 'player.read'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn () => Http::response(['ok' => true, 'entries' => []], 200));
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $location = Location::create(['short' => 'lax', 'name' => 'LA']);
        $node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
        $node->forceFill(['daemon_secret' => 's'])->save();

        $game = Game::create(['name' => 'MC', 'slug' => 'mc']);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker', 'startup' => 'run',
        ]);

        $this->owner = $this->user('owner@test.local');
        $this->server = Server::create([
            'name' => 'S', 'owner_id' => $this->owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 512, 'disk' => 1024, 'cpu' => 50, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);
    }

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U', 'email' => $email, 'password' => 'secret1234', 'role' => 'client',
        ]);
    }

    private function as(User $user, string $method, string $uri, array $body = [])
    {
        $plain = 'gm_'.Str::random(48);
        ApiToken::create([
            'user_id' => $user->id, 'name' => 'T',
            'token' => hash('sha256', $plain), 'scope' => 'client',
        ]);

        return $this->withHeader('Authorization', 'Bearer '.$plain)->json($method, $uri, $body);
    }

    private function url(string $suffix): string
    {
        return '/api/client/servers/'.$this->server->uuid_short.'/'.$suffix;
    }

    /** The owner reaches all of it. */
    public function test_the_owner_reaches_every_feature(): void
    {
        foreach (self::READS as [$path, $permission]) {
            $response = $this->as($this->owner, 'GET', $this->url($path));
            $this->assertSame(200, $response->status(), $path.' refused the owner: '.$response->getContent());
        }
    }

    /** A stranger reaches none of it. */
    public function test_a_stranger_reaches_nothing(): void
    {
        $stranger = $this->user('stranger@test.local');

        foreach (self::READS as [$path, $permission]) {
            $this->as($stranger, 'GET', $this->url($path))
                ->assertForbidden();
        }
    }

    /**
     * The one that matters most: a subuser holding exactly one permission gets
     * that endpoint and no other. Anything else means the API is a second
     * opinion about access, and the one that disagrees is a breach.
     */
    public function test_a_subuser_gets_exactly_what_they_hold(): void
    {
        $friend = $this->user('friend@test.local');
        Subuser::create([
            'server_id' => $this->server->id,
            'user_id' => $friend->id,
            'permissions' => ['backup.read'],
        ]);

        $this->as($friend, 'GET', $this->url('backups'))->assertOk();

        foreach (self::READS as [$path, $permission]) {
            if ($permission === 'backup.read') {
                continue;
            }
            $this->as($friend, 'GET', $this->url($path))
                ->assertForbidden();
        }
    }

    /** A suspended server refuses anything that changes it. */
    public function test_a_suspended_server_refuses_writes(): void
    {
        $this->server->forceFill(['status' => 'suspended'])->save();

        $this->as($this->owner, 'POST', $this->url('backups'), ['name' => 'x'])->assertForbidden();
        $this->as($this->owner, 'POST', $this->url('files/mkdir'), ['path' => '/x'])->assertForbidden();
    }

    /** Every permission in the matrix is a real string, not a typo in a guard. */
    public function test_the_permissions_these_endpoints_demand_all_exist(): void
    {
        $known = Subuser::allPermissions();

        foreach (self::READS as [$path, $permission]) {
            $this->assertContains($permission, $known, $path.' guards on '.$permission.', which is not in the matrix');
        }
    }
}
