<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['server_id', 'player_id', 'event', 'detail', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function tone(): string
    {
        return match ($this->event) {
            'join' => 'emerald',
            'leave' => 'slate',
            'kick' => 'amber',
            'ban' => 'rose',
            'unban' => 'emerald',
            default => 'slate',
        };
    }
}
