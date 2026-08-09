<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The API had never been called.
 *
 * AuthenticateApiToken looked a token up with ApiToken::findByPlaintext(), and
 * nothing in the codebase defined that method, so every request through the
 * api.token middleware died with a fatal error. isExpired() was written and
 * never called, and allowed_ips was stored, cast, and enforced nowhere.
 *
 * None of that was caught because no test ever presented a token. These do.
 */
class ApiTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        // The application scope is a paid feature, so these run licensed.
        // Otherwise every application test would be measuring the edition gate.
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['edition' => 'plus'],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $this->admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
    }

    /** Mints a token the way the account screen does, returning the plaintext. */
    private function token(array $overrides = []): string
    {
        $plain = 'gm_'.Str::random(48);

        ApiToken::create(array_merge([
            'user_id' => $this->admin->id,
            'name' => 'Test',
            'token' => hash('sha256', $plain),
            'scope' => 'application',
        ], $overrides));

        return $plain;
    }

    /** The one that was broken: a valid token has to actually work. */
    public function test_a_valid_token_is_accepted(): void
    {
        $plain = $this->token();

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/application/me')
            ->assertOk()
            ->assertJsonPath('email', 'admin@test.local');
    }

    public function test_no_token_is_refused(): void
    {
        $this->getJson('/api/application/me')->assertUnauthorized();
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $this->withHeader('Authorization', 'Bearer gm_'.Str::random(48))
            ->getJson('/api/application/me')
            ->assertUnauthorized();
    }

    /**
     * Only the hash is stored, so presenting the hash must not work either.
     * Somebody who reads the database still should not be able to call the API.
     */
    public function test_the_stored_hash_is_not_itself_a_credential(): void
    {
        $plain = $this->token();

        $this->withHeader('Authorization', 'Bearer '.hash('sha256', $plain))
            ->getJson('/api/application/me')
            ->assertUnauthorized();
    }

    /** isExpired() existed and was never consulted. */
    public function test_an_expired_token_is_refused(): void
    {
        $plain = $this->token(['expires_at' => now()->subDay()]);

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/application/me')
            ->assertUnauthorized();
    }

    public function test_a_token_expiring_later_still_works(): void
    {
        $plain = $this->token(['expires_at' => now()->addDay()]);

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/application/me')
            ->assertOk();
    }

    /** allowed_ips was stored, cast, and enforced nowhere. */
    public function test_a_token_bound_to_addresses_refuses_others(): void
    {
        $plain = $this->token(['allowed_ips' => ['203.0.113.5']]);

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->getJson('/api/application/me')
            ->assertForbidden();
    }

    public function test_a_token_bound_to_addresses_accepts_its_own(): void
    {
        $plain = $this->token(['allowed_ips' => ['203.0.113.5', '198.51.100.9']]);

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->getJson('/api/application/me')
            ->assertOk();
    }

    /** An empty list means "from anywhere", not "from nowhere". */
    public function test_no_address_restriction_means_anywhere(): void
    {
        $plain = $this->token(['allowed_ips' => []]);

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->getJson('/api/application/me')
            ->assertOk();
    }

    /**
     * A client token must not reach the application scope. This is the whole
     * point of there being two scopes, and it is what stops a customer's own
     * token listing every server on the panel.
     */
    public function test_a_client_token_cannot_reach_the_application_scope(): void
    {
        $client = User::create([
            'name' => 'Customer', 'email' => 'client@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);
        $plain = 'gm_'.Str::random(48);
        ApiToken::create([
            'user_id' => $client->id, 'name' => 'Theirs',
            'token' => hash('sha256', $plain), 'scope' => 'client',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/application/me')
            ->assertForbidden();

        // And its own scope still works.
        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/client/me')
            ->assertOk();
    }

    public function test_using_a_token_records_when_it_was_last_used(): void
    {
        $plain = $this->token();

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/application/me')
            ->assertOk();

        $this->assertNotNull(ApiToken::first()->fresh()->last_used_at);
    }
}
