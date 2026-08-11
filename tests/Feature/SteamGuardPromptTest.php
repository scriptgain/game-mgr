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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Answering a Steam Guard prompt from the panel.
 *
 * Without this the only routes through a challenge are storing the account's
 * shared secret, which costs a fifteen day trade hold, or having a shell on the
 * node, which a client never does. The install blocks on the node while the
 * code arrives on a separate request, so the panel's job is to notice the
 * prompt, show it, and forward five characters.
 */
class SteamGuardPromptTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
        $location = Location::create(['short' => 'ewr', 'name' => 'New Jersey']);
        $node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
        $game = Game::create(['name' => 'Team Fortress 2', 'slug' => 'tf2-'.Str::random(5)]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'TF2 Dedicated', 'runtime' => 'steamcmd',
            'startup' => 'run', 'steam_app_id' => 232250, 'steam_anonymous' => false,
            'requires_steam_account' => true,
        ]);
        $account = SteamAccount::create([
            'label' => 'Test', 'username' => 'someone', 'password' => 'hunter2',
        ]);

        $this->server = Server::create([
            'name' => 'TF2', 'owner_id' => $this->admin->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'steam_account_id' => $account->id,
            'runtime' => 'steamcmd', 'startup' => 'run',
            'memory' => 2048, 'disk' => 20480, 'cpu' => 200, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
            'status' => 'installing',
        ]);
    }

    /** A guard event has to land in the row immediately, not on the next throttled write. */
    public function test_a_guard_event_marks_the_server_as_waiting(): void
    {
        Http::fake(['*' => Http::response(
            "event: guard\ndata: Steam is asking for a Steam Guard code for this account.\n\n",
            200
        )]);

        (new InstallServer($this->server->id))->handle();

        // The stream ended without a done event, so the install failed, but the
        // prompt still has to have been recorded on the way through.
        $this->assertStringContainsString('Steam Guard', $this->server->fresh()->install_log);
    }

    /** Once the install moves on, the prompt is gone and the box must not linger. */
    public function test_the_prompt_is_cleared_when_the_install_finishes(): void
    {
        $this->server->forceFill(['guard_prompt_at' => now()])->save();

        Http::fake(['*' => Http::response("event: message\ndata: [gamemgr] install complete\n\n", 200)]);

        (new InstallServer($this->server->id))->handle();

        $this->assertNull($this->server->fresh()->guard_prompt_at);
    }

    public function test_a_code_is_forwarded_to_the_node(): void
    {
        $this->server->forceFill(['guard_prompt_at' => now()])->save();
        Http::fake(['*' => Http::response(['accepted' => true], 200)]);

        $this->actingAs($this->admin)
            ->post(route('server.guard-code', $this->server), ['code' => 'K9J4M'])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/guard-code') && $request['code'] === 'K9J4M';
        });

        $this->assertNull($this->server->fresh()->guard_prompt_at);
    }

    /** Lowercase is a normal way to type it and must not be rejected or sent as typed. */
    public function test_a_lowercase_code_is_upcased(): void
    {
        $this->server->forceFill(['guard_prompt_at' => now()])->save();
        Http::fake(['*' => Http::response(['accepted' => true], 200)]);

        $this->actingAs($this->admin)
            ->post(route('server.guard-code', $this->server), ['code' => 'k9j4m'])
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request['code'] === 'K9J4M');
    }

    /**
     * The credentials must not ride along with the code.
     *
     * Every other NodeClient call sends daemonPayload(), which carries
     * STEAM_PASS and a freshly generated STEAM_GUARD_CODE. Sending it here would
     * put the account password back on the wire for a request that needs five
     * characters, and would send a generated code alongside the typed one.
     */
    public function test_the_code_request_carries_no_credentials(): void
    {
        $this->server->forceFill(['guard_prompt_at' => now()])->save();
        Http::fake(['*' => Http::response(['accepted' => true], 200)]);

        $this->actingAs($this->admin)
            ->post(route('server.guard-code', $this->server), ['code' => 'K9J4M']);

        Http::assertSent(function ($request) {
            $body = (string) $request->body();

            return ! str_contains($body, 'hunter2')
                && ! str_contains($body, 'STEAM_PASS')
                && ! str_contains($body, 'STEAM_GUARD_CODE');
        });
    }

    /**
     * Steam's alphabet has no A, E, I, O, S, U, Z, 0 or 1.
     *
     * Caught in the browser because a wrong code is not free: Steam counts the
     * attempt and starts rate limiting the account after a few, and a typo that
     * costs an attempt is a typo that should never have left the page.
     */
    public function test_a_code_outside_steams_alphabet_is_refused(): void
    {
        $this->server->forceFill(['guard_prompt_at' => now()])->save();
        Http::fake();

        foreach (['AEIOU', '00000', 'K9J4', 'K9J4MM', ''] as $bad) {
            $this->actingAs($this->admin)
                ->post(route('server.guard-code', $this->server), ['code' => $bad])
                ->assertSessionHasErrors('code');
        }

        Http::assertNothingSent();
    }

    /** A code for a server that is not waiting goes nowhere and says so. */
    public function test_a_code_for_a_server_not_waiting_is_refused(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('server.guard-code', $this->server), ['code' => 'K9J4M'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    /** A stranger cannot answer somebody else's prompt. */
    public function test_a_stranger_cannot_answer_the_prompt(): void
    {
        $this->server->forceFill(['guard_prompt_at' => now()])->save();
        $stranger = User::create([
            'name' => 'Stranger', 'email' => 'stranger@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);
        Http::fake();

        $this->actingAs($stranger)
            ->post(route('server.guard-code', $this->server), ['code' => 'K9J4M'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    /** The code must never reach the audit log. */
    public function test_the_code_is_not_written_to_the_audit_log(): void
    {
        $this->server->forceFill(['guard_prompt_at' => now()])->save();
        Http::fake(['*' => Http::response(['accepted' => true], 200)]);

        $this->actingAs($this->admin)
            ->post(route('server.guard-code', $this->server), ['code' => 'K9J4M']);

        $entry = \App\Models\AuditLog::where('action', 'server.guard_code')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertStringNotContainsString('K9J4M', json_encode($entry->toArray()));
    }
}
