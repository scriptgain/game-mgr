<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Server;
use App\Models\WatchdogRule;
use App\Services\IntegrationNotifier;
use App\Services\NodeClient;
use Illuminate\Console\Command;

/**
 * Evaluate the watchdog rules and act on them.
 *
 * A crashed server gets restarted. An empty server can be stopped to free the
 * node. A memory ceiling gets flagged before the OOM killer beats you to it.
 * Pterodactyl does the first of those and nothing else.
 */
class WatchdogEvaluate extends Command
{
    protected $signature = 'watchdog:evaluate';

    protected $description = 'Evaluate watchdog rules and fire the configured actions';

    public function handle(): int
    {
        $rules = WatchdogRule::with('server')->where('is_active', true)->get();
        $fired = 0;

        foreach ($rules as $rule) {
            $servers = $rule->server_id
                ? Server::where('id', $rule->server_id)->get()
                : Server::whereNull('status')->get();

            foreach ($servers as $server) {
                if (! $this->matches($rule, $server)) {
                    continue;
                }

                // The grace window stops one bad minute producing sixty alerts.
                if ($rule->last_fired_at && $rule->last_fired_at->gt(now()->subSeconds(max(60, $rule->grace_seconds)))) {
                    continue;
                }

                $this->act($rule, $server);
                $rule->forceFill(['last_fired_at' => now()])->save();
                $fired++;
            }
        }

        $this->info($fired.' watchdog '.\Illuminate\Support\Str::plural('rule', $fired).' fired.');

        return self::SUCCESS;
    }

    private function matches(WatchdogRule $rule, Server $server): bool
    {
        return match ($rule->trigger) {
            'crash' => $server->last_crashed_at?->gt(now()->subMinutes(5)) ?? false,
            // Deliberately NOT fired when somebody asked for the server to be
            // off. Pressing Stop leaves exactly the same state as a crash, so
            // without stopped_intentionally this rule would restart every
            // server its owner shut down and they could never turn it off.
            'offline' => $server->auto_restart
                && ! $server->stopped_intentionally
                && $server->power_state !== 'running'
                && $server->last_started_at !== null
                && $server->status === null,
            'memory' => $server->memory > 0
                && $rule->threshold > 0
                && $server->cached_memory / $server->memory * 100 >= $rule->threshold,
            'players_zero' => $server->power_state === 'running'
                && $server->cached_players === 0
                && ($server->cached_at?->lt(now()->subSeconds($rule->grace_seconds)) ?? false),
            'tick_rate' => $rule->threshold > 0 && $server->metrics()
                ->where('sampled_at', '>=', now()->subMinutes(10))
                ->where('tick_rate', '<', $rule->threshold)
                ->exists(),
            // Log matching needs the daemon's log stream, which arrives with the
            // real runtime drivers. Never silently "match" in the meantime.
            'log_pattern' => false,
            default => false,
        };
    }

    private function act(WatchdogRule $rule, Server $server): void
    {
        $title = $rule->name.' fired on '.$server->name;
        $detail = 'Trigger: '.$rule->triggerLabel().'. Action taken: '.$rule->actionLabel().'.';

        Alert::create([
            'server_id' => $server->id,
            'node_id' => $server->node_id,
            'watchdog_rule_id' => $rule->id,
            'severity' => $rule->action === 'alert' ? 'warning' : 'critical',
            'title' => $title,
            'detail' => $detail,
        ]);

        match ($rule->action) {
            'restart' => NodeClient::for($server->node)->power($server, 'restart'),
            // A stop the watchdog decides on is still deliberate: it must not
            // then trip its own offline rule a minute later.
            'stop' => tap(NodeClient::for($server->node)->power($server, 'stop'),
                fn () => $server->update(['stopped_intentionally' => true])),
            'reinstall' => $server->update(['status' => 'installing', 'installed_at' => null]),
            default => null,
        };

        try {
            IntegrationNotifier::notify($title, $detail);
        } catch (\Throwable $e) {
            // A failed notification must never stop the action itself.
        }
    }
}
