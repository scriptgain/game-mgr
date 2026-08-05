<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerDatabase extends Model
{
    protected $fillable = [
        'server_id', 'database_host_id', 'database', 'username', 'password', 'remote', 'bytes',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(DatabaseHost::class, 'database_host_id');
    }

    /** What a game plugin's config wants. */
    public function jdbc(): string
    {
        $h = $this->host;

        return 'jdbc:mysql://'.($h?->linked_ip ?: $h?->host).':'.($h?->port ?? 3306).'/'.$this->database;
    }
}
