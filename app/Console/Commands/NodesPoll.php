<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Node;
use App\Services\NodeClient;
use Illuminate\Console\Command;

/**
 * Ask every node whether it is alive.
 *
 * Direct-mode nodes get dialled. Reverse-mode nodes cannot be dialled by
 * definition, so they are judged purely on how recently they called in.
 */
class NodesPoll extends Command
{
    protected $signature = 'nodes:poll';

    protected $description = 'Poll every node for health and record the result';

    public function handle(): int
    {
        $offlineAfter = (int) config('node.offline_after', 120);

        foreach (Node::all() as $node) {
            $wasOnline = $node->isOnline();

            if ($node->connection_mode === 'direct') {
                if (NodeClient::for($node)->ping()) {
                    $node->forceFill(['last_seen_at' => now()])->save();
                }
            }

            $isOnline = $node->fresh()->isOnline();

            // Only the transition is worth an alert. A node that has been down
            // for a week should not produce an alert every minute.
            if ($wasOnline && ! $isOnline) {
                Alert::create([
                    'node_id' => $node->id,
                    'severity' => 'critical',
                    'title' => $node->name.' went offline',
                    'detail' => 'No heartbeat for more than '.$offlineAfter.' seconds. Servers on this node cannot be controlled until it returns.',
                ]);
                $this->warn($node->name.' offline');
            } elseif (! $wasOnline && $isOnline) {
                Alert::create([
                    'node_id' => $node->id,
                    'severity' => 'info',
                    'title' => $node->name.' came back',
                    'detail' => 'The daemon is answering again.',
                ]);
                $this->info($node->name.' back online');
            }
        }

        return self::SUCCESS;
    }
}
