<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\AuditLog;
use App\Models\Blueprint;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin-side server management: create, resize, suspend, reinstall, delete.
 *
 * The client area owns everything an end user does to a server they already
 * have. This controller owns everything only an operator may do.
 */
class ServerController extends Controller
{
    public function index(Request $request)
    {
        $query = Server::with(['owner', 'node.location', 'template.game', 'allocation']);

        if ($node = $request->query('node')) {
            $query->where('node_id', $node);
        }
        if ($runtime = $request->query('runtime')) {
            $query->where('runtime', $runtime);
        }
        if ($state = $request->query('state')) {
            match ($state) {
                'running' => $query->whereNull('status')->where('power_state', 'running'),
                'offline' => $query->whereNull('status')->where('power_state', '!=', 'running'),
                'attention' => $query->whereNotNull('status'),
                default => null,
            };
        }
        if ($search = $request->query('q')) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return view('admin.servers.index', [
            'title' => 'Servers',
            'servers' => $query->orderBy('name')->get(),
            'nodes' => Node::orderBy('name')->get(),
            'filters' => compact('node', 'runtime', 'state', 'search'),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.servers.form', [
            'title' => 'New Server',
            'server' => new Server([
                'memory' => config('gamemgr.default_memory', 2048),
                'disk' => config('gamemgr.default_disk', 10240),
                'cpu' => config('gamemgr.default_cpu', 200),
                'swap' => 0,
                'io' => 500,
                'database_limit' => 2,
                'allocation_limit' => 3,
                'backup_limit' => 5,
                'auto_restart' => true,
            ]),
            'users' => User::orderBy('name')->get(),
            'nodes' => Node::with('location')->orderBy('name')->get(),
            'templates' => Template::with('game')->orderBy('name')->get(),
            'blueprints' => Blueprint::with('template')->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = Template::findOrFail($data['template_id']);

        $node = $this->resolveNode($data, $template);
        $allocation = $this->resolveAllocation($data, $node);

        $server = Server::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'owner_id' => $data['owner_id'],
            'node_id' => $node->id,
            'template_id' => $template->id,
            'allocation_id' => $allocation?->id,
            // Copied off the template, never read through it: editing a
            // template later must not re-point a running server.
            'runtime' => $template->runtime,
            'image' => $data['image'] ?: $template->defaultImage(),
            'startup' => $data['startup'] ?: $template->startup,
            'memory' => $data['memory'],
            'swap' => $data['swap'],
            'disk' => $data['disk'],
            'io' => $data['io'],
            'cpu' => $data['cpu'],
            'database_limit' => $data['database_limit'],
            'allocation_limit' => $data['allocation_limit'],
            'backup_limit' => $data['backup_limit'],
            'auto_restart' => (bool) ($data['auto_restart'] ?? true),
            'auto_update' => (bool) ($data['auto_update'] ?? false),
            'status' => 'installing',
        ]);

        $allocation?->update(['server_id' => $server->id]);

        foreach ($template->variables as $var) {
            ServerVariable::create([
                'server_id' => $server->id,
                'template_variable_id' => $var->id,
                'value' => $var->default_value,
            ]);
        }

        AuditLog::record('server.create', 'Created server "'.$server->name.'"', $server, $server->id);

        return redirect()->route('admin.servers.show', $server)
            ->with('status', 'Server created. It will install on '.$node->name.'.');
    }

    public function show(Server $server)
    {
        return view('admin.servers.show', [
            'title' => $server->name,
            'server' => $server->load(['owner', 'node.location', 'template.game', 'allocation', 'subusers.user', 'backups', 'databases']),
        ]);
    }

    public function edit(Server $server)
    {
        return view('admin.servers.form', [
            'title' => 'Edit '.$server->name,
            'server' => $server,
            'users' => User::orderBy('name')->get(),
            'nodes' => Node::with('location')->orderBy('name')->get(),
            'templates' => Template::with('game')->orderBy('name')->get(),
            'blueprints' => Blueprint::with('template')->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Server $server)
    {
        $data = $this->validated($request, $server);

        $server->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'owner_id' => $data['owner_id'],
            'image' => $data['image'] ?: $server->image,
            'startup' => $data['startup'] ?: $server->startup,
            'memory' => $data['memory'],
            'swap' => $data['swap'],
            'disk' => $data['disk'],
            'io' => $data['io'],
            'cpu' => $data['cpu'],
            'database_limit' => $data['database_limit'],
            'allocation_limit' => $data['allocation_limit'],
            'backup_limit' => $data['backup_limit'],
            'auto_restart' => (bool) ($data['auto_restart'] ?? false),
            'auto_update' => (bool) ($data['auto_update'] ?? false),
        ]);

        AuditLog::record('server.update', 'Updated server "'.$server->name.'"', $server, $server->id);

        return redirect()->route('admin.servers.show', $server)->with('status', 'Server updated.');
    }

    public function destroy(Server $server)
    {
        $name = $server->name;

        // Free the ports first. Cascade would take the allocation rows with it,
        // and those belong to the node, not the server.
        Allocation::where('server_id', $server->id)->update(['server_id' => null]);
        $server->delete();

        AuditLog::record('server.delete', 'Deleted server "'.$name.'"');

        return redirect()->route('admin.servers.index')->with('status', 'Server deleted.');
    }

    // ------------------------------------------------------------- lifecycle

    public function suspend(Server $server)
    {
        $server->update(['status' => 'suspended', 'stopped_intentionally' => true]);
        AuditLog::record('server.suspend', 'Suspended "'.$server->name.'"', $server, $server->id);

        return back()->with('status', 'Server suspended. Files are untouched.');
    }

    public function unsuspend(Server $server)
    {
        $server->update(['status' => null, 'stopped_intentionally' => false]);
        AuditLog::record('server.unsuspend', 'Unsuspended "'.$server->name.'"', $server, $server->id);

        return back()->with('status', 'Server unsuspended.');
    }

    public function reinstall(Server $server)
    {
        $server->update(['status' => 'installing', 'installed_at' => null, 'stopped_intentionally' => false]);
        AuditLog::record('server.reinstall', 'Queued a reinstall of "'.$server->name.'"', $server, $server->id);

        return back()->with('status', 'Reinstall queued. Server files are replaced, the data directory is kept.');
    }

    // ------------------------------------------------------------- internals

    /**
     * Auto placement picks the emptiest public node in the chosen location that
     * both supports the template's runtime and has the room. Pterodactyl makes
     * you work this out yourself every single time.
     */
    private function resolveNode(array $data, Template $template): Node
    {
        if (! empty($data['node_id'])) {
            $node = Node::findOrFail($data['node_id']);
            if (! $node->supports($template->runtime)) {
                throw ValidationException::withMessages([
                    'node_id' => $node->name.' cannot run '.$template->runtimeLabel().' templates.',
                ]);
            }

            return $node;
        }

        $candidates = Node::query()
            ->where('public', true)
            ->where('maintenance_mode', false)
            ->when(! empty($data['location_id']), fn ($q) => $q->where('location_id', $data['location_id']))
            ->get()
            ->filter(fn (Node $n) => $n->supports($template->runtime))
            ->filter(fn (Node $n) => $n->hasRoomFor((int) $data['memory'], (int) $data['disk']))
            ->sortBy(fn (Node $n) => $n->memoryPressure());

        $node = $candidates->first();

        if (! $node) {
            throw ValidationException::withMessages([
                'node_id' => 'No node has room for that. Pick one by hand, free up capacity, or raise an over-allocation percentage.',
            ]);
        }

        return $node;
    }

    private function resolveAllocation(array $data, Node $node): ?Allocation
    {
        if (! empty($data['allocation_id'])) {
            $allocation = Allocation::find($data['allocation_id']);
            if ($allocation && $allocation->node_id === $node->id && ! $allocation->isAssigned()) {
                return $allocation;
            }
        }

        return $node->allocations()->whereNull('server_id')->orderBy('port')->first();
    }

    private function validated(Request $request, ?Server $server = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['required', 'exists:users,id'],
            'template_id' => ['required', 'exists:templates,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'node_id' => ['nullable', 'exists:nodes,id'],
            'allocation_id' => ['nullable', 'exists:allocations,id'],
            'image' => ['nullable', 'string', 'max:255'],
            'startup' => ['nullable', 'string', 'max:2000'],
            'memory' => ['required', 'integer', 'min:0'],
            'swap' => ['required', 'integer', 'min:-1'],
            'disk' => ['required', 'integer', 'min:0'],
            'io' => ['required', 'integer', 'between:10,1000'],
            'cpu' => ['required', 'integer', 'min:0'],
            'database_limit' => ['required', 'integer', 'between:0,50'],
            'allocation_limit' => ['required', 'integer', 'between:0,50'],
            'backup_limit' => ['required', 'integer', 'between:0,200'],
            'auto_restart' => ['nullable', 'boolean'],
            'auto_update' => ['nullable', 'boolean'],
        ]);
    }
}
