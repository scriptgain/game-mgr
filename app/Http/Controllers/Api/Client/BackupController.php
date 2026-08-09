<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\BackupResource;
use App\Models\Server;
use App\Models\AuditLog;
use App\Services\NodeClient;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * Backup for one server, as its owner sees them.
 *
 * Guarded by backup.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class BackupController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'backup.read');

        return $this->paginate($request, $server->backups(), BackupResource::class);
    }

    /** Take a backup. The node does the work; this records it and asks. */
    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'backup.create');
        $this->refuseIfSuspended($server);

        $limit = (int) $server->backup_limit;
        if ($limit > 0 && $server->backups()->count() >= $limit) {
            return response()->json([
                'message' => 'This server keeps '.$limit.' backups and already has that many. Delete one first.',
            ], 409);
        }

        $backup = $server->backups()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $request->input('name') ?: 'Backup '.now()->format('Y-m-d H:i'),
            'is_successful' => false,
        ]);

        $result = NodeClient::for($server->node)->backup($server, $backup->uuid, (array) $request->input('ignore', []));

        $backup->forceFill([
            'bytes' => $result['bytes'] ?? 0,
            'checksum' => $result['checksum'] ?? null,
            'is_successful' => (bool) $result,
            'completed_at' => now(),
        ])->save();

        AuditLog::record('backup.create', 'Took a backup of "'.$server->name.'" over the API', $server, $server->id);

        return response()->json($this->one($backup->fresh(), BackupResource::class), 201);
    }

    /**
     * Restore replaces the server's contents with the backup's. Replaces, not
     * merges: anything created since it was taken is gone.
     */
    public function restore(Server $server, $backup)
    {
        $this->guard($server, 'backup.restore');
        $this->refuseIfSuspended($server);

        $record = $server->backups()->findOrFail($backup);
        abort_unless($record->is_successful, 409, 'That backup did not complete, so there is nothing to restore.');

        $ok = NodeClient::for($server->node)->restore($server, $record->uuid);
        AuditLog::record('backup.restore', 'Restored "'.$server->name.'" from a backup over the API', $server, $server->id);

        return $ok ? $this->done() : response()->json(['message' => 'The node refused the restore.'], 502);
    }

    /** Locked backups are refused, which is the entire point of locking one. */
    public function destroy(Server $server, $backup)
    {
        $this->guard($server, 'backup.delete');

        $record = $server->backups()->findOrFail($backup);
        abort_if($record->is_locked, 409, 'That backup is locked. Unlock it first.');

        $record->delete();
        AuditLog::record('backup.delete', 'Deleted a backup of "'.$server->name.'" over the API', $server, $server->id);

        return $this->done();
    }

    public function lock(Server $server, $backup)
    {
        $this->guard($server, 'backup.create');

        $record = $server->backups()->findOrFail($backup);
        $record->update(['is_locked' => ! $record->is_locked]);

        return $this->one($record->fresh(), BackupResource::class);
    }
}
