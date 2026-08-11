<?php

namespace Tests\Feature;

use App\Jobs\InstallServer;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\SteamAccount;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\User;
use App\Support\SteamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A bound Steam account reaches the daemon, and nothing else does.
 *
 * The credentials are assembled in Server::environment() at dispatch rather
 * than stored per server, which is the whole design: the shared secret stays in
 * the panel and the node only ever sees a code with thirty seconds left on it.
 */
class SteamAccountTest extends TestCase
{
    use RefreshDatabase;

    /** 20 bytes, Base64, the shape a real mobile authenticator export has. */
    private const SECRET = 'AAECAwQFBgcICQoLDA0ODxAREhM=';

    private Server $server;

    private SteamAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
        $game = Game::create(['name' => 'Deadlock', 'slug' => 'deadlock-'.Str::random(5)]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Deadlock Dedicated', 'runtime' => 'docker',
            'startup' => 'run', 'steam_app_id' => 1422450, 'steam_anonymous' => false,
            'requires_steam_account' => true,
        ]);

        $this->account = SteamAccount::create([
            'label' => 'Deadlock licence',
            'username' => 'gamemgr_bot',
            'password' => 'hunter2-should-never-be-visible',
            'shared_secret' => self::SECRET,
        ]);

        $this->server = Server::create([
            'name' => 'Streets', 'owner_id' => $admin->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'steam_account_id' => $this->account->id,
            'runtime' => 'docker', 'startup' => 'run',
            'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);
    }

    public function test_a_bound_account_reaches_the_daemon_payload(): void
    {
        $env = $this->server->daemonPayload()['environment'];

        $this->assertSame('gamemgr_bot', $env['STEAM_USER']);
        $this->assertSame('hunter2-should-never-be-visible', $env['STEAM_PASS']);
        $this->assertSame(SteamGuard::code(self::SECRET), $env['STEAM_GUARD_CODE']);
    }

    /** A server with no account bound sends none of the three, not empty ones. */
    public function test_an_unbound_server_sends_no_steam_keys(): void
    {
        $this->server->update(['steam_account_id' => null]);

        $env = $this->server->fresh()->environment();

        foreach (Server::REDACTED_ENV as $key) {
            $this->assertArrayNotHasKey($key, $env);
        }
    }

    /**
     * A template variable must not be able to shadow the real credential.
     *
     * These names are reserved. Without the ordering in environment() a
     * template that declared its own STEAM_PASS, or a client who edited one on
     * the Startup tab, would silently replace the account password with
     * whatever they typed, and the install would fail as a login error nobody
     * could explain.
     */
    public function test_a_template_variable_cannot_shadow_the_credentials(): void
    {
        $variable = TemplateVariable::create([
            'template_id' => $this->server->template_id,
            'name' => 'Steam Password', 'env_variable' => 'STEAM_PASS',
            'default_value' => 'wrong-from-the-template',
            'user_viewable' => true, 'user_editable' => true, 'rules' => 'nullable|string',
        ]);
        $this->server->variables()->create(['template_variable_id' => $variable->id, 'value' => 'wrong-from-the-client']);

        $env = $this->server->fresh()->environment();

        $this->assertSame('hunter2-should-never-be-visible', $env['STEAM_PASS']);
    }

    /** Credentials are encrypted at rest, so a database dump does not hand them over. */
    public function test_the_secrets_are_encrypted_in_the_database(): void
    {
        $row = DB::table('steam_accounts')->where('id', $this->account->id)->first();

        $this->assertNotSame('hunter2-should-never-be-visible', $row->password);
        $this->assertNotSame(self::SECRET, $row->shared_secret);
        $this->assertStringNotContainsString('hunter2', $row->password);
    }

    /** Neither secret survives serialisation, whatever holds the model. */
    public function test_the_secrets_are_never_serialised(): void
    {
        $json = $this->account->toJson();

        $this->assertStringNotContainsString('hunter2', $json);
        $this->assertStringNotContainsString(self::SECRET, $json);
    }

    /** Anything showing the environment to a person masks all three. */
    public function test_the_display_environment_masks_the_credentials(): void
    {
        $shown = $this->server->displayEnvironment();

        foreach (Server::REDACTED_ENV as $key) {
            $this->assertArrayHasKey($key, $shown);
            $this->assertSame('••••••••', $shown[$key]);
        }
        $this->assertStringNotContainsString('hunter2', json_encode($shown));
    }

    /** An account with Steam Guard turned off sends no code, rather than a wrong one. */
    public function test_an_account_without_a_shared_secret_sends_no_code(): void
    {
        $this->account->update(['shared_secret' => null]);

        $env = $this->server->fresh()->environment();

        $this->assertSame('gamemgr_bot', $env['STEAM_USER']);
        $this->assertSame('', $env['STEAM_GUARD_CODE']);
    }

    /** Deleting a credential must never take somebody's game server with it. */
    public function test_deleting_an_account_leaves_the_server_alone(): void
    {
        $this->account->delete();

        $fresh = $this->server->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->steam_account_id);
    }

    /** The admin screens render, which a route sweep on its own cannot tell you. */
    public function test_the_admin_screens_render(): void
    {
        $admin = User::where('email', 'admin@test.local')->first();

        $this->actingAs($admin)->get(route('admin.steam-accounts.index'))
            ->assertOk()->assertSee('Deadlock licence');
        $this->actingAs($admin)->get(route('admin.steam-accounts.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.steam-accounts.edit', $this->account))
            ->assertOk()
            // The form must never render either secret back into the page, not
            // even as a value attribute on a password input.
            ->assertDontSee('hunter2')
            ->assertDontSee(self::SECRET);
    }

    /** A client has no business anywhere near these. */
    public function test_a_client_cannot_reach_the_steam_accounts(): void
    {
        $client = User::create([
            'name' => 'Client', 'email' => 'client@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);

        $this->actingAs($client)->get(route('admin.steam-accounts.index'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.steam-accounts.edit', $this->account))->assertForbidden();
        $this->actingAs($client)->delete(route('admin.steam-accounts.destroy', $this->account))->assertForbidden();
    }

    /** Saving with the secret fields blank keeps what is stored. */
    public function test_a_blank_secret_field_keeps_the_stored_one(): void
    {
        $admin = User::where('email', 'admin@test.local')->first();

        $this->actingAs($admin)->put(route('admin.steam-accounts.update', $this->account), [
            'label' => 'Renamed', 'username' => 'gamemgr_bot',
            'password' => '', 'shared_secret' => '',
        ])->assertRedirect();

        $fresh = $this->account->fresh();
        $this->assertSame('Renamed', $fresh->label);
        $this->assertSame('hunter2-should-never-be-visible', $fresh->password);
        $this->assertSame(self::SECRET, $fresh->shared_secret);
    }

    /**
     * A secret in the wrong encoding is rejected at the form.
     *
     * Base32 is what an authenticator app shows and it is the obvious wrong
     * thing to paste. Accepted, it would produce five characters of the right
     * shape that are always wrong, and Steam answers a run of wrong codes with
     * a lockout indistinguishable from a bad password.
     */
    public function test_a_base32_secret_is_refused(): void
    {
        $admin = User::where('email', 'admin@test.local')->first();

        $this->actingAs($admin)->put(route('admin.steam-accounts.update', $this->account), [
            'label' => 'Deadlock licence', 'username' => 'gamemgr_bot',
            'shared_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        ])->assertSessionHasErrors('shared_secret');

        $this->assertSame(self::SECRET, $this->account->fresh()->shared_secret);
    }

    /** Changing the login invalidates every sentry, because Steam will challenge again. */
    public function test_changing_the_password_clears_the_authorized_nodes(): void
    {
        $admin = User::where('email', 'admin@test.local')->first();
        $this->account->markAuthorized($this->server->node);

        $this->actingAs($admin)->put(route('admin.steam-accounts.update', $this->account), [
            'label' => 'Deadlock licence', 'username' => 'gamemgr_bot',
            'password' => 'a-new-password',
        ])->assertRedirect();

        $this->assertSame([], $this->account->fresh()->authorized_nodes);
    }

    /**
     * A completed install records the node as authorized.
     *
     * This is here because the marking was written, unit tested against the
     * model, and then never called from anywhere: authorized_nodes could only
     * ever stay empty, and the "already authorized" notice on the account form
     * could never appear. A test on the model alone did not catch that, because
     * the model was fine. Only running the job does.
     */
    public function test_a_successful_install_marks_the_node_authorized(): void
    {
        Http::fake(['*' => Http::response("event: message\ndata: [gamemgr] install complete\n\n", 200)]);

        (new InstallServer($this->server->id))->handle();

        $this->assertSame([$this->server->node_id], $this->account->fresh()->authorized_nodes);
        $this->assertNotNull($this->server->fresh()->installed_at);
    }

    /** A failed install proves nothing about the sentry, so it must record nothing. */
    public function test_a_failed_install_does_not_mark_the_node_authorized(): void
    {
        Http::fake(['*' => Http::response("event: error\ndata: Two-factor code mismatch\n\n", 200)]);

        (new InstallServer($this->server->id))->handle();

        $this->assertEmpty($this->account->fresh()->authorized_nodes ?? []);
        $this->assertSame('install_failed', $this->server->fresh()->status);
    }

    public function test_authorized_nodes_records_where_the_sentry_took(): void
    {
        $node = $this->server->node;

        $this->assertFalse($this->account->authorizedOn($node));

        $this->account->markAuthorized($node);
        $this->account->markAuthorized($node);

        $this->assertTrue($this->account->fresh()->authorizedOn($node));
        $this->assertSame([$node->id], $this->account->fresh()->authorized_nodes);
    }
}
