<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\NodeMetric;
use App\Models\Server;
use Illuminate\Http\Request;
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
     * Exchange a single-use enrol token for the long-lived daemon credential.
     * The plaintext credential is returned exactly once and only its hash is
     * stored, so a database leak does not hand over live node access.
     */
    public function enrol(Request $request)
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

        $node = Node::where('enrol_token', $data['token'])->first();

        if (! $node || $node->enrol_token_expires_at?->isPast()) {
            return response()->json(['message' => 'That enrolment token is not valid or has expired.'], 401);
        }

        $plain = Str::random(64);

        // Enrolment is the one moment the panel learns what a node can actually
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
            'enrol_token' => null,
            'enrol_token_expires_at' => null,
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

        AuditLog::record('node.enrol', 'Node "'.$node->name.'" enrolled from '.$request->ip(), $node);

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
        ]);

        $node->forceFill([
            'last_seen_at' => now(),
            'reported_agent_version' => $data['agent_version'] ?? $node->reported_agent_version,
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
