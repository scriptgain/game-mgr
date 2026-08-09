<?php

namespace App\Console\Commands;

use App\Services\Telemetry;
use Illuminate\Console\Command;

/**
 * The daily send.
 *
 * The schedule runs this often enough that a box which is off overnight still
 * gets a turn; the service itself refuses to send more than once a day, so
 * calling it more cannot cause a flood.
 *
 * Nothing here fails loudly. An install that cannot reach scriptgain.com has
 * nothing wrong with it, and a red line in a cron mail says otherwise.
 */
class TelemetrySend extends Command
{
    protected $signature = 'telemetry:send {--force : Send now, ignoring the once-a-day rule}';

    protected $description = 'Send the anonymous install counts to ScriptGain, if telemetry is on';

    public function handle(): int
    {
        if (! Telemetry::enabled()) {
            $this->line('Telemetry is off. Nothing sent.');

            return self::SUCCESS;
        }

        if (Telemetry::send((bool) $this->option('force'))) {
            $this->line('Sent: '.json_encode(Telemetry::lastSent()));
        } else {
            $this->line('Nothing sent.');
        }

        return self::SUCCESS;
    }
}
