<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\WorldResource;
use App\Models\Server;
use App\Models\AuditLog;
use App\Services\NodeClient;
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

    /**
     * Upload a world archive and unpack it.
     *
     * world.upload has been a permission with nothing implementing it since the
     * beginning. It goes up as a raw body for the same reason files do, lands
     * inside the server's own directory, and is unpacked by the node, which
     * refuses any entry that would escape.
     *
     * The world is not made active. Somebody uploading a world usually wants to
     * look at it before their players are standing in it.
     */
    public function upload(Request $request, Server $server)
    {
        $this->guard($server, 'world.upload');
        $this->refuseIfSuspended($server);

        $name = trim((string) $request->query('name', ''));
        abort_if($name === '', 422, 'Name the world.');
        abort_unless(preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/', $name), 422,
            'Use letters, numbers, spaces, dots, dashes and underscores.');

        $archive = 'worlds/'.$name.'.tar.gz';
        $client = NodeClient::for($server->node);

        $upload = $client->upload(
            $server,
            $archive,
            $request->getContent(true),
            (int) ($server->node->upload_size ?? 4096) * 1024 * 1024,
        );

        if (! ($upload['ok'] ?? false)) {
            return response()->json(['message' => $upload['error'] ?? 'The node refused the upload.'], 422);
        }

        if (! $client->extract($server, $archive)) {
            return response()->json([
                'message' => 'The world was uploaded but could not be unpacked. It has to be a .zip, .tar or .tar.gz.',
            ], 502);
        }

        $world = $server->worlds()->updateOrCreate(
            ['name' => $name],
            ['path' => 'worlds/'.$name, 'is_active' => false, 'bytes' => $upload['bytes'] ?? 0],
        );

        AuditLog::record('world.upload', 'Uploaded world "'.$name.'" to "'.$server->name.'" over the API', $server, $server->id);

        return response()->json([
            'object' => 'world',
            'attributes' => (new WorldResource($world->fresh()))->fields(),
            'meta' => ['note' => 'Uploaded but not activated. Switch to it when you are ready.'],
        ], 201);
    }
}
