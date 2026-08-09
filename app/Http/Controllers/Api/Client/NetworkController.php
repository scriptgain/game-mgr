<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\AllocationResource;
use App\Models\AuditLog;
use App\Models\Server;
use Illuminate\Http\Request;

/**
 * The addresses a server answers on.
 *
 * A customer can claim a spare port on their own node and choose which is
 * primary. They cannot invent one: the ports exist because an administrator
 * added them to the node, and this only ever moves one from free to taken.
 */
class NetworkController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'allocation.read');

        return $this->paginate($request, $server->allocations(), AllocationResource::class);
    }

    public function store(Server $server)
    {
        $this->guard($server, 'allocation.create');

        $limit = (int) $server->allocation_limit;
        if ($limit > 0 && $server->allocations()->count() >= $limit) {
            return response()->json([
                'message' => 'This server is allowed '.$limit.' addresses and already has that many.',
            ], 409);
        }

        // Same address as the primary, so a second port does not land the
        // server on an IP its players are not being told about.
        $free = $server->node->allocations()
            ->whereNull('server_id')
            ->when($server->allocation, fn ($q) => $q->where('ip', $server->allocation->ip))
            ->orderBy('port')
            ->first();

        if (! $free) {
            return response()->json(['message' => 'That node has no free port on this address.'], 409);
        }

        $free->update(['server_id' => $server->id]);
        AuditLog::record('allocation.create', 'Claimed '.$free->ip.':'.$free->port.' for "'.$server->name.'" over the API', $server, $server->id);

        return response()->json($this->one($free->fresh(), AllocationResource::class), 201);
    }

    public function primary(Server $server, $allocation)
    {
        $this->guard($server, 'allocation.update');

        $record = $server->allocations()->findOrFail($allocation);
        $server->forceFill(['allocation_id' => $record->id])->save();

        return response()->json([
            'object' => 'allocation',
            'attributes' => (new AllocationResource($record))->fields(),
            'meta' => ['note' => 'The server has to restart before players reach it here.'],
        ]);
    }

    public function destroy(Server $server, $allocation)
    {
        $this->guard($server, 'allocation.delete');

        $record = $server->allocations()->findOrFail($allocation);
        abort_if($record->id === $server->allocation_id, 409, 'That is the primary address. Make another one primary first.');

        $record->update(['server_id' => null, 'role' => null]);

        return $this->done();
    }
}
