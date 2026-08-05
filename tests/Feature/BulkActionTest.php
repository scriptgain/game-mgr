<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Backup;
use App\Models\Game;
use App\Models\Mod;
use App\Models\Player;
use App\Models\Schedule;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Subuser;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A bulk endpoint is the classic way round a permission model: one route that
 * takes a list of ids and a verb, written in a hurry, checking neither. These
 * tests exist to make sure it never becomes the cheaper way in.
 */
class BulkActionTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private Server $otherServer;

    private User $owner;

    private User $subuser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret1234', 'role' => 'admin']);
        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $this->subuser = User::create(['name' => 'Sub', 'email' => 'sub@test.local', 'password' => 'secret1234', 'role' => 'client']);

        $location = Location::create(['short' => 'test', 'name' => 'Test']);
        $node = Node::create([
            'name' => 'test-node', 'location_id' => $location->id, 'fqdn' => '127.0.0.1',
            'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'runtimes' => ['docker'],
        ]);
        $game = Game::create(['name' => 'Test Game']);
        $template = Template::create(['game_id' => $game->id, 'name' => 'Test Template', 'runtime' => 'docker']);

        $make = fn (string $name) => Server::create([
            'name' => $name, 'owner_id' => $this->owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);

        $this->server = $make('Test Server');
        $this->otherServer = $make('Somebody Elses Server');

        Subuser::create([
            'server_id' => $this->server->id,
            'user_id' => $this->subuser->id,
            // Deliberately holds backup.read but NOT backup.delete.
            'permissions' => ['control.console', 'backup.read'],
        ]);

        Allocation::create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => 25565, 'server_id' => $this->server->id]);
    }

    private function backupFor(Server $server, array $attributes = []): Backup
    {
        return Backup::create(array_merge([
            'server_id' => $server->id,
            'name' => 'Backup for '.$server->name,
            'disk' => 'local',
            'is_successful' => true,
            'completed_at' => now(),
        ], $attributes));
    }

    // ------------------------------------------------------------------ admin

    public function test_a_client_cannot_reach_the_admin_bulk_endpoint(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.bulk', 'servers'), ['action' => 'delete', 'ids' => [$this->server->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('servers', ['id' => $this->server->id]);
    }

    public function test_an_unregistered_resource_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.bulk', 'settings'), ['action' => 'delete', 'ids' => [1]])
            ->assertForbidden();
    }

    public function test_an_action_the_resource_does_not_allow_is_refused(): void
    {
        // Games only permit delete. Suspend is a valid action elsewhere, which
        // is exactly why the check has to be per resource and not global.
        $this->actingAs($this->admin)
            ->post(route('admin.bulk', 'games'), ['action' => 'suspend', 'ids' => [1]])
            ->assertForbidden();
    }

    public function test_an_admin_can_bulk_suspend(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.bulk', 'servers'), [
                'action' => 'suspend',
                'ids' => [$this->server->id, $this->otherServer->id],
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $this->server->fresh()->status);
        $this->assertSame('suspended', $this->otherServer->fresh()->status);
    }

    /** The root admin must survive a bulk delete that includes it. */
    public function test_bulk_delete_skips_the_root_admin_rather_than_failing(): void
    {
        $root = User::create(['name' => 'Root', 'email' => 'root@test.local', 'password' => 'secret1234', 'role' => 'admin']);
        $root->forceFill(['root_admin' => true])->save();

        $ordinary = User::create(['name' => 'Ordinary', 'email' => 'ordinary@test.local', 'password' => 'secret1234', 'role' => 'client']);

        $this->actingAs($this->admin)
            ->post(route('admin.bulk', 'users'), ['action' => 'delete', 'ids' => [$root->id, $ordinary->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $root->id]);
        $this->assertDatabaseMissing('users', ['id' => $ordinary->id]);
    }

    // ----------------------------------------------------------------- server

    public function test_a_subuser_without_the_permission_cannot_bulk_delete(): void
    {
        $backup = $this->backupFor($this->server);

        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'backups']), ['action' => 'delete', 'ids' => [$backup->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('backups', ['id' => $backup->id]);
    }

    public function test_the_owner_can_bulk_delete_their_own_backups(): void
    {
        $backup = $this->backupFor($this->server);

        $this->actingAs($this->owner)
            ->post(route('server.bulk', [$this->server, 'backups']), ['action' => 'delete', 'ids' => [$backup->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
    }

    /**
     * The one that matters most: passing an id from another server must not act
     * on it, even when the caller is legitimately allowed to act on this one.
     */
    public function test_ids_belonging_to_another_server_are_ignored(): void
    {
        $mine = $this->backupFor($this->server);
        $theirs = $this->backupFor($this->otherServer);

        $this->actingAs($this->owner)
            ->post(route('server.bulk', [$this->server, 'backups']), [
                'action' => 'delete',
                'ids' => [$mine->id, $theirs->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('backups', ['id' => $mine->id]);
        $this->assertDatabaseHas('backups', ['id' => $theirs->id]);
    }

    /** A locked backup survives a bulk delete, exactly as it does a single one. */
    public function test_locked_backups_are_skipped(): void
    {
        $locked = $this->backupFor($this->server, ['is_locked' => true]);
        $loose = $this->backupFor($this->server);

        $this->actingAs($this->owner)
            ->post(route('server.bulk', [$this->server, 'backups']), [
                'action' => 'delete',
                'ids' => [$locked->id, $loose->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('backups', ['id' => $locked->id]);
        $this->assertDatabaseMissing('backups', ['id' => $loose->id]);
    }

    public function test_the_primary_allocation_cannot_be_released_in_bulk(): void
    {
        $primary = $this->server->allocations()->first();
        $this->server->update(['allocation_id' => $primary->id]);

        $this->actingAs($this->owner)
            ->post(route('server.bulk', [$this->server, 'allocations']), [
                'action' => 'release',
                'ids' => [$primary->id],
            ])
            ->assertRedirect();

        $this->assertSame($this->server->id, $primary->fresh()->server_id);
    }

    public function test_a_stranger_cannot_bulk_act_on_a_server(): void
    {
        $stranger = User::create(['name' => 'Nobody', 'email' => 'nobody@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $backup = $this->backupFor($this->server);

        $this->actingAs($stranger)
            ->post(route('server.bulk', [$this->server, 'backups']), ['action' => 'delete', 'ids' => [$backup->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('backups', ['id' => $backup->id]);
    }

    // ------------------------------------------------- permission escalation

    /**
     * The bulk endpoint must never grant more than the button it replaces.
     *
     * These three all passed before the registry mapped a permission per action
     * rather than per resource: a subuser given the weaker right could reach the
     * stronger one simply by going through the bulk route.
     */
    public function test_mod_update_cannot_bulk_delete_mods(): void
    {
        $this->grant(['mod.read', 'mod.update']);
        $mod = Mod::create([
            'server_id' => $this->server->id, 'source' => 'manual', 'name' => 'EssentialsX',
            'slug' => 'essentialsx', 'version' => '1.0.0', 'enabled' => true,
        ]);

        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'mods']), ['action' => 'delete', 'ids' => [$mod->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('mods', ['id' => $mod->id]);

        // The weaker action it genuinely holds still works, so the fix is a
        // narrowing rather than a blanket refusal.
        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'mods']), ['action' => 'disable', 'ids' => [$mod->id]])
            ->assertRedirect();

        $this->assertFalse($mod->fresh()->enabled);
    }

    public function test_schedule_update_cannot_bulk_delete_schedules(): void
    {
        $this->grant(['schedule.read', 'schedule.update']);
        $schedule = Schedule::create(['server_id' => $this->server->id, 'name' => 'Nightly Restart']);

        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'schedules']), ['action' => 'delete', 'ids' => [$schedule->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }

    public function test_player_ban_cannot_bulk_whitelist_or_op(): void
    {
        $this->grant(['player.read', 'player.ban']);
        $player = Player::create([
            'server_id' => $this->server->id, 'identifier' => 'abc', 'name' => 'Roundabout',
            'is_whitelisted' => false,
        ]);

        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'players']), ['action' => 'whitelist', 'ids' => [$player->id]])
            ->assertForbidden();

        $this->assertFalse($player->fresh()->is_whitelisted);

        // Banning is what they were actually given.
        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'players']), ['action' => 'ban', 'ids' => [$player->id]])
            ->assertRedirect();

        $this->assertTrue($player->fresh()->is_banned);
    }

    public function test_backup_create_covers_locking_but_not_deleting(): void
    {
        $this->grant(['backup.read', 'backup.create']);
        $backup = $this->backupFor($this->server);

        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'backups']), ['action' => 'lock', 'ids' => [$backup->id]])
            ->assertRedirect();

        $this->assertTrue($backup->fresh()->is_locked);

        $this->actingAs($this->subuser)
            ->post(route('server.bulk', [$this->server, 'backups']), ['action' => 'delete', 'ids' => [$backup->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('backups', ['id' => $backup->id]);
    }

    /** Replaces the subuser's permissions with exactly this set. */
    private function grant(array $permissions): void
    {
        Subuser::where('server_id', $this->server->id)
            ->where('user_id', $this->subuser->id)
            ->update(['permissions' => json_encode($permissions)]);
    }
}
