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
 * The plain CRUD resources, driven the way a caller would.
 *
 * One test per resource rather than one per verb: the value here is that the
 * round trip works and that the rules match the admin screen's, not that
 * Laravel can write a row.
 */
class ApplicationCrudTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['edition' => 'plus'],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
        $plain = 'gm_'.Str::random(48);
        ApiToken::create([
            'user_id' => $admin->id, 'name' => 'T',
            'token' => hash('sha256', $plain), 'scope' => 'application',
        ]);
        $this->token = $plain;
    }

    private function api(string $method, string $uri, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->json($method, $uri, $body);
    }

    /** Create, read, update, delete, for each resource that is plain CRUD. */
    private function resources(): array
    {
        return [
            'locations' => ['locations', ['short' => 'lax', 'name' => 'Los Angeles'], ['name' => 'LA West']],
            'games' => ['games', ['name' => 'Terraria'], ['name' => 'Terraria Reborn']],
            'mounts' => ['mounts', ['name' => 'Maps', 'source' => '/srv/maps', 'target' => '/maps'], ['name' => 'Shared Maps']],
            'webhooks' => ['webhooks', ['name' => 'Ops', 'url' => 'https://example.test/hook'], ['name' => 'Ops Two']],
            'channels' => ['channels', ['name' => 'Discord', 'type' => 'discord', 'target' => 'https://discord.test/x'], ['name' => 'Discord Two']],
            'database-hosts' => ['database-hosts', ['name' => 'db1', 'host' => 'db.test', 'port' => 3306, 'username' => 'root', 'password' => 'secret', 'max_databases' => 10], ['name' => 'db1 renamed']],
        ];
    }

    public function test_every_resource_round_trips(): void
    {
        foreach ($this->resources() as $label => [$path, $create, $update]) {
            $this->roundTrip($label, $path, $create, $update);
        }
    }

    private function roundTrip(string $label, string $path, array $create, array $update): void
    {
        $created = $this->api('POST', '/api/application/'.$path, $create);
        $this->assertSame(201, $created->status(), $label.' create failed: '.$created->getContent());
        $id = $created->json('attributes.id');
        $this->assertNotNull($id, $label.' did not come back with an id');

        $this->api('GET', '/api/application/'.$path)
            ->assertOk()
            ->assertJsonPath('object', 'list');

        $this->api('GET', '/api/application/'.$path.'/'.$id)->assertOk();

        // The rules are the admin screen's, and those are whole-object rules
        // rather than per-field, so a PATCH carries the full record. That is a
        // deliberate consequence of sharing validation with the form: the API
        // cannot be more permissive than the panel.
        $patched = $this->api('PATCH', '/api/application/'.$path.'/'.$id, array_merge($create, $update));
        $this->assertSame(200, $patched->status(), $label.' patch failed: '.$patched->getContent());
        $patched->assertJsonPath('attributes.name', $update['name']);

        $this->api('DELETE', '/api/application/'.$path.'/'.$id)->assertNoContent();
        $this->api('GET', '/api/application/'.$path.'/'.$id)->assertNotFound();
    }

    /** Rules are the admin screen's, so what the form refuses the API refuses. */
    public function test_validation_matches_the_panel(): void
    {
        $this->api('POST', '/api/application/locations', ['name' => 'No Short Code'])->assertStatus(422);
        $this->api('POST', '/api/application/webhooks', ['name' => 'Bad', 'url' => 'not-a-url'])->assertStatus(422);
        $this->api('POST', '/api/application/channels', ['name' => 'X', 'type' => 'carrier-pigeon', 'target' => 'x'])->assertStatus(422);
    }

    /** A secret is never handed back, and a blank one on update does not wipe it. */
    public function test_a_credential_is_write_only_and_survives_a_patch(): void
    {
        $id = $this->api('POST', '/api/application/database-hosts', [
            'name' => 'db', 'host' => 'db.test', 'port' => 3306,
            'username' => 'root', 'password' => 'the-secret', 'max_databases' => 5,
        ])->assertCreated()->json('attributes.id');

        $shown = $this->api('GET', '/api/application/database-hosts/'.$id)->assertOk()->json('attributes');
        $this->assertArrayNotHasKey('password', $shown);

        $this->api('PATCH', '/api/application/database-hosts/'.$id, [
            'name' => 'db renamed', 'host' => 'db.test', 'port' => 3306,
            'username' => 'root', 'max_databases' => 5,
        ])->assertOk();

        $this->assertNotEmpty(\App\Models\DatabaseHost::find($id)->password,
            'patching another field wiped the stored credential');
    }

    public function test_a_client_token_cannot_reach_any_of_this(): void
    {
        $client = User::create([
            'name' => 'C', 'email' => 'c@test.local', 'password' => 'secret1234', 'role' => 'client',
        ]);
        $plain = 'gm_'.Str::random(48);
        ApiToken::create(['user_id' => $client->id, 'name' => 'T', 'token' => hash('sha256', $plain), 'scope' => 'client']);

        foreach (['locations', 'games', 'webhooks', 'database-hosts'] as $path) {
            $this->withHeader('Authorization', 'Bearer '.$plain)
                ->json('GET', '/api/application/'.$path)
                ->assertForbidden();
        }
    }
}
