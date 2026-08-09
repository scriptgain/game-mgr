<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\ModResource;
use App\Models\Server;
use Illuminate\Http\Request;

/**
 * Mod for one server, as its owner sees them.
 *
 * Guarded by mod.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class ModController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'mod.read');

        return $this->paginate($request, $server->mods(), ModResource::class);
    }

    public function toggle(Server $server, $mod)
    {
        $this->guard($server, 'mod.update');

        $record = $server->mods()->findOrFail($mod);
        $record->update(['enabled' => ! $record->enabled]);

        return response()->json([
            'object' => 'mod',
            'attributes' => (new ModResource($record->fresh()))->fields(),
            'meta' => ['note' => 'The server has to restart before this takes effect.'],
        ]);
    }

    public function destroy(Server $server, $mod)
    {
        $this->guard($server, 'mod.delete');

        $server->mods()->findOrFail($mod)->delete();

        return $this->done();
    }
}
