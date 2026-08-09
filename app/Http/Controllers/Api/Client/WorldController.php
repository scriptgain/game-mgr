<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\WorldResource;
use App\Models\Server;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * World for one server, as its owner sees them.
 *
 * Guarded by world.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class WorldController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'world.read');

        return $this->paginate($request, $server->worlds(), WorldResource::class);
    }

    /** Switching worlds needs a restart to take effect, and says so. */
    public function activate(Server $server, $world)
    {
        $this->guard($server, 'world.switch');
        $this->refuseIfSuspended($server);

        $record = $server->worlds()->findOrFail($world);
        $server->worlds()->update(['is_active' => false]);
        $record->update(['is_active' => true]);

        AuditLog::record('world.switch', 'Switched "'.$server->name.'" to world "'.$record->name.'" over the API', $server, $server->id);

        return response()->json([
            'object' => 'world',
            'attributes' => (new WorldResource($record->fresh()))->fields(),
            'meta' => ['note' => 'The server has to restart before this takes effect.'],
        ]);
    }

    public function destroy(Server $server, $world)
    {
        $this->guard($server, 'world.delete');

        $record = $server->worlds()->findOrFail($world);
        abort_if($record->is_active, 409, 'That is the active world. Switch to another one first.');

        $record->delete();

        return $this->done();
    }
}
