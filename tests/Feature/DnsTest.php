<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\Dns\DnsConfig;
use App\Services\Dns\WildcardManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Connection names, phase 1.
 *
 * The contract being tested is not "names work". It is that names are an
 * addition: every failure mode of this feature leaves ip:port exactly as it
 * was, and nothing here can 500 a page or stop a server being created.
 */
class DnsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Node $node;

    private Server $server;

    /** The fake Cloudflare's whole world: id => record. */
    private array $records = [];

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret1234', 'role' => 'admin']);

        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $this->node = Node::create([
            'name' => 'lax-docker-01', 'location_id' => $location->id, 'fqdn' => '45.63.49.152',
            'daemon_port' => 8942, 'memory' => 8192, 'disk' => 51200, 'cpu' => 400,
            'runtimes' => ['docker'], 'dns_label' => 'lax1',
        ]);

        $game = Game::create(['name' => 'Palworld']);
        $template = Template::create(['game_id' => $game->id, 'name' => 'Palworld', 'runtime' => 'docker']);

        $this->server = Server::create([
            'name' => 'Alpha', 'owner_id' => $this->admin->id, 'node_id' => $this->node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'memory' => 2048, 'disk' => 10240, 'cpu' => 100,
        ]);

        $allocation = Allocation::create([
            'node_id' => $this->node->id, 'ip' => '45.63.49.152', 'port' => 8211, 'server_id' => $this->server->id,
        ]);
        $this->server->update(['allocation_id' => $allocation->id]);
        $this->server->refresh()->load('allocation');
    }

    // ------------------------------------------------- the safety property

    public function test_the_direct_address_is_unchanged_however_dns_is_configured(): void
    {
        // Off.
        $this->configure(false);
        $this->assertSame('45.63.49.152:8211', $this->server->address());
        $this->assertNull($this->server->connectName());

        // On and working.
        $this->configure(true);
        $this->assertSame('45.63.49.152:8211', $this->server->address());

        // On and misconfigured: no zone at all.
        config(['domains.zone' => '']);
        $this->assertSame('45.63.49.152:8211', $this->server->address());
        $this->assertNull($this->server->connectName());

        // On, configured, and the provider is failing.
        $this->configure(true, 'cloudflare');
        Http::fake(fn () => Http::response('gateway timeout', 504));
        (new WildcardManager)->sync($this->node);

        $this->assertSame(WildcardManager::STATUS_FAILED, $this->node->fresh()->wildcard_status);
        $this->assertSame('45.63.49.152:8211', $this->server->fresh()->load('allocation')->address());
    }

    public function test_turning_the_feature_off_hides_every_name_without_touching_a_row(): void
    {
        $this->configure(true);
        (new WildcardManager)->refreshServerNames($this->node);

        $named = $this->server->fresh();
        $this->assertSame('alpha.lax1.play.example.com', $named->connectName());
        $this->assertSame('alpha.lax1.play.example.com:8211', $named->load('allocation')->connectAddress());

        $this->configure(false);

        $off = $this->server->fresh();
        $this->assertNull($off->connectName());
        $this->assertNull($off->connectAddress());
        // The column is still there, so turning it back on needs no repair.
        $this->assertSame('alpha.lax1.play.example.com', $off->connect_name);
    }

    // ------------------------------------------------------ record creation

    public function test_the_wildcard_is_created_grey_clouded(): void
    {
        $this->configure(true, 'cloudflare');
        $this->fakeCloudflare();

        $status = (new WildcardManager)->sync($this->node);

        $this->assertSame(WildcardManager::STATUS_ACTIVE, $status);
        $this->assertNull($this->node->fresh()->wildcard_error);

        $created = collect($this->records)->firstWhere('name', '*.lax1.play.example.com');
        $this->assertNotNull($created, 'the wildcard record should exist at the provider');
        $this->assertSame('A', $created['type']);
        $this->assertSame('45.63.49.152', $created['content']);

        // The one assertion that matters most: an orange record silently kills
        // every game server under it, so proxied must be false on every write.
        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST') {
                return false;
            }

            return $request->data()['proxied'] === false;
        });
    }

    public function test_a_server_is_named_at_creation_with_no_provider_call_at_all(): void
    {
        $this->configure(true, 'cloudflare');
        // Any HTTP call at all in this test is a failure: the wildcard already
        // answers for new servers, which is the whole point of doing it per node.
        Http::preventStrayRequests();
        Http::fake([]);

        $second = Server::create([
            'name' => 'Bravo', 'owner_id' => $this->admin->id, 'node_id' => $this->node->id,
            'template_id' => $this->server->template_id, 'runtime' => 'docker',
            'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);

        $this->assertSame('bravo', $second->dns_label);
        $this->assertSame('bravo.lax1.play.example.com', $second->connect_name);
    }

    public function test_two_servers_with_one_name_get_different_labels(): void
    {
        $this->configure(true);
        (new WildcardManager)->refreshServerNames($this->node);

        $twin = Server::create([
            'name' => 'Alpha', 'owner_id' => $this->admin->id, 'node_id' => $this->node->id,
            'template_id' => $this->server->template_id, 'runtime' => 'docker',
            'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);

        // The label a customer has already handed to their players never moves
        // under them; the newcomer is the one that gets suffixed.
        $this->assertSame('alpha', $this->server->fresh()->dns_label);
        $this->assertSame('alpha-2', $twin->dns_label);
        $this->assertSame('alpha-2.lax1.play.example.com', $twin->connect_name);

        // And a later reconcile leaves both exactly where they are.
        (new WildcardManager)->refreshServerNames($this->node);
        $this->assertSame('alpha', $this->server->fresh()->dns_label);
        $this->assertSame('alpha-2', $twin->fresh()->dns_label);
    }

    // ------------------------------------------------------- failure paths

    public function test_a_provider_that_is_down_records_the_failure_instead_of_throwing(): void
    {
        $this->configure(true, 'cloudflare');
        Http::fake(fn () => Http::response(['success' => false, 'errors' => [['code' => 10000, 'message' => 'Authentication error']]], 403));

        $status = (new WildcardManager)->sync($this->node);

        $this->assertSame(WildcardManager::STATUS_FAILED, $status);
        $this->assertStringContainsString('Authentication error', (string) $this->node->fresh()->wildcard_error);

        // And the node page still renders, rather than 500ing on the way to
        // telling somebody what is wrong.
        $this->actingAs($this->admin)
            ->get(route('admin.nodes.show', $this->node))
            ->assertOk()
            ->assertSee('Authentication error', false);
    }

    public function test_a_connection_error_is_recorded_rather_than_raised(): void
    {
        $this->configure(true, 'cloudflare');
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out'));

        $status = (new WildcardManager)->sync($this->node);

        $this->assertSame(WildcardManager::STATUS_FAILED, $status);
        $this->assertStringContainsString('timed out', (string) $this->node->fresh()->wildcard_error);
    }

    public function test_a_node_with_no_label_is_reported_not_guessed(): void
    {
        $this->configure(true);
        $this->node->update(['dns_label' => null]);

        $this->assertSame(WildcardManager::STATUS_UNLABELLED, (new WildcardManager)->sync($this->node->fresh()));
        $this->assertNull($this->server->fresh()->connectName());
    }

    public function test_a_proxied_record_is_reported_as_wrong(): void
    {
        $this->configure(true, 'cloudflare');
        $this->fakeCloudflare(proxyEverything: true);

        $this->assertSame(WildcardManager::STATUS_DRIFT, (new WildcardManager)->sync($this->node));
        $this->assertStringContainsString('grey cloud', (string) $this->node->fresh()->wildcard_error);
    }

    // ---------------------------------------------------------------- sync

    public function test_dns_sync_puts_a_deleted_record_back(): void
    {
        $this->configure(true, 'cloudflare');
        $this->fakeCloudflare();

        $this->artisan('gamemgr:dns-sync')->assertExitCode(0);
        $this->assertCount(1, $this->records);

        // Somebody deletes it at the provider, by hand, at 3am.
        $this->records = [];
        $this->node->forceFill(['wildcard_status' => null])->saveQuietly();

        $this->artisan('gamemgr:dns-sync')->assertExitCode(0);

        $this->assertCount(1, $this->records);
        $this->assertSame(WildcardManager::STATUS_ACTIVE, $this->node->fresh()->wildcard_status);
    }

    public function test_dns_sync_backfills_servers_created_while_the_feature_was_off(): void
    {
        // Created with names turned off: no label, no name, and it still works.
        $this->configure(false);
        $orphan = Server::create([
            'name' => 'Charlie', 'owner_id' => $this->admin->id, 'node_id' => $this->node->id,
            'template_id' => $this->server->template_id, 'runtime' => 'docker',
            'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);
        $this->assertNull($orphan->connect_name);

        $this->configure(true, 'cloudflare');
        $this->fakeCloudflare();
        $this->artisan('gamemgr:dns-sync')->assertExitCode(0);

        $this->assertSame('charlie.lax1.play.example.com', $orphan->fresh()->connect_name);
    }

    public function test_dns_sync_does_nothing_at_all_when_the_feature_is_off(): void
    {
        $this->configure(false);
        Http::preventStrayRequests();
        Http::fake([]);

        $this->artisan('gamemgr:dns-sync')->assertExitCode(0);
    }

    public function test_moving_a_server_to_another_node_renames_it(): void
    {
        $this->configure(true);
        (new WildcardManager)->refreshServerNames($this->node);

        $other = Node::create([
            'name' => 'fra-docker-01', 'location_id' => $this->node->location_id, 'fqdn' => '167.235.14.40',
            'daemon_port' => 8942, 'memory' => 8192, 'disk' => 51200, 'cpu' => 400,
            'runtimes' => ['docker'], 'dns_label' => 'fra1',
        ]);

        $this->server->fresh()->update(['node_id' => $other->id]);

        $this->assertSame('alpha.fra1.play.example.com', $this->server->fresh()->connect_name);
    }

    // ------------------------------------------------------------- settings

    public function test_the_api_token_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->admin)->put(route('settings.domains.update'), [
            'domains_enabled' => '1',
            'domains_provider' => 'cloudflare',
            'domains_zone' => 'play.example.com',
            'domains_api_token' => 'v1.0-super-secret',
        ])->assertRedirect(route('settings.domains.edit'));

        $stored = (string) Setting::where('key', 'domains_api_token')->value('value');

        $this->assertNotSame('v1.0-super-secret', $stored, 'the token must not be stored in plaintext');
        $this->assertStringNotContainsString('super-secret', $stored);
        $this->assertSame('v1.0-super-secret', Setting::secret('domains_api_token'));
        $this->assertSame('v1.0-super-secret', DnsConfig::token());
    }

    public function test_the_settings_page_renders_and_names_cannot_be_turned_on_without_a_zone(): void
    {
        $this->actingAs($this->admin)->get(route('settings.domains.edit'))->assertOk()->assertSee('Connection Names');

        $this->actingAs($this->admin)->put(route('settings.domains.update'), [
            'domains_enabled' => '1',
            'domains_provider' => 'cloudflare',
            'domains_zone' => '',
        ])->assertSessionHasErrors('domains_zone');

        $this->assertNotSame('1', Setting::get('domains_enabled'));
    }

    /**
     * A feature that is off should say so, not vanish.
     *
     * Phase one shipped complete and dormant, which meant a server page showed
     * a bare address and no hint that a name was five minutes away. The line is
     * for admins only: a customer cannot open Settings, and pointing them at a
     * door they cannot open is worse than the address they already have.
     */
    public function test_a_server_says_where_names_are_switched_on_but_only_to_an_admin(): void
    {
        $this->configure(false);

        $this->actingAs($this->admin)
            ->get(route('admin.servers.show', $this->server))
            ->assertOk()
            ->assertSee('Connection names are off', false)
            ->assertSee(route('settings.domains.edit'), false)
            // The direct address is still exactly where it was.
            ->assertSee('45.63.49.152:8211');

        $customer = User::create([
            'name' => 'Customer', 'email' => 'customer@test.local',
            'password' => 'secret1234', 'role' => 'user',
        ]);
        $this->server->update(['owner_id' => $customer->id]);

        $this->actingAs($customer)
            ->get(route('server.network', $this->server))
            ->assertOk()
            ->assertDontSee('Connection names are off', false)
            ->assertSee('45.63.49.152:8211');
    }

    /** With names working, the prompt has nothing to say and stays away. */
    public function test_the_prompt_disappears_once_a_server_has_a_name(): void
    {
        $this->configure(true);
        (new WildcardManager)->refreshServerNames($this->node);

        $this->actingAs($this->admin)
            ->get(route('admin.servers.show', $this->server))
            ->assertOk()
            ->assertSee('alpha.lax1.play.example.com:8211')
            ->assertDontSee('Connection names are off', false);
    }

    /** On, with a zone, but the node was never labelled: say that instead. */
    public function test_an_unlabelled_node_is_named_as_the_reason(): void
    {
        $this->configure(true);
        $this->node->update(['dns_label' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.servers.show', $this->server))
            ->assertOk()
            ->assertSee('which has no label', false);
    }

    /**
     * The one that actually happened.
     *
     * A node's label went from lax1 to empty, the hourly reconciler did exactly
     * what an unlabelled node means and deleted the wildcard, and every server
     * on that node stopped resolving. Nobody set out to do it: the field is
     * optional and an empty one is an ordinary thing to post.
     *
     * So an empty label is now only honoured when the request says so, and any
     * other save leaves it alone.
     */
    public function test_an_unconfirmed_save_cannot_clear_the_dns_label(): void
    {
        $this->configure(true);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.nodes.update', $this->node), $this->nodeForm(['dns_label' => '']));

        $response->assertRedirect(route('admin.nodes.show', $this->node));
        $this->assertSame('lax1', $this->node->fresh()->dns_label, 'the label was silently thrown away');
        $this->assertStringContainsString('left as "lax1"', session('warning'));
    }

    /** Deliberately clearing it still works, because sometimes that is the job. */
    public function test_a_confirmed_save_clears_it(): void
    {
        $this->configure(true);

        $this->actingAs($this->admin)->put(route('admin.nodes.update', $this->node), $this->nodeForm([
            'dns_label' => '',
            'confirm_clear_dns_label' => '1',
        ]));

        $this->assertNull($this->node->fresh()->dns_label);
    }

    /** An edit that never mentions the label must not disturb it either. */
    public function test_an_unrelated_edit_leaves_the_label_alone(): void
    {
        $this->configure(true);

        $form = $this->nodeForm(['name' => 'renamed-node']);
        unset($form['dns_label']);

        $this->actingAs($this->admin)->put(route('admin.nodes.update', $this->node), $form);

        $this->assertSame('renamed-node', $this->node->fresh()->name);
        $this->assertSame('lax1', $this->node->fresh()->dns_label);
    }

    /**
     * Node edits had no audit trail at all, so when the label vanished there
     * was no way to tell who or what had done it.
     */
    public function test_a_node_edit_is_audited(): void
    {
        $this->configure(true);

        $this->actingAs($this->admin)->put(route('admin.nodes.update', $this->node), $this->nodeForm([
            'dns_label' => 'lax2',
        ]));

        $entry = \App\Models\AuditLog::where('action', 'node.update')->latest('id')->first();

        $this->assertNotNull($entry, 'a node edit left no trail');
        $this->assertStringContainsString('lax1', $entry->description);
        $this->assertStringContainsString('lax2', $entry->description);
        $this->assertSame($this->admin->id, $entry->user_id);
    }

    /** The form the node edit screen posts, with overrides. */
    private function nodeForm(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->node->name,
            'location_id' => $this->node->location_id,
            'connection_mode' => $this->node->connection_mode ?? 'direct',
            'scheme' => $this->node->scheme ?? 'http',
            'fqdn' => $this->node->fqdn,
            'dns_label' => $this->node->dns_label,
            'daemon_port' => $this->node->daemon_port,
            'sftp_port' => $this->node->sftp_port ?? 2022,
            'memory' => $this->node->memory,
            'memory_overallocate' => 0,
            'disk' => $this->node->disk,
            'disk_overallocate' => 0,
            'cpu' => $this->node->cpu,
            'cpu_overallocate' => 0,
            'upload_size' => 256,
            'daemon_base' => $this->node->daemon_base ?: '/var/lib/gamemgr/volumes',
            // A MAP, not a list: the form posts runtimes[docker] => "1" from
            // its toggles, and a list validates as an array and then filters
            // down to nothing, which fails with "pick at least one runtime".
            'runtimes' => array_fill_keys($this->node->runtimes ?: ['docker'], '1'),
            'public' => 1,
        ], $overrides);
    }

    // ------------------------------------------------------------ fixtures

    private function configure(bool $enabled, string $provider = 'null'): void
    {
        config([
            'domains.enabled' => $enabled,
            'domains.provider' => $provider,
            'domains.zone' => 'play.example.com',
            'domains.api_token' => 'test-token',
        ]);
    }

    /**
     * A Cloudflare that holds its records in a property.
     *
     * Enough of the real API to be worth testing against: the zone lookup walks
     * up from play.example.com to example.com, listing filters by name, and a
     * second write updates rather than duplicates.
     */
    private function fakeCloudflare(bool $proxyEverything = false): void
    {
        $this->records = [];

        Http::fake(function (Request $request) use ($proxyEverything) {
            $url = $request->url();
            $method = mb_strtoupper($request->method());
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $path = (string) parse_url($url, PHP_URL_PATH);

            // Zone lookup. Only the registrable domain is a zone, so this also
            // proves the driver walks up rather than demanding the exact name.
            if ($path === '/client/v4/zones') {
                $name = (string) ($query['name'] ?? '');

                return Http::response([
                    'success' => true,
                    'errors' => [],
                    'result' => $name === 'example.com' ? [['id' => 'zone-123', 'name' => $name]] : [],
                ]);
            }

            if (! str_starts_with($path, '/client/v4/zones/zone-123/dns_records')) {
                return Http::response(['success' => false, 'errors' => [['message' => 'Unexpected path '.$path]]], 404);
            }

            $recordId = trim(mb_substr($path, mb_strlen('/client/v4/zones/zone-123/dns_records')), '/');

            if ($method === 'GET') {
                $matches = array_values(array_filter(
                    $this->records,
                    fn (array $r) => $r['name'] === ($query['name'] ?? '') && $r['type'] === ($query['type'] ?? 'A'),
                ));

                return Http::response(['success' => true, 'errors' => [], 'result' => $matches]);
            }

            if ($method === 'DELETE') {
                unset($this->records[$recordId]);

                return Http::response(['success' => true, 'errors' => [], 'result' => ['id' => $recordId]]);
            }

            $body = $request->data();
            $id = $recordId !== '' ? $recordId : 'rec-'.(count($this->records) + 1);

            $this->records[$id] = [
                'id' => $id,
                'type' => $body['type'],
                'name' => $body['name'],
                'content' => $body['content'],
                'ttl' => $body['ttl'],
                // A provider that quietly turns the proxy back on is a real
                // failure mode, so the fake can do it too.
                'proxied' => $proxyEverything ? true : (bool) $body['proxied'],
            ];

            return Http::response(['success' => true, 'errors' => [], 'result' => $this->records[$id]]);
        });
    }
}
