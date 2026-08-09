<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\NodeMetric;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * What a node daemon talks to.
 *
 * The panel is the source of truth for everything; a node holds no state it
 * could disagree with. All this endpoint does is verify identity, take the
 * node's word on its own health, and hand back the servers it should be running.
 */
class NodeApiController extends Controller
{
    /**
     * Exchange a single-use enroll token for the long-lived daemon credential.
     * The plaintext credential is returned exactly once and only its hash is
     * stored, so a database leak does not hand over live node access.
     */
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'os' => ['nullable', 'string', 'max:120'],
            'kernel' => ['nullable', 'string', 'max:120'],
            'arch' => ['nullable', 'string', 'max:32'],
            'docker' => ['nullable', 'string', 'max:64'],
            'agent_version' => ['nullable', 'string', 'max:32'],
            'cpu_cores' => ['nullable', 'integer', 'min:1'],
            'memory' => ['nullable', 'integer', 'min:0'],
            'disk' => ['nullable', 'integer', 'min:0'],
            'runtimes' => ['nullable', 'array'],
        ]);

        $node = Node::where('enroll_token', $data['token'])->first();

        if (! $node || $node->enroll_token_expires_at?->isPast()) {
            return response()->json(['message' => 'That enrollment token is not valid or has expired.'], 401);
        }

        $plain = Str::random(64);

        // Enrollment is the one moment the panel learns what a node can actually
        // run. Until it happens the row carries the model's ['docker'] guess,
        // and Node::supports() gates server creation on that array: a node with
        // steamcmd and LinuxGSM installed and working would refuse every
        // template but the Docker ones. Filtered against RUNTIMES so a daemon
        // cannot invent a runtime the panel has no driver for, and only applied
        // when the daemon actually reported some, so a node enrolled by an older
        // agent keeps whatever the operator set by hand.
        $reported = array_values(array_intersect(Node::RUNTIMES, $data['runtimes'] ?? []));

        $node->forceFill([
            'runtimes' => $reported !== [] ? $reported : $node->runtimes,
            'daemon_token_id' => Str::random(16),
            // The hash authenticates the daemon when it calls in. The encrypted
            // plaintext is what the panel presents when it calls out: without
            // it the panel has no credential for this node at all and every
            // authenticated request is refused by a node that is working fine.
            'daemon_token' => hash('sha256', $plain),
            'daemon_secret' => $plain,
            'enroll_token' => null,
            'enroll_token_expires_at' => null,
            'enrolled_at' => now(),
            'last_seen_at' => now(),
            'reported_os' => $data['os'] ?? null,
            'reported_kernel' => $data['kernel'] ?? null,
            'reported_arch' => $data['arch'] ?? null,
            'reported_docker' => $data['docker'] ?? null,
            'reported_agent_version' => $data['agent_version'] ?? null,
            'reported_cpu_cores' => $data['cpu_cores'] ?? null,
            'reported_memory' => $data['memory'] ?? null,
            'reported_disk' => $data['disk'] ?? null,
        ])->save();

        AuditLog::record('node.enroll', 'Node "'.$node->name.'" enrolled from '.$request->ip(), $node);

        return response()->json([
            'node' => ['uuid' => $node->uuid, 'name' => $node->name],
            'token' => $plain,
            'panel' => rtrim((string) config('app.url'), '/'),
            'heartbeat_interval' => 30,
        ]);
    }

    public function heartbeat(Request $request)
    {
        $node = $request->attributes->get('agent_node');

        $data = $request->validate([
            'cpu' => ['nullable', 'numeric'],
            'memory' => ['nullable', 'integer'],
            'disk' => ['nullable', 'integer'],
            'load' => ['nullable', 'numeric'],
            'running' => ['nullable', 'integer'],
            'agent_version' => ['nullable', 'string', 'max:32'],
            'sftp_enabled' => ['nullable', 'boolean'],
            'sftp_fingerprint' => ['nullable', 'string', 'max:120'],
        ]);

        $node->forceFill([
            'last_seen_at' => now(),
            'reported_agent_version' => $data['agent_version'] ?? $node->reported_agent_version,
            // Reported, never configured. The client area shows an SFTP host and
            // username off the back of this, so it has to mean "the node is
            // really answering" rather than "somebody ticked a box". An older
            // agent sends neither key and is treated as not offering it.
            'sftp_enabled' => (bool) ($data['sftp_enabled'] ?? false),
            'sftp_fingerprint' => $data['sftp_fingerprint'] ?? null,
        ])->save();

        NodeMetric::create([
            'node_id' => $node->id,
            'sampled_at' => now(),
            'cpu' => (float) ($data['cpu'] ?? 0),
            'memory' => (int) ($data['memory'] ?? 0),
            'disk' => (int) ($data['disk'] ?? 0),
            'load' => (float) ($data['load'] ?? 0),
            'server_count' => $node->servers()->count(),
            'running_count' => (int) ($data['running'] ?? 0),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Everything this node should be running, with full startup detail. */
    public function servers(Request $request)
    {
        $node = $request->attributes->get('agent_node');

        return response()->json([
            'servers' => $node->servers()
                ->with(['template.variables', 'variables', 'allocation'])
                ->get()
                ->map(fn (Server $s) => $s->daemonPayload() + ['status' => $s->status]),
        ]);
    }

    /**
     * Whether an SFTP login is allowed, and to which server's files.
     *
     * The daemon holds no accounts, so this is where an SFTP password is
     * actually checked. Two things matter here.
     *
     * A refusal is a 200 with granted:false, not a 401. The node authenticates
     * to this endpoint with its own bearer token, and 401 already means "this
     * node's credential is wrong". Overloading it would make a node with a stale
     * token report every customer's login as a bad password, and nobody would
     * think to look at the node.
     *
     * And the answer comes from ServerPolicy, the same class the web file
     * manager asks. SFTP must not be a second opinion about who may touch what:
     * revoking someone in the panel has to revoke them here, in the same moment,
     * without anybody remembering to update two places.
     */
    public function sftpAuthenticate(Request $request)
    {
        $node = $request->attributes->get('agent_node');

        $data = $request->validate([
            'username' => ['required', 'string', 'max:160'],
            'password' => ['required', 'string', 'max:512'],
            'ip' => ['nullable', 'string', 'max:64'],
        ]);

        $denied = response()->json(['granted' => false]);

        // username.serveridentifier. Split on the LAST dot, because a username
        // may contain dots and the server identifier never does.
        $position = strrpos($data['username'], '.');
        if ($position === false) {
            return $denied;
        }
        $username = substr($data['username'], 0, $position);
        $identifier = substr($data['username'], $position + 1);

        // Rate limited on the panel as well as on the node. The node limits per
        // address; this limits per account, so a run spread across many
        // addresses still cannot grind away at one person's password.
        $throttle = 'sftp:'.$username;
        if (RateLimiter::tooManyAttempts($throttle, 10)) {
            return $denied;
        }

        $user = User::where('username', $username)->first();
        $server = Server::where('uuid_short', $identifier)->where('node_id', $node->id)->first();

        // Checked in one branch on purpose. Answering "no such user" faster than
        // "wrong password" tells an unauthenticated stranger which accounts
        // exist, and Hash::check on a dummy hash keeps the timing even.
        $password = $data['password'];
        $valid = $user && Hash::check($password, $user->password);
        if (! $user) {
            Hash::check($password, '$2y$12$'.str_repeat('x', 53));
        }

        if (! $valid || ! $server || $user->suspended) {
            RateLimiter::hit($throttle, 900);
            AuditLog::record('sftp.denied', 'An SFTP login was refused.', $server, $server?->id, [
                'username' => $data['username'],
                'client_ip' => $data['ip'] ?? null,
                'node' => $node->name,
            ]);

            return $denied;
        }

        // file.sftp is the permission to connect at all, separate from what may
        // then be done with the files. It is deliberately not in a subuser's
        // default set: being given the file manager should not silently hand out
        // access to the box from the outside.
        //
        // Suspension is handled by this same check rather than by a test of its
        // own here. ServerPolicy already refuses every non-read permission on a
        // suspended server, so a suspended customer cannot connect, while an
        // admin still can and support does not lose file access to the server it
        // has been called about. Adding a second suspension rule here would be
        // the divergence this endpoint exists to avoid.
        if (! Gate::forUser($user)->allows('check', [$server, 'file.sftp'])) {
            AuditLog::record('sftp.denied', 'An SFTP login was refused: that account may not use SFTP for this server.', $server, $server->id, [
                'username' => $data['username'],
                'client_ip' => $data['ip'] ?? null,
            ]);

            return $denied;
        }

        $permissions = collect(['file.read', 'file.create', 'file.update', 'file.delete'])
            ->filter(fn (string $permission) => Gate::forUser($user)->allows('check', [$server, $permission]))
            ->values();

        RateLimiter::clear($throttle);
        // The client's own address goes in the properties, not in the log's ip
        // column: that column records who made the HTTP request, which for this
        // endpoint is always the node.
        AuditLog::record('sftp.connected', $user->name.' connected over SFTP.', $server, $server->id, [
            'username' => $data['username'],
            'client_ip' => $data['ip'] ?? null,
            'permissions' => $permissions->all(),
        ]);

        return response()->json([
            'granted' => true,
            'server_uuid' => $server->uuid,
            'runtime' => $server->runtime,
            'permissions' => $permissions,
            'username' => $data['username'],
        ]);
    }

    /** The daemon reporting what a server is actually doing. */
    public function state(Request $request, string $uuid)
    {
        $node = $request->attributes->get('agent_node');

        $server = Server::where('uuid', $uuid)->where('node_id', $node->id)->first();
        if (! $server) {
            return response()->json(['message' => 'No such server on this node.'], 404);
        }

        $data = $request->validate([
            'state' => ['required', 'in:offline,starting,running,stopping,crashed'],
            'cpu' => ['nullable', 'numeric'],
            'memory' => ['nullable', 'integer'],
            'disk' => ['nullable', 'integer'],
            'players' => ['nullable', 'integer'],
            'max_players' => ['nullable', 'integer'],
        ]);

        $server->forceFill([
            'power_state' => $data['state'] === 'crashed' ? 'offline' : $data['state'],
            'cached_cpu' => (float) ($data['cpu'] ?? 0),
            'cached_memory' => (int) ($data['memory'] ?? 0),
            'cached_disk' => (int) ($data['disk'] ?? $server->cached_disk),
            'cached_players' => (int) ($data['players'] ?? 0),
            'cached_max_players' => (int) ($data['max_players'] ?? $server->cached_max_players),
            'cached_at' => now(),
            'last_crashed_at' => $data['state'] === 'crashed' ? now() : $server->last_crashed_at,
        ])->save();

        return response()->json(['ok' => true]);
    }
}
