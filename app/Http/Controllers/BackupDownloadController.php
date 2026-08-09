<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Http\Request;

/**
 * Serves a backup from the node, for a link that was signed for this one
 * backup and this one server.
 *
 * Outside the auth middleware on purpose: the signature is the authorisation,
 * which is what lets a download survive being handed to a download manager that
 * carries no session. Laravel's signed middleware has already checked the
 * signature and the expiry before this runs, so the only thing left to prove is
 * that the backup really belongs to the server named in the URL.
 */
class BackupDownloadController extends Controller
{
    public function show(Request $request, Server $server, string $backup)
    {
        $record = $server->backups()->where('uuid', $backup)->firstOrFail();
        abort_unless($record->is_successful, 404);

        $stream = NodeClient::for($server->node)->downloadBackup($server, $record->uuid);
        abort_if($stream === null, 502, 'The node could not produce that backup.');

        return response()->streamDownload(
            function () use ($stream) {
                // Echoed in chunks rather than returned whole: a forty gigabyte
                // backup must not be assembled in memory to be handed over.
                while (! $stream->eof()) {
                    echo $stream->read(1024 * 512);
                    flush();
                }
            },
            $record->name.'.tar.gz',
            ['Content-Type' => 'application/gzip'],
        );
    }
}
