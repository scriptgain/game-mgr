<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ServerResource;
use App\Jobs\InstallServer;
use App\Models\Allocation;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\Server;
use App\Models\Template;
use App\Jobs\MigrateServer;
use App\Services\AllocationPlanner;
use App\Services\ServerMigrator;
use App\Services\NodeClient;
use App\Support\Edition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Servers, for whatever is provisioning them. This is the half a billing system
 * drives: create, suspend, unsuspend, change the package, reinstall, terminate.
 *
 * Every action here shares the code the web screens use rather than
 * reimplementing it, so a server created by WHMCS is the same shape as one
 * created by hand, and a suspension means the same thing whichever asked for it.
 */
class ServerController extends Controller
{
    public function index(Request $request)
    {
        $servers = Server::query()
            ->when($request->query('node'), fn ($q, $node) => $q->where('node_id', $node))
            ->when($request->query('owner'), fn ($q, $owner) => $q->where('owner_id', $owner))
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->with(['owner', 'node', 'allocation'])
            ->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return ApiResource::list($servers, ServerResource::class);
    }

    public function show(Server $server)
    {
        return (new ServerResource($server->load(['owner', 'node', 'allocations'])))->toArray(request());
    }

    /**
     * Create a server, exactly as the admin screen does.
     *
     * Ports are planned before anything is written, so a set that cannot be
     * placed fails here rather than leaving a half-built server holding one
     * port and none of the others that make it usable.
     */
    public function store(Request $request, AllocationPlanner $planner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'owner_id' => ['required', 'exists:users,id'],
            'template_id' => ['required', 'exists:templates,id'],
            'node_id' => ['nullable', 'exists:nodes,id'],
            'memory' => ['required', 'integer', 'min:0'],
            'disk' => ['required', 'integer', 'min:0'],
            'cpu' => ['required', 'integer', 'min:0'],
            'swap' => ['nullable', 'integer'],
            'io' => ['nullable', 'integer'],
            'database_limit' => ['nullable', 'integer', 'min:0'],
            'allocation_limit' => ['nullable', 'integer', 'min:0'],
            'backup_limit' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'environment' => ['nullable', 'array'],
            'start_on_completion' => ['nullable', 'boolean'],
        ]);

        $template = Template::with(['variables', 'ports', 'game'])->findOrFail($data['template_id']);

        if (! Edition::roomForServer()) {
            return response()->json([
                'message' => 'The '.Edition::label().' edition covers '.Edition::limit('servers').' servers and this panel is at that limit.',
            ], 403);
        }
        if (! Edition::allowsTemplate($template)) {
            return response()->json([
                'message' => $template->game?->name.' is not included in the '.Edition::label().' edition.',
            ], 403);
        }

        $node = $data['node_id']
            ? Node::findOrFail($data['node_id'])
            : $this->firstNodeWithRoom($template, $data);

        if (! $node) {
            return response()->json([
                'message' => 'No node can take a server that size with that runtime.',
            ], 422);
        }

        $plan = $planner->plan($node, $template);
        if (! $plan) {
            return response()->json([
                'message' => 'That node has no free address that can take every port this template needs.',
            ], 422);
        }

        $server = DB::transaction(function () use ($data, $node, $template, $plan, $planner) {
            $server = Server::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id' => $data['owner_id'],
                'node_id' => $node->id,
                'template_id' => $template->id,
                'runtime' => $template->runtime,
                'image' => $template->defaultImage(),
                'startup' => $template->startup,
                'memory' => $data['memory'],
                'disk' => $data['disk'],
                'cpu' => $data['cpu'],
                'swap' => $data['swap'] ?? 0,
                'io' => $data['io'] ?? 500,
                'database_limit' => $data['database_limit'] ?? 0,
                'allocation_limit' => $data['allocation_limit'] ?? 0,
                'backup_limit' => $data['backup_limit'] ?? 0,
                'status' => 'installing',
            ]);

            // reserve returns the primary allocation, which is the one
            // carrying the game role. The server points at it rather than at
            // whichever row happened to be reserved first.
            $primary = $planner->reserve($server, $plan);
            if ($primary) {
                $server->forceFill(['allocation_id' => $primary->id])->save();
            }
            $this->applyVariables($server, $template, $data['environment'] ?? []);

            return $server;
        });

        AuditLog::record('server.create', 'Created server "'.$server->name.'" over the API', $server, $server->id);
        InstallServer::dispatch($server->id);

        return response()->json(
            (new ServerResource($server->fresh(['owner', 'node', 'allocations'])))->toArray($request),
            201,
        );
    }

    /** Change the package: limits, and the container that has to honour them. */
    public function build(Request $request, Server $server)
    {
        $data = $request->validate([
            'memory' => ['nullable', 'integer', 'min:0'],
            'disk' => ['nullable', 'integer', 'min:0'],
            'cpu' => ['nullable', 'integer', 'min:0'],
            'swap' => ['nullable', 'integer'],
            'io' => ['nullable', 'integer'],
            'database_limit' => ['nullable', 'integer', 'min:0'],
            'allocation_limit' => ['nullable', 'integer', 'min:0'],
            'backup_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $server->update(array_filter($data, fn ($v) => $v !== null));

        /*
         * A container carries the limits it was created with. Writing new
         * numbers to the row and stopping there meant a customer who upgraded
         * kept the old cgroup limits until something happened to recreate the
         * container, which could be weeks. Recreating it is the only way the
         * new memory actually applies, and it costs a restart, so it only
         * happens when the server is already running.
         */
        $restarted = false;
        if ($server->power_state === 'running') {
            $client = NodeClient::for($server->node);
            $client->power($server, 'stop');
            $client->power($server, 'start');
            $restarted = true;
        }

        AuditLog::record('server.build', 'Changed the package for "'.$server->name.'" over the API', $server, $server->id);

        return [
            'object' => 'server',
            'attributes' => (new ServerResource($server->fresh()))->fields(),
            'meta' => [
                'restarted' => $restarted,
                'note' => $restarted
                    ? 'The server was restarted so the new limits apply.'
                    : 'The new limits apply the next time this server starts.',
            ],
        ];
    }

    /** Stops the server, then blocks the panel. */
    public function suspend(Server $server)
    {
        if ($server->power_state !== 'offline') {
            NodeClient::for($server->node)->power($server, 'stop');
        }
        $server->update(['status' => 'suspended', 'stopped_intentionally' => true]);
        AuditLog::record('server.suspend', 'Suspended "'.$server->name.'" over the API', $server, $server->id);

        return response()->json(null, 204);
    }

    /** Unblocks. Deliberately does not start the server. */
    public function unsuspend(Server $server)
    {
        $server->update(['status' => null, 'stopped_intentionally' => false]);
        AuditLog::record('server.unsuspend', 'Unsuspended "'.$server->name.'" over the API', $server, $server->id);

        return response()->json(null, 204);
    }

    public function reinstall(Request $request, Server $server)
    {
        $wipe = $request->boolean('wipe');

        $server->update(['status' => 'installing', 'installed_at' => null, 'stopped_intentionally' => false]);
        AuditLog::record('server.reinstall',
            'Queued a reinstall of "'.$server->name.'" over the API'.($wipe ? ', wiping the data directory' : ''),
            $server, $server->id);

        InstallServer::dispatch($server->id, $wipe);

        return response()->json(null, 204);
    }

    /** Terminate: the node loses the container and the files, then the row goes. */
    public function destroy(Server $server)
    {
        $name = $server->name;
        $reached = NodeClient::for($server->node)->destroy($server);

        Allocation::where('server_id', $server->id)
            ->update(['server_id' => null, 'role' => null, 'protocol' => 'both']);
        $server->delete();

        AuditLog::record('server.delete', 'Deleted server "'.$name.'" over the API'
            .($reached ? '' : ' (the node was unreachable, so its files are still on disk)'));

        // 204 either way: the server is gone from the panel. The header says
        // whether the node confirmed, because a caller automating terminations
        // needs to know it has a node to go and tidy up.
        return response()->json(null, 204)->withHeaders([
            'X-GameMGR-Node-Confirmed' => $reached ? 'true' : 'false',
        ]);
    }

    /**
     * Move a server to another node.
     *
     * Answers immediately and does the work on the queue: copying a large world
     * takes far longer than a request should live. Everything that can be
     * checked is checked here, so an impossible migration is refused now rather
     * than failing silently in a worker ten minutes later.
     */
    public function transfer(Request $request, Server $server, ServerMigrator $migrator)
    {
        $data = $request->validate([
            'node_id' => ['required', 'exists:nodes,id'],
        ]);

        $target = Node::findOrFail($data['node_id']);

        if ($reason = $migrator->reasonItCannotRun($server, $target)) {
            return response()->json(['message' => $reason], 409);
        }

        MigrateServer::dispatch($server->id, $target->id);

        return response()->json([
            'object' => 'migration',
            'attributes' => [
                'server' => $server->uuid_short,
                'from' => $server->node->name,
                'to' => $target->name,
                'status' => 'queued',
            ],
            'meta' => [
                'note' => 'The server is offline for the transfer and its address will change. Its connection name follows automatically.',
            ],
        ], 202);
    }

    private function firstNodeWithRoom(Template $template, array $data): ?Node
    {
        return Node::where('public', true)
            ->where('maintenance_mode', false)
            ->get()
            ->first(fn (Node $node) => $node->supports($template->runtime)
                && $node->hasRoomFor((int) $data['memory'], (int) $data['disk'], (int) $data['cpu']));
    }

    private function applyVariables(Server $server, Template $template, array $environment): void
    {
        foreach ($template->variables as $variable) {
            $server->variables()->create([
                'template_variable_id' => $variable->id,
                'value' => $environment[$variable->env_variable] ?? $variable->default_value,
            ]);
        }
    }}
