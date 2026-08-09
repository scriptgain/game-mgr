<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acting as a customer, for support.
 *
 * The trap this exists to pin: while impersonating a client you are NOT an
 * admin, so if the way back sits behind the admin gate it refuses the only
 * person who needs it and strands them in somebody else's account with no exit
 * but clearing cookies.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
        $this->client = User::create([
            'name' => 'A Customer', 'email' => 'client@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);
    }

    public function test_an_admin_can_act_as_a_client(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.act-as', $this->client))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->client);
        $this->assertSame($this->admin->id, session('impersonator_id'), 'the way back must be remembered');
    }

    /**
     * The one that matters. A client session carrying an impersonator token must
     * be able to end it, even though a client cannot reach anything else in the
     * admin area.
     */
    public function test_the_way_back_works_while_you_are_not_an_admin(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.act-as', $this->client));

        $this->assertAuthenticatedAs($this->client);
        // Proof the session really is a client's: the admin area refuses it.
        $this->get(route('admin.users.index'))->assertForbidden();

        // And yet the exit is reachable.
        $this->delete(route('act-as.stop'))->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_a_client_cannot_act_as_anybody(): void
    {
        $other = User::create([
            'name' => 'Someone Else', 'email' => 'other@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);

        $this->actingAs($this->client)
            ->post(route('admin.users.act-as', $other))
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->client);
    }

    /** Nothing is gained, and the audit trail stops saying who did what. */
    public function test_an_admin_cannot_act_as_another_admin(): void
    {
        $other = User::create([
            'name' => 'Other Admin', 'email' => 'admin2@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.act-as', $other))
            ->assertRedirect();

        $this->assertAuthenticatedAs($this->admin); // not swapped
        $this->assertNull(session('impersonator_id'));
    }

    public function test_a_suspended_account_is_refused(): void
    {
        $this->client->forceFill(['suspended' => true])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.users.act-as', $this->client))
            ->assertRedirect();

        $this->assertAuthenticatedAs($this->admin);
    }

    /** Stopping when nothing was started is harmless, not an error page. */
    public function test_stopping_without_starting_just_goes_home(): void
    {
        $this->actingAs($this->client)
            ->delete(route('act-as.stop'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->client);
    }

    public function test_both_ends_are_recorded(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.act-as', $this->client));
        $this->delete(route('act-as.stop'));

        $this->assertTrue(AuditLog::where('action', 'user.impersonate.start')->exists());
        $this->assertTrue(AuditLog::where('action', 'user.impersonate.stop')->exists());
    }
}
