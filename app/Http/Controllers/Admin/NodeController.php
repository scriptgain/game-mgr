<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeMetric;
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

    public function store(Request $request)
    {
        $node = Node::create($this->validated($request));
        $this->issueEnrollToken($node);

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

    public function update(Request $request, Node $node)
    {
        $node->update($this->validated($request, $node));

        return redirect()->route('admin.nodes.show', $node)->with('status', 'Node updated.');
    }

    public function destroy(Node $node)
    {
        if ($node->servers()->exists()) {
            return back()->with('error', 'That node still hosts servers. Move or delete them first.');
        }

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

    public function allocations(Node $node)
    {
        return view('admin.nodes.allocations', [
            'title' => $node->name.' Allocations',
            'node' => $node,
            'allocations' => $node->allocations()->with('server')->orderBy('ip')->orderBy('port')->paginate(100),
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

        return view('admin.nodes.metrics', [
            'title' => $node->name.' Metrics',
            'node' => $node,
            'series' => NodeMetric::where('node_id', $node->id)
                ->where('sampled_at', '>=', $since)
                ->orderBy('sampled_at')
                ->get(['sampled_at', 'cpu', 'memory', 'disk', 'load']),
        ]);
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
            'daemon_port' => ['required', 'integer', 'between:1,65535'],
            'sftp_port' => ['required', 'integer', 'between:1,65535'],
            'memory' => ['required', 'integer', 'min:0'],
            'memory_overallocate' => ['required', 'integer', 'between:0,500'],
            'disk' => ['required', 'integer', 'min:0'],
            'disk_overallocate' => ['required', 'integer', 'between:0,500'],
            'cpu' => ['required', 'integer', 'min:0'],
            'upload_size' => ['required', 'integer', 'between:1,4096'],
            // Posted as a map by the toggles: runtimes[docker] => "1".
            'runtimes' => ['required', 'array'],
            'daemon_base' => ['required', 'string', 'max:255'],
            'public' => ['nullable', 'boolean'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'behind_proxy' => ['nullable', 'boolean'],
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
