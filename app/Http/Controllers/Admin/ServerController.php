<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\InstallServer;
use App\Models\Allocation;
use App\Models\AuditLog;
use App\Models\Blueprint;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\User;
use App\Services\NodeClient;
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
        $server = new Server([
            'memory' => config('gamemgr.default_memory', 2048),
            'disk' => config('gamemgr.default_disk', 10240),
            'cpu' => config('gamemgr.default_cpu', 200),
            'swap' => 0,
            'io' => 500,
            'database_limit' => 2,
            'allocation_limit' => 3,
            'backup_limit' => 5,
            'auto_restart' => true,
        ]);

        $users = User::orderBy('name')->get();
        $nodes = Node::with(['location'])->withCount('servers')->orderBy('name')->get();
        // Grouped by game on the picker, so the order is game first, then
        // template. Sorting here keeps the view a plain foreach.
        $templates = Template::with(['game', 'variables'])->get()
            ->sortBy(fn (Template $t) => mb_strtolower(($t->game?->name ?? 'zzz').' '.$t->name))
            ->values();
        // Smallest first: the size cards read as a ladder, and the recommended
        // one (the cheapest that fits) is where the eye already starts.
        $blueprints = Blueprint::with('template.game')->orderBy('name')->get()
            ->sortBy(fn (Blueprint $b) => [(int) ($b->limits['memory'] ?? 0), $b->name])
            ->values();
        $locations = Location::orderBy('name')->get();

        return view('admin.servers.create', [
            'title' => 'New Server',
            'server' => $server,
            'users' => $users,
            'nodes' => $nodes,
            'templates' => $templates,
            'blueprints' => $blueprints,
            'locations' => $locations,
            // Everything the wizard needs client side, as one JSON island. The
            // view stays markup and the behaviour stays in public/js.
            'wizard' => $this->wizardPayload($users, $nodes, $templates, $blueprints, $locations, $server),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = Template::with('variables')->findOrFail($data['template_id']);

        // Variables are validated against the template's own rules before
        // anything is written, so a bad value cannot leave a half-built server.
        $variableValues = $this->validatedVariables($request, $template);

        $node = $this->resolveNode($data, $template);
        $allocation = $this->resolveAllocation($data, $node, $template);

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
            // Both are nullable in the rules, so the key is absent entirely when
            // the request omits it rather than sending it empty. The browser form
            // always sends both, which is why this only 500s for an API caller.
            'image' => ($data['image'] ?? null) ?: $template->defaultImage(),
            'startup' => ($data['startup'] ?? null) ?: $template->startup,
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
                // The wizard posts a value for every variable it showed. An API
                // caller that posts none still gets the template defaults.
                'value' => array_key_exists($var->id, $variableValues)
                    ? $variableValues[$var->id]
                    : $var->default_value,
            ]);
        }

        AuditLog::record('server.create', 'Created server "'.$server->name.'"', $server, $server->id);

        // Without this the row sits at "installing" forever: the daemon is
        // never told to fetch anything and no game files ever arrive.
        InstallServer::dispatch($server->id);

        return redirect()->route('admin.servers.show', $server)
            ->with('status', 'Server created. It will install on '.$node->name.'.');
    }

    public function show(Server $server)
    {
        $server->load([
            'owner', 'node.location', 'template.game', 'allocation', 'allocations',
            'subusers.user', 'backups', 'databases', 'variables.variable', 'schedules',
        ]);

        $client = $server->node ? NodeClient::for($server->node) : null;

        // The admin page drives the same node endpoints the client console
        // does. An operator looking at a stuck install should not have to hop
        // into the customer view to read the reason.
        $backlog = $client ? $client->logs($server, 200) : [];

        // Mid install there is no game process to read logs from, so the useful
        // output is the install transcript the queue job is recording. Null
        // here whenever the install columns are not present yet.
        if (in_array($server->status, ['installing', 'install_failed'], true) && filled($server->install_log)) {
            $backlog = preg_split("/\r?\n/", trim((string) $server->install_log)) ?: [];
        }

        // Only paid for when it is the question on the screen. A server that is
        // mid install, or has failed one, is waiting on the node, so "is the
        // node answering at all" is the first thing to establish.
        //
        // Two checks, not one. /healthz needs no credential and a wrong daemon
        // token still passes it, which is exactly how a node that looks healthy
        // never installs anything: every authenticated call is a 401 and the
        // only symptom is a server that sits at "installing" forever.
        $nodeCheck = null;
        if ($client && in_array($server->status, ['installing', 'install_failed'], true)) {
            $alive = $client->ping();
            $nodeCheck = [
                'alive' => $alive,
                'authenticated' => $alive ? $client->system() !== null : false,
            ];
        }

        return view('admin.servers.show', [
            'title' => $server->name,
            'server' => $server,
            'backlog' => $backlog,
            'streamUrl' => $client?->streamUrl($server),
            'nodeCheck' => $nodeCheck,
            'memoryFloor' => $this->memoryFloor($server),
            'clientLinks' => $this->clientLinks($server),
        ]);
    }

    /**
     * The smallest memory figure anyone published for this template, as a
     * blueprint, plus which blueprint it came from.
     *
     * Templates carry no recommended_memory column, so the only statement of
     * "this game needs at least X" that exists in the data is the set of
     * blueprints built on the template. That is a weaker source than a column
     * would be, but it is real: a Palworld server created without picking a
     * blueprint gets the panel default of 2 GiB, the cgroup writes that as a
     * hard memory.max with swap off, and the world load is OOM killed. Saying
     * so on the page is worth more than saying nothing until it dies.
     */
    private function memoryFloor(Server $server): ?array
    {
        if (! $server->template_id) {
            return null;
        }

        $floor = Blueprint::where('template_id', $server->template_id)->get()
            ->map(fn (Blueprint $b) => ['name' => $b->name, 'memory' => (int) ($b->limits['memory'] ?? 0)])
            ->filter(fn (array $b) => $b['memory'] > 0)
            ->sortBy('memory')
            ->first();

        if (! $floor || (int) $server->memory >= $floor['memory']) {
            return null;
        }

        return $floor;
    }

    /**
     * One click from the admin page to the real tools, which all live in the
     * client area. Entries the server cannot use are dropped rather than shown
     * as links to an empty tab.
     */
    private function clientLinks(Server $server): array
    {
        $template = $server->template;

        return array_values(array_filter([
            ['label' => 'Console', 'route' => 'server.console', 'icon' => 'terminal', 'show' => true],
            ['label' => 'Files', 'route' => 'server.files', 'icon' => 'folder', 'show' => true],
            ['label' => 'Backups', 'route' => 'server.backups', 'icon' => 'archive', 'show' => $server->backup_limit > 0],
            ['label' => 'Databases', 'route' => 'server.databases', 'icon' => 'database', 'show' => $server->database_limit > 0],
            ['label' => 'Schedules', 'route' => 'server.schedules', 'icon' => 'clock', 'show' => true],
            ['label' => 'Players', 'route' => 'server.players', 'icon' => 'user-group', 'show' => (bool) ($template?->rcon_supported || $template?->query_protocol)],
            ['label' => 'Mods', 'route' => 'server.mods', 'icon' => 'puzzle', 'show' => (bool) $template?->supportsMods()],
            ['label' => 'Worlds', 'route' => 'server.worlds', 'icon' => 'map', 'show' => true],
            ['label' => 'Network', 'route' => 'server.network', 'icon' => 'network', 'show' => true],
            ['label' => 'Metrics', 'route' => 'server.metrics', 'icon' => 'chart', 'show' => true],
            ['label' => 'Startup', 'route' => 'server.startup', 'icon' => 'bolt', 'show' => true],
            ['label' => 'Activity', 'route' => 'server.activity', 'icon' => 'book', 'show' => true],
        ], fn (array $link) => $link['show']));
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

        InstallServer::dispatch($server->id);

        return back()->with('status', 'Reinstall queued. Server files are replaced, the data directory is kept.');
    }

    // --------------------------------------------------------------- wizard

    /**
     * Which wizard step owns each posted field. The create screen is stepped,
     * so a validation failure has to be able to say "step 2", not just "there
     * is an error somewhere on this page".
     */
    private const STEP_FIELDS = [
        'template_id' => 1,
        'image' => 1,
        'startup' => 1,
        'location_id' => 2,
        'node_id' => 2,
        'allocation_id' => 2,
        'name' => 3,
        'description' => 3,
        'owner_id' => 3,
        'memory' => 4,
        'swap' => 4,
        'disk' => 4,
        'io' => 4,
        'cpu' => 4,
        'database_limit' => 4,
        'allocation_limit' => 4,
        'backup_limit' => 4,
        'auto_restart' => 4,
        'auto_update' => 4,
    ];

    /** Every field the last POST rejected, in the browser's own key form. */
    private function failedFields(): array
    {
        $bag = session('errors');

        return $bag ? array_keys($bag->getBag('default')->messages()) : [];
    }

    /**
     * Did the last POST fail on a variable the template keeps to itself? Those
     * sit in a collapsed panel, and a message nobody can see is no message.
     */
    private function lockedVariableFailed($templates): bool
    {
        $failed = [];

        foreach ($this->failedFields() as $key) {
            if (str_starts_with($key, 'variables.')) {
                $failed[] = (int) substr($key, strlen('variables.'));
            }
        }

        if (! $failed) {
            return false;
        }

        return $templates->flatMap(fn (Template $t) => $t->variables)
            ->contains(fn (TemplateVariable $v) => ! $v->user_editable && in_array($v->id, $failed, true));
    }

    /** The earliest step carrying an error from the last POST, or step 1. */
    private function stepForErrors(): int
    {
        $bag = session('errors');

        if (! $bag) {
            return 1;
        }

        $steps = [];

        foreach (array_keys($bag->getBag('default')->messages()) as $key) {
            if (str_starts_with($key, 'variables.')) {
                $steps[] = 5;

                continue;
            }
            if (isset(self::STEP_FIELDS[$key])) {
                $steps[] = self::STEP_FIELDS[$key];
            }
        }

        return $steps ? min($steps) : 1;
    }

    /**
     * Everything the stepped create form needs in the browser: the allocations
     * that belong to each node, the variables each template exposes, and the
     * limits each blueprint presets. Rendered once as a JSON island rather than
     * as thousands of hidden inputs.
     */
    private function wizardPayload($users, $nodes, $templates, $blueprints, $locations, Server $defaults): array
    {
        $free = Allocation::whereNull('server_id')
            ->orderBy('ip')->orderBy('port')->get()
            ->groupBy('node_id');

        return [
            // A rejected POST must reopen on the step that actually failed, not
            // on step one with the message scrolled out of sight.
            'step' => $this->stepForErrors(),

            // Which fields failed, so a disclosure holding one of them opens
            // rather than hiding the message that came back with it.
            'errors' => $this->failedFields(),
            'open_locked' => $this->lockedVariableFailed($templates),

            'selected' => [
                'template_id' => old('template_id', $templates->first()?->id),
                'node_id' => old('node_id'),
                'allocation_id' => old('allocation_id'),
                'location_id' => old('location_id'),
                'owner_id' => old('owner_id', $users->first()?->id),
            ],

            // Seeds for the controls the browser owns. Every one of these is
            // x-model bound, so the state and the posted value can never drift,
            // and a rejected POST comes back with what was typed.
            'values' => [
                'name' => (string) old('name', ''),
                'description' => (string) old('description', ''),
                'image' => (string) old('image', ''),
                'startup' => (string) old('startup', ''),
                'memory' => (int) old('memory', $defaults->memory),
                'disk' => (int) old('disk', $defaults->disk),
                'cpu' => (int) old('cpu', $defaults->cpu),
                'swap' => (int) old('swap', $defaults->swap),
                'io' => (int) old('io', $defaults->io),
                'database_limit' => (int) old('database_limit', $defaults->database_limit),
                'allocation_limit' => (int) old('allocation_limit', $defaults->allocation_limit),
                'backup_limit' => (int) old('backup_limit', $defaults->backup_limit),
            ],

            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all(),

            'locations' => $locations->map(fn (Location $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'flag' => $l->flag,
            ])->values()->all(),

            'templates' => $templates->map(fn (Template $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'game' => $t->game?->name,
                'game_id' => $t->game_id,
                'description' => $t->description,
                'runtime' => $t->runtime,
                'runtime_label' => $t->runtimeLabel(),
                'default_image' => $t->defaultImage(),
                'startup' => $t->startup,
                // Names and ids only: the inputs themselves are rendered by
                // Blade, one hidden block per template, so every control can be
                // the right shape for the rule it enforces.
                'variables' => $t->variables->map(fn (TemplateVariable $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'env' => $v->env_variable,
                    'editable' => (bool) $v->user_editable,
                ])->values()->all(),
            ])->values()->all(),

            'blueprints' => $blueprints->map(fn (Blueprint $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'description' => $b->description,
                'template_id' => $b->template_id,
                'summary' => $b->summary(),
                'limits' => $b->limits ?? [],
                'features' => $b->feature_limits ?? [],
                'environment' => $b->environment ?? [],
            ])->values()->all(),

            // Enough capacity detail for the browser to answer "will this fit"
            // while the operator is still dragging the memory slider, rather
            // than after a rejected POST.
            'nodes' => $nodes->map(fn (Node $n) => [
                'id' => $n->id,
                'name' => $n->name,
                'location' => $n->location?->name,
                'location_id' => $n->location_id,
                'runtimes' => array_values($n->runtimes ?? []),
                'pressure' => $n->memoryPressure(),
                'maintenance' => (bool) $n->maintenance_mode,
                'public' => (bool) $n->public,
                'servers' => (int) ($n->servers_count ?? 0),
                'memory_capacity' => $n->memoryCapacity(),
                'memory_used' => $n->memoryAllocated(),
                'disk_capacity' => $n->diskCapacity(),
                'disk_used' => $n->diskAllocated(),
                'allocations' => collect($free->get($n->id, []))
                    ->map(fn (Allocation $a) => [
                        'id' => $a->id,
                        'label' => $a->address(),
                        'notes' => $a->notes,
                    ])->values()->all(),
            ])->values()->all(),
        ];
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

    private function resolveAllocation(array $data, Node $node, ?Template $template = null): ?Allocation
    {
        if (! empty($data['allocation_id'])) {
            $allocation = Allocation::find($data['allocation_id']);
            if ($allocation && $allocation->node_id === $node->id && ! $allocation->isAssigned()) {
                return $allocation;
            }
        }

        $free = $node->allocations()->whereNull('server_id');

        // The game's own port first. Picking the lowest free port on the node
        // put a Palworld server on 2456, which is Valheim's, purely because the
        // bootstrap seeds one allocation per catalogue default and 2456 sorts
        // first. Players then have to be told a port for a game that has a
        // perfectly good default, and the node installer's firewall rules,
        // which are written around those defaults, do not cover it.
        if ($template?->default_port) {
            $preferred = (clone $free)->where('port', $template->default_port)->first();
            if ($preferred) {
                return $preferred;
            }
        }

        return $free->orderBy('port')->first();
    }

    /**
     * Template variables posted alongside the server, keyed by variable id.
     * Only variables this template actually owns are kept, and each is checked
     * against the rules the template declares for it.
     */
    private function validatedVariables(Request $request, Template $template): array
    {
        $submitted = (array) $request->input('variables', []);
        $rules = [];
        $labels = [];
        $values = [];

        foreach ($template->variables as $var) {
            $labels['variables.'.$var->id] = $var->name;

            if (! array_key_exists($var->id, $submitted)) {
                continue;
            }

            $rules['variables.'.$var->id] = $var->ruleArray();
            $values[$var->id] = (string) $submitted[$var->id];
        }

        if ($rules) {
            $request->validate($rules, [], $labels);
        }

        return $values;
    }

    private function validated(Request $request, ?Server $server = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['required', 'exists:users,id'],
            // The edit form disables the template select, because a running
            // server cannot be re-pointed, and a disabled control posts nothing.
            // Requiring it there failed every single save with "the template id
            // field is required" on a field the operator could not even reach.
            'template_id' => $server
                ? ['nullable', 'exists:templates,id']
                : ['required', 'exists:templates,id'],
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
