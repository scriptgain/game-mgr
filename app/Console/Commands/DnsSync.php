<?php

namespace App\Console\Commands;

use App\Services\Dns\DnsConfig;
use App\Services\Dns\WildcardManager;
use Illuminate\Console\Command;

/**
 * Reconcile every node's wildcard record, hourly.
 *
 * This is the repair path for everything the request path is not allowed to
 * block on: a provider that was down when a node was created, a record somebody
 * deleted by hand, a record somebody orange-clouded, a zone that changed, and
 * servers created while the feature was switched off.
 *
 * A reconciler rather than a queued job on purpose. A queued job dies with the
 * first service restart and never comes back; this runs again in an hour
 * whatever happened last time.
 */
class DnsSync extends Command
{
    protected $signature = 'gamemgr:dns-sync';

    protected $description = 'Reconcile the per-node wildcard DNS records and the names built on them';

    public function handle(WildcardManager $wildcards): int
    {
        if (! DnsConfig::active()) {
            $this->line('Connection names are turned off. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line('Zone '.DnsConfig::zone().' via the '.DnsConfig::providerName().' provider.');

        $failed = 0;

        foreach ($wildcards->syncAll() as $node => $status) {
            $this->line(sprintf('  %-24s %s', $node, $status));

            if (in_array($status, [WildcardManager::STATUS_FAILED, WildcardManager::STATUS_DRIFT], true)) {
                $failed++;
            }
        }

        if ($failed > 0) {
            // Reported, not thrown. The schedule runs again in an hour and the
            // node page carries the message in the meantime.
            $this->warn($failed.' '.\Illuminate\Support\Str::plural('node', $failed).' still need attention. See the node page for the exact error.');
        }

        return self::SUCCESS;
    }
}
