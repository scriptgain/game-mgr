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

    /**
     * The three permissions that existed in the matrix with nothing anywhere
     * implementing them. An administrator could tick these boxes and grant
     * nothing at all; now each has an endpoint that demands it.
     */
    public function test_the_formerly_dead_permissions_are_now_honoured(): void
    {
        $friend = $this->user('archivist@test.local');
        Subuser::create([
            'server_id' => $this->server->id,
            'user_id' => $friend->id,
            'permissions' => ['file.read'],
        ]);

        // Holding file.read but not file.archive: refused.
        $this->as($friend, 'POST', $this->url('files/archive'), ['paths' => ['world']])
            ->assertForbidden();
        $this->as($friend, 'POST', $this->url('files/extract'), ['path' => 'x.tar.gz'])
            ->assertForbidden();
        $this->as($friend, 'POST', $this->url('worlds/upload').'?name=w')
            ->assertForbidden();

        // The owner holds everything, so the endpoints exist and are reachable.
        $this->assertNotSame(403, $this->as($this->owner, 'POST', $this->url('files/archive'), ['paths' => ['world']])->status());
        $this->assertNotSame(403, $this->as($this->owner, 'POST', $this->url('files/extract'), ['path' => 'x.tar.gz'])->status());
    }

    /** Every permission the matrix declares is demanded by something. */
    /**
     * No permission may exist that nothing honours.
     *
     * Scans for the string anywhere in a controller rather than only for a
     * literal guard() call, because several are reached through a match() on
     * the requested action and one is checked by the node API rather than by a
     * client screen. The point is whether granting it does anything, not how
     * the check is spelled.
     */
    public function test_no_permission_in_the_matrix_is_dead(): void
    {
        $haystack = '';
        foreach ([
            'Http/Controllers/Api/Client',
            'Http/Controllers/Client',
            'Http/Controllers/Api',
            'Models',
        ] as $dir) {
            foreach (glob(app_path($dir.'/*.php')) as $file) {
                $haystack .= file_get_contents($file);
            }
        }

        $dead = [];
        foreach (Subuser::allPermissions() as $permission) {
            // Subuser::MATRIX itself declares them, so a permission only counts
            // as honoured if it appears somewhere that is not the declaration.
            if (substr_count($haystack, "'".$permission."'") < 2) {
                $dead[] = $permission;
            }
        }

        $this->assertSame([], $dead,
            'these permissions can be granted and do nothing: '.implode(', ', $dead));
    }
}
