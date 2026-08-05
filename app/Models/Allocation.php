<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An IP and port pair a server can bind to. Ports are the scarce resource on a
 * game node, so they are modelled explicitly rather than invented at start
 * time: that is how you avoid two servers fighting over 27015.
 */
class Allocation extends Model
{
    protected $fillable = ['node_id', 'ip', 'ip_alias', 'port', 'server_id', 'notes'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** What a player types into their game client. */
    public function address(): string
    {
        return ($this->ip_alias ?: $this->ip).':'.$this->port;
    }

    public function isAssigned(): bool
    {
        return $this->server_id !== null;
    }
}
