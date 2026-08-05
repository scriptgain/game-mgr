<?php

namespace Tests\Feature;

use App\Models\NotificationChannel;
use App\Models\DatabaseHost;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `$request->validate()` returns only the keys that were present in the request,
 * so reading a `nullable` field straight off the result is an `Undefined array
 * key` for anything that omits it. The HTML forms always post every field, which
 * is why this stayed hidden: it is live only for callers driving the panel
 * programmatically, and it shows up as a 500 rather than a validation message.
 */
class OptionalFieldTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
    }

    public function test_a_user_can_be_updated_without_sending_a_password(): void
    {
        $user = User::create([
            'name' => 'Someone', 'email' => 'someone@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);
        $before = $user->password;

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Someone Else',
                'email' => 'someone@test.local',
                'role' => 'client',
                'timezone' => 'UTC',
            ])
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('Someone Else', $fresh->name);
        $this->assertSame($before, $fresh->password, 'the existing password must survive');
    }

    public function test_a_database_host_can_be_updated_without_resending_its_password(): void
    {
        $host = DatabaseHost::create([
            'name' => 'Local MySQL', 'host' => '127.0.0.1', 'port' => 3306,
            'username' => 'root', 'password' => 'original-secret', 'max_databases' => 10,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.database-hosts.update', $host), [
                'name' => 'Renamed MySQL',
                'host' => '127.0.0.1',
                'port' => 3306,
                'username' => 'root',
                'max_databases' => 10,
            ])
            ->assertRedirect();

        $fresh = $host->fresh();
        $this->assertSame('Renamed MySQL', $fresh->name);
        $this->assertSame('original-secret', $fresh->password);
    }

    public function test_a_channel_can_be_updated_without_resending_its_webhook(): void
    {
        $channel = NotificationChannel::create([
            'name' => 'Ops', 'type' => 'slack', 'target' => 'https://example.test/hook', 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.channels.update', $channel), [
                'name' => 'Ops Renamed',
                'type' => 'slack',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $fresh = $channel->fresh();
        $this->assertSame('Ops Renamed', $fresh->name);
        $this->assertSame('https://example.test/hook', $fresh->target);
    }

    /** Suspending was silently dropped: the attribute was not fillable. */
    public function test_suspending_a_user_actually_suspends_them(): void
    {
        $user = User::create([
            'name' => 'Nuisance', 'email' => 'nuisance@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Nuisance',
                'email' => 'nuisance@test.local',
                'role' => 'client',
                'timezone' => 'UTC',
                'suspended' => 1,
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $user->fresh()->suspended);
    }
}
