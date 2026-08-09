<?php

namespace App\Jobs;

use App\Models\Node;
use App\Models\Server;
use App\Services\ServerMigrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * A migration, off the request cycle.
 *
 * Copying a forty gigabyte world takes far longer than any HTTP request should
 * live, so the API answers immediately and this does the work.
 *
 * One try, deliberately. A half-finished migration retried automatically is a
 * migration whose second attempt starts from a state the first one left, and
 * the failure path in ServerMigrator is written to leave the server where it
 * was rather than to be resumable.
 */
class MigrateServer implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 21600;

    public function __construct(public int $serverId, public int $targetNodeId) {}

    public function handle(ServerMigrator $migrator): void
    {
        $server = Server::with(['node', 'template'])->find($this->serverId);
        $target = Node::find($this->targetNodeId);

        if (! $server || ! $target) {
            Log::warning('GameMGR migration skipped: server or node has gone away');

            return;
        }

        $migrator->migrate($server, $target, function (string $event, string $message) use ($server) {
            Log::info('GameMGR migration ['.$server->uuid_short.'] '.$event.': '.$message);
        });
    }
}
