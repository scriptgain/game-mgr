<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Telemetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Telemetry is only acceptable if what it sends is what it says it sends.
 *
 * These pin the contract rather than the plumbing: counts and no names, off
 * meaning off, and a failure never reaching the operator.
 */
class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn () => Http::response(['ok' => true], 200));
    }

    /** The payload is the contract. Anything new here is something nobody agreed to. */
    public function test_the_payload_carries_counts_and_never_names(): void
    {
        $payload = Telemetry::payload();

        $this->assertSame(
            ['id', 'product', 'version', 'php', 'os', 'edition', 'nodes', 'servers', 'runtimes', 'at'],
            array_keys($payload),
            'the telemetry payload changed. Every field here is something an operator agreed to when they last read the settings page.',
        );

        $this->assertIsInt($payload['nodes']);
        $this->assertIsInt($payload['servers']);

        // Nothing identifying anywhere in it.
        $flat = strtolower(json_encode($payload));
        foreach (['email', 'hostname', 'ip', 'domain', 'address', 'name'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $flat,
                'the payload gained a '.$forbidden.' field');
        }
    }

    public function test_off_means_nothing_is_sent(): void
    {
        Telemetry::setEnabled(false);

        $this->assertFalse(Telemetry::send(true));
        Http::assertNothingSent();
    }

    public function test_on_sends_and_records_exactly_what_went(): void
    {
        Telemetry::setEnabled(true);

        $this->assertTrue(Telemetry::send(true));
        Http::assertSent(fn ($request) => $request->url() === Telemetry::ENDPOINT);

        $last = Telemetry::lastSent();
        $this->assertNotNull($last, 'nothing was recorded, so the settings page can show nothing');
        $this->assertSame(Telemetry::payload()['id'], $last['id']);
    }

    /** The vendor being unreachable is not the operator's problem. */
    public function test_a_failure_is_silent(): void
    {
        Telemetry::setEnabled(true);
        Http::fake(fn () => throw new \RuntimeException('no route to host'));

        // No exception, and no fuss.
        $this->assertFalse(Telemetry::send(true));
    }

    /** The install id is stable, or two sends look like two panels. */
    public function test_the_install_id_is_stable(): void
    {
        $first = Telemetry::installId();

        $this->assertSame($first, Telemetry::installId());
        $this->assertSame($first, Setting::get('telemetry_id'));
    }

    // ------------------------------------------------- the auditable part

    /** The page exists to show the payload. If it does not, none of this counts. */
    public function test_the_settings_page_shows_what_would_go_and_what_did(): void
    {
        Telemetry::setEnabled(true);
        Telemetry::send(true);

        $this->actingAs($this->admin())
            ->get(route('settings.telemetry.edit'))
            ->assertOk()
            ->assertSee('Counts, Never Names')
            ->assertSee(Telemetry::ENDPOINT)
            ->assertSee(Telemetry::installId());
    }

    public function test_the_switch_persists_and_says_which_way_it_went(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('settings.telemetry.update'), ['telemetry_enabled' => '0'])
            ->assertRedirect(route('settings.telemetry.edit'));

        $this->assertFalse(Telemetry::enabled());

        // Off is honoured by the send path too, not only by the page.
        $this->actingAs($admin)->post(route('settings.telemetry.send'));
        Http::assertNothingSent();

        $this->actingAs($admin)->put(route('settings.telemetry.update'), ['telemetry_enabled' => '1']);
        $this->assertTrue(Telemetry::enabled());
    }

    /** Asked once, rather than assumed. Either answer stops the asking. */
    public function test_the_first_admin_is_asked_and_is_not_asked_twice(): void
    {
        $admin = $this->admin();

        $this->assertFalse(Telemetry::acknowledged());
        $this->actingAs($admin)->get(route('settings.telemetry.edit'))
            ->assertSee('This install sends anonymous counts');

        $this->actingAs($admin)->put(route('settings.telemetry.update'), ['telemetry_enabled' => '1']);

        $this->assertTrue(Telemetry::acknowledged());
        $this->actingAs($admin)->get(route('settings.telemetry.edit'))
            ->assertDontSee('This install sends anonymous counts');
    }

    /** The daily command is the only thing that sends on its own. */
    public function test_the_scheduled_command_respects_the_switch(): void
    {
        Telemetry::setEnabled(false);
        $this->artisan('telemetry:send --force')->expectsOutputToContain('Telemetry is off')->assertSuccessful();
        Http::assertNothingSent();

        Telemetry::setEnabled(true);
        $this->artisan('telemetry:send --force')->assertSuccessful();
        Http::assertSent(fn ($request) => $request->url() === Telemetry::ENDPOINT);
    }

    private function admin(): User
    {
        Setting::updateOrCreate(['key' => 'setup_complete'], ['value' => '1']);

        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
    }

    /** Not forced, and recently sent, means it waits rather than spamming. */
    public function test_it_does_not_send_more_than_once_a_day(): void
    {
        Telemetry::setEnabled(true);
        Telemetry::send(true);
        Http::fake(fn () => Http::response(['ok' => true], 200));

        $this->assertFalse(Telemetry::send());
        Http::assertNothingSent();
    }
}
