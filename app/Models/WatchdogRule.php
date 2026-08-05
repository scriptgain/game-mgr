<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rule that watches a server and does something about it. server_id null
 * means the rule applies to the whole fleet.
 *
 * This is the gap that costs game hosts the most sleep: Pterodactyl will
 * restart a crashed container, and that is the end of its interest. It cannot
 * tell you a server has been at zero players for six hours, cannot watch the
 * log for a known corruption message, and cannot page you anywhere.
 */
class WatchdogRule extends Model
{
    use Concerns\Auditable;

    public const TRIGGERS = [
        'crash' => 'Server Crashed',
        'offline' => 'Unexpectedly Offline',
        'log_pattern' => 'Log Line Matches',
        'memory' => 'Memory Above Threshold',
        'players_zero' => 'No Players For A While',
        'tick_rate' => 'Tick Rate Below Threshold',
    ];

    public const ACTIONS = [
        'alert' => 'Alert Only',
        'restart' => 'Restart The Server',
        'stop' => 'Stop The Server',
        'reinstall' => 'Reinstall The Server',
    ];

    protected $fillable = [
        'server_id', 'name', 'trigger', 'pattern', 'threshold', 'grace_seconds',
        'action', 'channels', 'is_active', 'last_fired_at',
    ];

    protected function casts(): array
    {
        return ['channels' => 'array', 'is_active' => 'boolean', 'last_fired_at' => 'datetime'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeText(): string
    {
        return $this->server ? $this->server->name : 'Every Server';
    }

    public function triggerLabel(): string
    {
        return self::TRIGGERS[$this->trigger] ?? ucfirst((string) $this->trigger);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst((string) $this->action);
    }
}
