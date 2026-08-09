<?php

use Illuminate\Support\Facades\Schedule;

// Poll every node for health and capacity. A node that misses its window is
// marked offline, its servers are parked, and the watchdog fires.
Schedule::command('nodes:poll')->everyMinute()->withoutOverlapping();

// Fire due server schedules (restarts, commands, backups, game updates).
Schedule::command('schedules:dispatch-due')->everyMinute()->withoutOverlapping();

// Sample per-server CPU, memory, disk, network and player count into history,
// which is what the charts read. Pterodactyl keeps none of this.
Schedule::command('metrics:sample')->everyMinute()->withoutOverlapping();

// Evaluate watchdog rules (crash, offline, log pattern, memory ceiling) and
// dispatch alerts to the configured channels.
Schedule::command('watchdog:evaluate')->everyMinute()->withoutOverlapping();

// Check installed mods against their upstream source once an hour so the Mods
// tab can show "update available" without the user hunting for it.
Schedule::command('mods:check-updates')->hourly()->withoutOverlapping();

// Reconcile the per-node wildcard DNS records, and the names built on them.
// Hourly because the request path never blocks on a DNS provider: a failure
// there is recorded against the node and repaired here.
Schedule::command('gamemgr:dns-sync')->hourly()->withoutOverlapping();

// Anonymous install counts, if the operator has left telemetry on. Hourly
// rather than daily so a box that is powered off overnight still gets a turn;
// the service refuses to send more than once a day, so this cannot flood.
Schedule::command('telemetry:send')->hourly()->withoutOverlapping();

// Nightly housekeeping: trim metric history, expired backups, old audit rows.
Schedule::command('gamemgr:housekeeping')->dailyAt('03:30')->withoutOverlapping();

// Self-update: check periodically and auto-apply a newer signed release unless
// the operator has turned auto-update off.
Schedule::command('app:update')
    ->everyFiveMinutes()
    ->when(fn () => \App\Services\UpdateService::autoEnabled())
    ->withoutOverlapping();

// Admin "Update Now" requests, serviced within a minute by the scheduler so the
// command runs with the right PHP binary and user rather than shelling out
// from php-fpm.
Schedule::command('app:update')
    ->everyMinute()
    ->when(fn () => \App\Models\Setting::get('update_requested') === '1')
    ->withoutOverlapping();
