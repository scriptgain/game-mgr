<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'server_id', 'node_id', 'watchdog_rule_id', 'severity', 'title', 'detail',
        'acknowledged_at', 'acknowledged_by',
    ];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(WatchdogRule::class, 'watchdog_rule_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function tone(): string
    {
        return match ($this->severity) {
            'critical' => 'rose',
            'warning' => 'amber',
            default => 'sky',
        };
    }
}
