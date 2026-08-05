<?php

namespace App\Http\Controllers\Client;

use App\Models\Backup;
use App\Models\RetentionPolicy;
use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BackupController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'backup.read');

        return view('server.backups', [
            'title' => $server->name.' Backups',
            'server' => $server->load('node'),
            'backups' => $server->backups()->with('policy')->get(),
            'policies' => RetentionPolicy::orderBy('name')->get(),
            'used' => $server->backups()->count(),
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'backup.create');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'ignored' => ['nullable', 'string', 'max:2000'],
            'disk' => ['required', 'in:local,s3,storagemgr'],
        ]);

        // Locked backups sit outside the cap on purpose: the whole point of
        // locking one is that it survives everything, including this check.
        $counted = $server->backups()->where('is_locked', false)->count();
        if ($server->backup_limit > 0 && $counted >= $server->backup_limit) {
            return back()->with('error', 'This server is at its limit of '.$server->backup_limit.' backups. Delete one first, or unlock and delete an older one.');
        }

        $backup = Backup::create([
            'server_id' => $server->id,
            'name' => $data['name'] ?: 'Manual '.now()->format('Y-m-d H:i'),
            'disk' => $data['disk'],
            'ignored_files' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['ignored'] ?? ''))))),
        ]);

        $result = NodeClient::for($server->node)->backup($server, $backup->uuid, $backup->ignored_files ?? []);

        if ($result === null) {
            $backup->update(['failure_reason' => 'The node did not answer.', 'completed_at' => now()]);

            return back()->with('error', 'The node did not answer, so the backup was recorded as failed.');
        }

        $backup->update([
            'bytes' => $result['bytes'] ?? 0,
            'checksum' => $result['checksum'] ?? null,
            'is_successful' => true,
            'completed_at' => now(),
        ]);

        $this->log($server, 'backup.create', 'Took backup "'.$backup->name.'"');

        return back()->with('status', 'Backup complete.');
    }

    public function lock(Server $server, Backup $backup)
    {
        $this->guard($server, 'backup.create');
        abort_unless($backup->server_id === $server->id, 404);

        $backup->update(['is_locked' => ! $backup->is_locked]);

        return back()->with('status', $backup->is_locked
            ? 'Locked. Retention will leave this one alone.'
            : 'Unlocked. Retention can now remove this one.');
    }

    public function restore(Server $server, Backup $backup)
    {
        $this->guard($server, 'backup.restore');
        abort_unless($backup->server_id === $server->id, 404);

        if (! $backup->is_successful) {
            return back()->with('error', 'That backup failed, so there is nothing to restore from it.');
        }

        if (! NodeClient::for($server->node)->restore($server, $backup->uuid)) {
            return back()->with('error', 'The node refused the restore.');
        }

        $server->update(['status' => 'restoring']);
        $this->log($server, 'backup.restore', 'Restored backup "'.$backup->name.'"');

        return back()->with('status', 'Restore started. The server stays offline until it finishes.');
    }

    public function destroy(Server $server, Backup $backup)
    {
        $this->guard($server, 'backup.delete');
        abort_unless($backup->server_id === $server->id, 404);

        if ($backup->is_locked) {
            return back()->with('error', 'That backup is locked. Unlock it first.');
        }

        $name = $backup->name;
        $backup->delete();
        $this->log($server, 'backup.delete', 'Deleted backup "'.$name.'"');

        return back()->with('status', 'Backup deleted.');
    }
}
