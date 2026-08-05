<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One historical sample. This table is the reason GameMGR can draw a week of
 * CPU and a month of player counts: Pterodactyl throws its live stats away the
 * moment the websocket closes, so there is nothing to chart after the fact.
 */
class ServerMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'server_id', 'sampled_at', 'cpu', 'memory', 'disk',
        'net_rx', 'net_tx', 'players', 'tick_rate',
    ];

    protected function casts(): array
    {
        return ['sampled_at' => 'datetime'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
