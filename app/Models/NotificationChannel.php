<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    use Concerns\Auditable;

    public const TYPES = [
        'discord' => 'Discord Webhook',
        'slack' => 'Slack Webhook',
        'webhook' => 'Generic Webhook',
        'email' => 'Email',
    ];

    /** Everything a channel can be told about. */
    public const EVENTS = [
        'server.crashed' => 'Server Crashed',
        'server.started' => 'Server Started',
        'server.stopped' => 'Server Stopped',
        'server.installed' => 'Server Installed',
        'node.offline' => 'Node Went Offline',
        'node.online' => 'Node Came Back',
        'backup.completed' => 'Backup Completed',
        'backup.failed' => 'Backup Failed',
        'update.available' => 'Game Update Available',
        'watchdog.fired' => 'Watchdog Rule Fired',
        'capacity.warning' => 'Node Nearly Full',
    ];

    protected $fillable = ['name', 'type', 'target', 'events', 'is_active', 'last_used_at'];

    protected $hidden = ['target'];

    protected function casts(): array
    {
        return ['events' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    /** Never print a webhook URL in full: it is a bearer credential. */
    public function maskedTarget(): string
    {
        $t = (string) $this->target;
        if ($this->type === 'email') {
            return $t;
        }
        if (strlen($t) <= 24) {
            return str_repeat('*', max(0, strlen($t) - 4)).substr($t, -4);
        }

        return substr($t, 0, 20).'...'.substr($t, -4);
    }
}
