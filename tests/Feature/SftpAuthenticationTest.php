<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The node daemon holds no accounts, so this endpoint is the whole of SFTP
 * authentication. If it is wrong, the jail on the node does not matter: it would
 * be jailing the wrong person into somebody else's directory.
 *
 * The property being pinned throughout is that SFTP is not a second opinion.
 * Every answer here comes from ServerPolicy, the same class the web file manager
 * asks, so revoking somebody in the panel revokes them here in the same moment.
 */
class SftpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Node $node;

    private string $nodeToken;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $this->node = Node::create([
            'name' => 'gamemgr001',
            'location_id' => $location->id,
            'scheme' => 'http',
            'fqdn' => '127.0.0.1',
            'daemon_port' => 8942,
        ]);

        $this->nodeToken = Str::random(64);
        $this->node->forceFill(['daemon_token' => hash('sha256', $this->nodeToken)])->save();
    }

    private function user(string $email, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'A Person',
            'email' => $email,
            'password' => 'correct-horse',
            'role' => 'client',
        ], $overrides));
    }

    private function server(User $owner, array $overrides = []): Server
    {
        $game = Game::create(['name' => 'Minecraft', 'slug' => 'minecraft-'.Str::random(6)]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper '.Str::random(4), 'runtime' => 'docker', 'startup' => 'java -jar server.jar',
        ]);

        return Server::create(array_merge([
            'name' => 'Survival',
            'owner_id' => $owner->id,
            'node_id' => $this->node->id,
            'template_id' => $template->id,
            'runtime' => 'docker',
            'startup' => 'java -jar server.jar',
            'memory' => 2048, 'disk' => 10240, 'cpu' => 200, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ], $overrides));
    }

    private function attempt(string $username, string $password = 'correct-horse')
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->nodeToken)
            ->postJson('/api/node/sftp/authenticate', [
                'username' => $username,
                'password' => $password,
                'ip' => '72.217.7.118',
            ]);
    }

    public function test_an_owner_gets_their_own_server_and_every_file_permission(): void
    {
        $owner = $this->user('allen@example.test');
        $server = $this->server($owner);

        $response = $this->attempt($owner->sftpUsername($server))->assertOk();

        $response->assertJson([
            'granted' => true,
            'server_uuid' => $server->uuid,
            'runtime' => 'docker',
        ]);
        $this->assertEqualsCanonicalizing(
            ['file.read', 'file.create', 'file.update', 'file.delete'],
            $response->json('permissions')
        );
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $owner = $this->user('allen@example.test');
        $server = $this->server($owner);

        $this->attempt($owner->sftpUsername($server), 'not the password')
            ->assertOk()
            ->assertJson(['granted' => false]);
    }

    /**
     * A refusal must not be a 401. This endpoint authenticates the node with a
     * bearer token, so 401 already means "this node's own credential is wrong".
     * Overloading it would make a node with a stale token report every
     * customer's login as a bad password, and nobody would look at the node.
     */
    public function test_a_refusal_is_a_200_so_it_cannot_be_confused_with_a_bad_node_token(): void
    {
        $this->attempt('nobody.deadbeefdead', 'whatever')
            ->assertOk()
            ->assertJson(['granted' => false]);

        // Whereas a bad node token really is a 401.
        $this->withHeader('Authorization', 'Bearer not-a-real-node-token')
            ->postJson('/api/node/sftp/authenticate', [
                'username' => 'someone.deadbeefdead',
                'password' => 'correct-horse',
            ])
            ->assertUnauthorized();
    }

    public function test_a_server_on_another_node_is_refused(): void
    {
        $owner = $this->user('allen@example.test');

        $other = Node::create([
            'name' => 'gamemgr002',
            'location_id' => $this->node->location_id,
            'scheme' => 'http', 'fqdn' => '127.0.0.2', 'daemon_port' => 8942,
        ]);
        $server = $this->server($owner, ['node_id' => $other->id]);

        // The right password for the right server, presented to the wrong node.
        // A node may only ever be told about files it actually holds.
        $this->attempt($owner->sftpUsername($server))
            ->assertOk()
            ->assertJson(['granted' => false]);
    }

    /**
     * Suspension is a status, and it is ServerPolicy that enforces it: every
     * non-read permission is refused on a suspended server, and connecting is
     * file.sftp. So this passes without a suspension rule of its own in the
     * endpoint, which is the point.
     */
    public function test_a_suspended_server_refuses_file_access_entirely(): void
    {
        $owner = $this->user('allen@example.test');
        $server = $this->server($owner);
        $server->forceFill(['status' => 'suspended'])->save();

        $this->assertTrue($server->fresh()->isSuspended());

        $this->attempt($owner->sftpUsername($server))
            ->assertOk()
            ->assertJson(['granted' => false]);
    }

    /**
     * The other half of that: an admin keeps file access to a suspended server,
     * because support is usually called precisely about the server somebody has
     * just had suspended.
     */
    public function test_an_admin_still_reaches_a_suspended_server(): void
    {
        $admin = $this->user('admin@example.test', ['role' => 'admin']);
        $server = $this->server($admin);
        $server->forceFill(['status' => 'suspended'])->save();

        $this->attempt($admin->sftpUsername($server))
            ->assertOk()
            ->assertJson(['granted' => true]);
    }

    public function test_a_suspended_account_is_refused(): void
    {
        $owner = $this->user('allen@example.test', ['suspended' => true]);
        $server = $this->server($owner);

        $this->attempt($owner->sftpUsername($server))
            ->assertOk()
            ->assertJson(['granted' => false]);
    }

    /**
     * file.sftp is the permission to connect at all, and it is deliberately not
     * in a subuser's default set: being handed the file manager should not
     * silently hand out a credential that works against the node from anywhere.
     */
    public function test_a_subuser_without_the_sftp_permission_cannot_connect(): void
    {
        $owner = $this->user('allen@example.test');
        $server = $this->server($owner);

        $friend = $this->user('friend@example.test');
        Subuser::create([
            'server_id' => $server->id,
            'user_id' => $friend->id,
            'permissions' => Subuser::defaultPermissions(),
        ]);

        $this->assertNotContains('file.sftp', Subuser::defaultPermissions(),
            'file.sftp must not be granted by default');

        $this->attempt($friend->sftpUsername($server))
            ->assertOk()
            ->assertJson(['granted' => false]);
    }

    public function test_a_subuser_gets_exactly_the_file_permissions_they_hold(): void
    {
        $owner = $this->user('allen@example.test');
        $server = $this->server($owner);

        $friend = $this->user('friend@example.test');
        Subuser::create([
            'server_id' => $server->id,
            'user_id' => $friend->id,
            // May connect, may look, may upload. May not change or delete.
            'permissions' => ['file.sftp', 'file.read', 'file.create'],
        ]);

        $response = $this->attempt($friend->sftpUsername($server))->assertOk();

        $response->assertJson(['granted' => true, 'server_uuid' => $server->uuid]);
        $this->assertEqualsCanonicalizing(
            ['file.read', 'file.create'],
            $response->json('permissions'),
            'a subuser must not gain permissions over SFTP that they do not have in the panel'
        );
    }

    /**
     * A node's fqdn is how the panel reaches the daemon. On a single-box
     * install, which is what the installer produces by default, that is
     * 127.0.0.1, and printing it in the client area tells a customer to connect
     * to their own machine.
     */
    public function test_a_loopback_node_shows_the_panel_hostname_not_127_0_0_1(): void
    {
        config(['app.url' => 'https://gamemgr001.scriptgain.com']);

        $owner = $this->user('allen@example.test');
        $server = $this->server($owner);

        $this->assertSame('127.0.0.1', $server->node->fqdn);
        $this->assertSame('gamemgr001.scriptgain.com:2022', $server->sftpHost());
    }

    public function test_a_real_node_hostname_is_used_as_is(): void
    {
        $owner = $this->user('allen@example.test');
        $this->node->forceFill(['fqdn' => 'lax1.example.com', 'sftp_port' => 2222])->save();
        $server = $this->server($owner);

        $this->assertSame('lax1.example.com:2222', $server->fresh()->sftpHost());
    }

    /**
     * Usernames may contain dots and the server identifier never does, so the
     * split has to be on the last one. Splitting on the first would send
     * "first.last" to a server called "last".
     */
    public function test_a_username_containing_a_dot_still_resolves(): void
    {
        $owner = $this->user('first.last@example.test');
        $server = $this->server($owner);

        $this->assertSame('first.last', $owner->username);

        $this->attempt($owner->sftpUsername($server))
            ->assertOk()
            ->assertJson(['granted' => true, 'server_uuid' => $server->uuid]);
    }

    /**
     * The username has to identify exactly one account. It used to be built by
     * slugging the display name, so two people called Alex Smith produced the
     * same login and the daemon had no way to tell them apart.
     */
    public function test_two_accounts_with_the_same_display_name_get_different_usernames(): void
    {
        $first = $this->user('alex@example.test', ['name' => 'Alex Smith']);
        $second = $this->user('alex@other.test', ['name' => 'Alex Smith']);

        $this->assertNotSame($first->username, $second->username);

        // And the second one's login reaches the second one's server.
        $server = $this->server($second);
        $this->attempt($second->sftpUsername($server))
            ->assertOk()
            ->assertJson(['granted' => true, 'server_uuid' => $server->uuid]);
    }
}
