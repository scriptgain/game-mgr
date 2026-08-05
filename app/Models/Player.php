<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somebody who has played on a server. Pterodactyl has no concept of a player
 * at all: to kick one you open the console and type. Here it is a row you can
 * search, ban, whitelist and chart.
 */
class Player extends Model
{
    protected $fillable = [
        'server_id', 'identifier', 'name', 'ip', 'first_seen_at', 'last_seen_at',
        'playtime_seconds', 'is_online', 'is_banned', 'is_op', 'is_whitelisted', 'ban_reason',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_online' => 'boolean',
            'is_banned' => 'boolean',
            'is_op' => 'boolean',
            'is_whitelisted' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlayerEvent::class)->latest('occurred_at');
    }

    public function playtime(): string
    {
        $h = intdiv($this->playtime_seconds, 3600);
        $m = intdiv($this->playtime_seconds % 3600, 60);

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}
