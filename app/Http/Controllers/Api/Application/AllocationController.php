<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AllocationResource;
use App\Models\Allocation;
use App\Models\AuditLog;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The ports a node has, and which of them are spoken for.
 *
 * This was the worst gap in the API: nothing could see which ports a node had
 * free, so anything managing capacity programmatically was blind, and adding a
 * port range meant a human on the allocations screen.
 *
 * The rules match the web screen deliberately, including the 500 port ceiling
 * on one range: the panel's version exists because somebody asked for
 * 1024-65535 in one go and made 64,000 rows.
 */
class AllocationController extends ApiController
{
    public function index(Request $request, Node $node)
    {
        $query = $node->allocations()
            ->when($request->query('ip'), fn ($q, $ip) => $q->where('ip', $ip))
            ->when($request->boolean('free'), fn ($q) => $q->whereNull('server_id'))
            ->when($request->boolean('assigned'), fn ($q) => $q->whereNotNull('server_id'))
            ->orderBy('ip')
            ->orderBy('port');

        return $this->paginate($request, $query, AllocationResource::class);
    }

    public function show(Node $node, Allocation $allocation)
    {
        $this->assertBelongs($node, $allocation);

        return $this->one($allocation, AllocationResource::class);
    }

    /**
     * Add a range of ports.
     *
     * firstOrCreate rather than insert, so asking for a range that overlaps one
     * a node already has adds the missing ports instead of failing on the first
     * duplicate. Re-running the same call is a no-op, which is what anything
     * automating capacity wants.
     */
    public function store(Request $request, Node $node)
    {
        $data = $request->validate(static::rules('store'));

        $span = $data['port_end'] - $data['port_start'] + 1;
        if ($span > 500) {
            return response()->json([
                'message' => 'That is '.$span.' ports in one go. Keep a range to 500 or fewer.',
            ], 422);
        }

        $made = 0;
        for ($port = $data['port_start']; $port <= $data['port_end']; $port++) {
            $created = Allocation::firstOrCreate(
                ['node_id' => $node->id, 'ip' => $data['ip'], 'port' => $port],
                ['ip_alias' => $data['ip_alias'] ?? null],
            );
            if ($created->wasRecentlyCreated) {
                $made++;
            }
        }

        AuditLog::record('node.allocations',
            'Added '.$made.' '.Str::plural('allocation', $made).' to "'.$node->name.'" over the API', $node);

        return response()->json([
            'object' => 'list',
            'data' => AllocationResource::collection(
                $node->allocations()->where('ip', $data['ip'])
                    ->whereBetween('port', [$data['port_start'], $data['port_end']])
                    ->orderBy('port')->get()
            )->resolve(),
            'meta' => ['created' => $made, 'existing' => $span - $made],
        ], 201);
    }

    /**
     * Free a port, or refuse because a server is on it.
     *
     * Deleting an allocation a server is using would take the row out from
     * under a running game, which then answers on a port the panel no longer
     * believes it owns and which the firewall will close on the next change.
     */
    public function destroy(Node $node, Allocation $allocation)
    {
        $this->assertBelongs($node, $allocation);

        if ($allocation->server_id !== null) {
            return response()->json([
                'message' => 'That port is assigned to a server. Move the server off it first.',
                'server_id' => $allocation->server_id,
            ], 409);
        }

        $allocation->delete();
        AuditLog::record('node.allocations', 'Removed an allocation from "'.$node->name.'" over the API', $node);

        return $this->done();
    }

    /**
     * A node route carrying an allocation id must prove the two belong together,
     * or the id alone would reach any allocation on any node.
     */
    private function assertBelongs(Node $node, Allocation $allocation): void
    {
        abort_unless($allocation->node_id === $node->id, 404, 'That allocation is not on this node.');
    }

    /**
     * The request body for each write action, in one place so the API
     * reference can describe it rather than admitting it cannot.
     *
     * Static and public because two callers need it: validation here, and the
     * OpenAPI document, which would otherwise have to parse this file. The
     * subject is the record being acted on, for rules that must ignore it.
     *
     * @return array<string,mixed>
     */
    public static function rules(string $action = 'store', mixed $subject = null): array
    {
        return match ($action) {
            'store' => [
                'ip' => ['required', 'ip'],
                'ip_alias' => ['nullable', 'string', 'max:255'],
                'port_start' => ['required', 'integer', 'between:1024,65535'],
                'port_end' => ['required', 'integer', 'between:1024,65535', 'gte:port_start'],
            ],
            default => [],
        };
    }
}
