<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A world or save. Switching the active one is a first-class action here rather
 * than something you do by hand in the file manager and hope you got right.
 */
class World extends Model
{
    protected $fillable = [
        'server_id', 'name', 'path', 'seed', 'level_type', 'bytes', 'is_active', 'last_played_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_played_at' => 'datetime'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
