<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An opt-in public page for one server: up or down, who is on, when the next
 * restart lands. Community owners currently build these by hand or pay for a
 * third-party service.
 */
class StatusPage extends Model
{
    protected $fillable = [
        'server_id', 'slug', 'headline', 'is_public',
        'show_players', 'show_address', 'show_uptime', 'show_version',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'show_players' => 'boolean',
            'show_address' => 'boolean',
            'show_uptime' => 'boolean',
            'show_version' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
