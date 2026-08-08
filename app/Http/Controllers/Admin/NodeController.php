<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeMetric;
use App\Services\AllocationPlanner;
use App\Services\Dns\WildcardManager;
use App\Services\NodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nodes are the machines game servers run on, and they can be anywhere: a VPS,
 * a dedicated box, a Proxmox VM, a NUC in a cupboard. This controller is the
 * whole lifecycle: create the row, hand out an enroll one-liner, watch the
 * daemon report in, carve up its ports, and eventually drain it.
 */
class NodeController extends Controller
{
    public function index()
    {
        $nodes = Node::with('location')
            ->withCount(['servers', 'allocations'])
            ->orderBy('name')
            ->get();

        return view('admin.nodes.index', [
            'title' => 'Nodes',
            'nodes' => $nodes,
        ]);
    }

    public function create()
    {
        return view('admin.nodes.form', [
            'title' => 'New Node',
            'node' => new Node(['runtimes' => ['docker'], 'daemon_port' => config('node.default_port'), 'sftp_port' => 2022, 'scheme' => 'https', 'connection_mode' => 'direct', 'public' => true]),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, WildcardManager $wildcards)
    {
        $node = Node::create($this->validated($request));
        $this->issueEnrollToken($node);

        // Best effort, and it cannot fail into an error page: the manager
        // records what happened on the node and the hourly sync repairs it.
        $wildcards->sync($node);

        return redirect()->route('admin.nodes.enroll', $node)
            ->with('status', 'Node created. Run the command below on the machine to enroll it.');
    }

    public function show(Node $node)
    {
        $node->loadCount(['servers', 'allocations']);

        return view('admin.nodes.show', [
            'title' => $node->name,
            'node' => $node,
            'servers' => $node->servers()->with(['owner', 'template.game', 'allocation'])->orderBy('name')->get(),
            'freePorts' => $node->allocations()->whereNull('server_id')->count(),
            // Live daemon answer, or null when the node is not reachable. The
            // page renders either way: a node being down must never 500 the
            // screen you would use to find out why.
            'system' => NodeClient::for($node)->system(),
        ]);
    }

    public function edit(Node $node)
    {
        return view('admin.nodes.form', [
            'title' => 'Edit '.$node->name,
            'node' => $node,
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Node $node, WildcardManager $wildcards)
    {
        $previousLabel = $node->dns_label;
        $node->update($this->validated($request, $node));

        // A node's label is the middle of every name on it, so changing it
        // renames every server here and orphans the old wildcard.
        if ($previousLabel !== $node->dns_label) {
            if (filled($previousLabel)) {
                $wildcards->remove((new Node)->forceFill(['dns_label' => $previousLabel]));
            }
            $wildcards->sync($node);
        }

        return redirect()->route('admin.nodes.show', $node)->with('status', 'Node updated.');
    }

    public function destroy(Node $node, WildcardManager $wildcards)
    {
        if ($node->servers()->exists()) {
            return back()->with('error', 'That node still hosts servers. Move or delete them first.');
        }

        // Before the row goes, or there is nothing left to build the name from.
        $wildcards->remove($node);

        $node->delete();

        return redirect()->route('admin.nodes.index')->with('status', 'Node deleted.');
    }

    // ---------------------------------------------------------------- enroll

    public function enroll(Node $node)
    {
        if (! $node->enroll_token || $node->enroll_token_expires_at?->isPast()) {
            $this->issueEnrollToken($node);
        }

        return view('admin.nodes.enroll', [
            'title' => 'Enroll '.$node->name,
            'node' => $node,
            'command' => $this->enrollCommand($node),
        ]);
    }

    public function regenerateEnroll(Node $node)
    {
        $this->issueEnrollToken($node);

        return back()->with('status', 'A fresh enroll token was issued. The previous one no longer works.');
    }

    /**
     * The one-liner an operator pastes onto the box. The token is short-lived
     * and single use: all it buys the daemon is its long-lived credential, so a
     * token leaking off a support ticket is not a compromise.
     */
    private function enrollCommand(Node $node): string
    {
        return sprintf(
            'curl -fsSL %s/install/node | sudo bash -s -- --panel %s --token %s',
            rtrim(config('app.url'), '/'),
            rtrim(config('app.url'), '/'),
            $node->enroll_token,
        );
    }

    private function issueEnrollToken(Node $node): void
    {
        $node->forceFill([
            'enroll_token' => Str::random(48),
            'enroll_token_expires_at' => now()->addSeconds((int) config('node.enroll_token_ttl', 3600)),
        ])->save();
    }

    // ---------------------------------------------------------- allocations

    public function allocations(Node $node, AllocationPlanner $planner)
    {
        return view('admin.nodes.allocations', [
            'title' => $node->name.' Allocations',
            'node' => $node,
            'allocations' => $node->allocations()->with('server')->orderBy('ip')->orderBy('port')->paginate(config('gamemgr.rows_per_page', 10)),
            // Which addresses are anybody's and which are one server's alone.
            // That distinction decides whether a game gets its real port, so it
            // belongs on the page where addresses are managed.
            'ips' => $planner->ipInventory($node),
        ]);
    }

    /**
     * Ports are created in ranges because that is how anyone actually thinks
     * about them: "27015 through 27030 on this address", not sixteen forms.
     */
    public function storeAllocations(Request $request, Node $node)
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'ip_alias' => ['nullable', 'string', 'max:255'],
            'port_start' => ['required', 'integer', 'between:1024,65535'],
            'port_end' => ['required', 'integer', 'between:1024,65535', 'gte:port_start'],
        ]);

        $span = $data['port_end'] - $data['port_start'] + 1;
        if ($span > 500) {
            return back()->with('error', 'That is '.$span.' ports in one go. Keep a range to 500 or fewer.');
        }

        $made = 0;
        for ($port = $data['port_start']; $port <= $data['port_end']; $port++) {
            $created = Allocation::firstOrCreate(
                ['node_id' => $node->id, 'ip' => $data['ip'], 'port' => $port],
                ['ip_alias' => $data['ip_alias']],
            );
            if ($created->wasRecentlyCreated) {
                $made++;
            }
        }

        return back()->with('status', $made.' '.Str::plural('allocation', $made).' added.');
    }

    public function destroyAllocation(Node $node, Allocation $allocation)
    {
        abort_unless($allocation->node_id === $node->id, 404);

        if ($allocation->server_id) {
            return back()->with('error', 'That port is in use by a server. Free it on the server first.');
        }

        $allocation->delete();

        return back()->with('status', 'Allocation removed.');
    }

    // -------------------------------------------------------------- metrics

    public function metrics(Node $node)
    {
        $since = now()->subDays(7);
        $window = NodeMetric::where('node_id', $node->id)->where('sampled_at', '>=', $since);

        // The headline figures are aggregated across the whole week rather than
        // read off the visible page. A node heartbeats every 30 seconds, so a
        // week is around twenty thousand rows: whichever rows happen to be on
        // screen cannot tell you the week's peak, and loading all of them to
        // find out was the reason this page carried the lot into memory and
        // then rendered the newest 48.
        $summary = (clone $window)
            // `load` is a reserved word in MariaDB, so it has to be quoted even
            // inside an aggregate. Unquoted it is a bare syntax error, and the
            // page 500s rather than degrading.
            ->selectRaw('MAX(cpu) as peak_cpu, MAX(memory) as peak_memory, MAX(disk) as peak_disk, MAX(`load`) as peak_load, COUNT(*) as samples')
            ->first();

        return view('admin.nodes.metrics', [
            'title' => $node->name.' Metrics',
            'node' => $node,
            'latest' => (clone $window)->orderByDesc('sampled_at')->first(),
            'summary' => $summary,
            'samples' => (clone $window)
                ->orderByDesc('sampled_at')
                ->paginate(config('gamemgr.rows_per_page', 10), ['sampled_at', 'cpu', 'memory', 'disk', 'load']),
        ]);
    }

    /**
     * Throw away this node's telemetry history.
     *
     * Housekeeping trims it nightly, but that is a retention window and not a
     * button: an operator who has just fixed a node does not want a week of the
     * old node's readings dragging the averages around. Deletes only this
     * node's rows, never anybody else's.
     */
    public function clearMetrics(Node $node)
    {
        $count = NodeMetric::where('node_id', $node->id)->count();
        NodeMetric::where('node_id', $node->id)->delete();

        AuditLog::record('node.metrics_cleared',
            'Cleared '.number_format($count).' '.Str::plural('metric sample', $count).' from "'.$node->name.'"', $node);

        return redirect()->route('admin.nodes.metrics', $node)
            ->with('status', number_format($count).' '.Str::plural('sample', $count).' deleted. New readings arrive on the next heartbeat.');
    }

    /** Poke the daemon on demand, so "is it back yet" has an answer button. */
    public function check(Node $node)
    {
        $client = NodeClient::for($node);
        $system = $client->system();

        if (! $system) {
            return back()->with('error', 'No answer from the daemon at '.$node->daemonUrl().'.');
        }

        $node->forceFill([
            'last_seen_at' => now(),
            'reported_agent_version' => $system['version'] ?? $node->reported_agent_version,
        ])->save();

        return back()->with('status', 'Daemon answered. Running version '.($system['version'] ?? 'unknown').'.');
    }

    // ------------------------------------------------------------------ dns

    /**
     * Put this node's wildcard record back, now.
     *
     * The sync never throws, so this button cannot fail into an error page: it
     * either confirms the record or comes back with the reason it could not,
     * which is the same message the Wildcard row shows.
     */
    public function syncWildcard(Node $node, WildcardManager $wildcards)
    {
        $status = $wildcards->sync($node);

        if ($status === WildcardManager::STATUS_ACTIVE) {
            return back()->with('status', 'Wildcard record confirmed: '.$node->wildcardName().' points at '.$node->dnsTargetIp().'.');
        }

        return back()->with('error', $node->wildcard_error ?: 'The wildcard record is not in place. See the Wildcard row for details.');
    }

    // ------------------------------------------------------------ internals

    private function validated(Request $request, ?Node $node = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'location_id' => ['required', 'exists:locations,id'],
            'connection_mode' => ['required', 'in:direct,reverse'],
            'scheme' => ['required', 'in:http,https'],
            'fqdn' => ['nullable', 'string', 'max:255'],
            // One DNS label, no dots: it is the middle piece of
            // alpha.lax1.play.example.com, not a hostname of its own. Unique
            // across nodes, or two nodes would answer for the same names.
            'dns_label' => [
                'nullable', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                \Illuminate\Validation\Rule::unique('nodes', 'dns_label')->ignore($node?->id),
            ],
            'daemon_port' => ['required', 'integer', 'between:1,65535'],
            'sftp_port' => ['required', 'integer', 'between:1,65535'],
            'memory' => ['required', 'integer', 'min:0'],
            'memory_overallocate' => ['required', 'integer', 'between:0,500'],
            'disk' => ['required', 'integer', 'min:0'],
            'disk_overallocate' => ['required', 'integer', 'between:0,500'],
            'cpu' => ['required', 'integer', 'min:0'],
            // nullable, not required: the form has always posted this, but it
            // was missing from the rules and so was silently discarded on the
            // way through. Anything older that posts without it still saves.
            'cpu_overallocate' => ['nullable', 'integer', 'between:0,500'],
            'upload_size' => ['required', 'integer', 'between:1,4096'],
            // Posted as a map by the toggles: runtimes[docker] => "1".
            'runtimes' => ['required', 'array'],
            'daemon_base' => ['required', 'string', 'max:255'],
            'public' => ['nullable', 'boolean'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'behind_proxy' => ['nullable', 'boolean'],
        ], [
            'dns_label.regex' => 'A DNS label is lowercase letters, numbers and hyphens, with no dots. For example lax1.',
        ]);

        // A direct-mode node has to be dialable, so it needs an address. A
        // reverse-mode node dials us, so demanding one would be nonsense.
        if (($data['connection_mode'] ?? null) === 'direct' && blank($data['fqdn'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fqdn' => 'A direct node needs a hostname or IP address the panel can reach.',
            ]);
        }

        foreach (['public', 'maintenance_mode', 'behind_proxy'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        // Fold the toggle map back into the list the model stores, keeping only
        // runtimes that actually exist. A node with none could never run
        // anything, so that is a validation failure rather than an empty array
        // saved quietly.
        $data['runtimes'] = array_values(array_filter(
            Node::RUNTIMES,
            fn (string $runtime) => filter_var($data['runtimes'][$runtime] ?? false, FILTER_VALIDATE_BOOL),
        ));

        if ($data['runtimes'] === []) {
            throw ValidationException::withMessages([
                'runtimes' => 'Pick at least one runtime, or this node cannot run anything.',
            ]);
        }

        return $data;
    }
}
