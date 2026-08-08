<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Actually install a server on its node.
 *
 * Nothing did this before. Creating a server wrote status = "installing" and
 * the panel never spoke to the node, so no game files were ever fetched and the
 * row stayed at "installing" until somebody deleted it. The daemon has had the
 * endpoint since the first import.
 *
 * Queued, because a SteamCMD app can be tens of gigabytes and this holds an
 * open stream for the whole download. One attempt only: retrying a multi-hour
 * download automatically is how you turn one failure into three.
 */
class InstallServer implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Six hours. TF2's dedicated server alone is 14.9 GB. */
    public int $timeout = 21600;

    public function __construct(public int $serverId) {}

    public function handle(): void
    {
        $server = Server::with('node')->find($this->serverId);
        if (! $server) {
            return;
        }
        if (! $server->node) {
            $this->fail($server, 'This server has no node, so there is nowhere to install it.');

            return;
        }

        $server->forceFill([
            'status' => 'installing',
            'install_started_at' => now(),
            'install_progress' => null,
            'install_phase' => 'Starting',
            'install_log' => '',
        ])->save();

        $lines = [];
        $lastWrite = 0;
        $progress = null;
        $phase = 'Starting';

        $ok = NodeClient::for($server->node)->install($server, function (string $event, string $data) use (
            $server, &$lines, &$lastWrite, &$progress, &$phase
        ) {
            $lines[] = $data;
            // The tail is what anyone reads. Keeping the whole of a SteamCMD
            // install would be megabytes of progress chatter in a row that is
            // read on every page load.
            if (count($lines) > 500) {
                array_splice($lines, 0, count($lines) - 500);
            }

            if ($found = $this->progressFrom($data)) {
                [$progress, $phase] = $found;
            }

            // Throttled: this stream emits many lines a second during a
            // download and a write per line would hammer the database for no
            // extra information.
            if (time() - $lastWrite >= 2) {
                $lastWrite = time();
                $server->forceFill([
                    'install_log' => implode("\n", $lines),
                    'install_progress' => $progress,
                    'install_phase' => $phase,
                ])->save();
            }
        });

        $server->forceFill([
            'install_log' => implode("\n", $lines),
            'install_progress' => $ok ? 100 : $progress,
            'install_phase' => $ok ? 'Complete' : 'Failed',
            'status' => $ok ? null : 'install_failed',
            'installed_at' => $ok ? now() : null,
        ])->save();

        AuditLog::record(
            $ok ? 'server.installed' : 'server.install_failed',
            ($ok ? 'Installed "' : 'Install failed for "').$server->name.'"',
            $server,
            $server->id,
        );

        if (! $ok) {
            Log::warning('GameMGR install failed for server '.$server->uuid);
        }
    }

    /**
     * Pull a percentage and a phase out of a console line.
     *
     * SteamCMD is the only runtime that reports a real number, and it reports
     * it as "Update state (0x61) downloading, progress: 41.27 (…)". The Docker
     * and LinuxGSM paths get a phase and a null percentage rather than a fake
     * one, because a progress bar that is guessing is worse than no bar.
     *
     * @return array{0: int|null, 1: string}|null
     */
    private function progressFrom(string $line): ?array
    {
        if (preg_match('/progress:\s*([0-9]+(?:\.[0-9]+)?)/i', $line, $m)) {
            $pct = (int) round((float) $m[1]);
            $phase = 'Downloading';
            if (stripos($line, 'validating') !== false) {
                $phase = 'Validating';
            } elseif (stripos($line, 'preallocating') !== false) {
                $phase = 'Allocating Disk';
            }

            return [max(0, min(100, $pct)), $phase];
        }

        foreach ([
            'pulling' => 'Pulling Image',
            'app_update' => 'Downloading',
            'linuxgsm' => 'Fetching LinuxGSM',
            'auto-install' => 'Installing',
            'install complete' => 'Complete',
            'data directory' => 'Preparing',
        ] as $needle => $phase) {
            if (stripos($line, $needle) !== false) {
                return [null, $phase];
            }
        }

        return null;
    }

    private function fail(Server $server, string $why): void
    {
        $server->forceFill([
            'status' => 'install_failed',
            'install_phase' => 'Failed',
            'install_log' => $why,
        ])->save();
    }
}
