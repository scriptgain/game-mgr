<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An IP and port pair a server can bind to. Ports are the scarce resource on a
 * game node, so they are modelled explicitly rather than invented at start
 * time: that is how you avoid two servers fighting over 27015.
 *
 * A reserved allocation also records what it is for. `role` is a comma
 * separated list because several roles regularly land on one number: CS2 takes
 * game traffic, A2S queries and RCON all on 27015. `protocol` is what actually
 * has to be open, which is not always the same as the game port's: Minecraft
 * Java is TCP for play and UDP for query, on the same port.
 */
class Allocation extends Model
{
    public const PROTOCOLS = [
        'tcp' => 'TCP',
        'udp' => 'UDP',
        'both' => 'TCP And UDP',
    ];

    protected $fillable = ['node_id', 'ip', 'ip_alias', 'port', 'protocol', 'role', 'server_id', 'notes'];

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

    // ----------------------------------------------------------------- roles

    /** @return array<int, string> */
    public function roles(): array
    {
        if (! filled($this->role)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $this->role))));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    public function isGamePort(): bool
    {
        return $this->hasRole('game');
    }

    /** "Game, Query And RCON", or "Additional" when nobody said what it is for. */
    public function roleLabel(): string
    {
        $roles = $this->roles();
        if (! $roles) {
            return 'Additional';
        }

        $names = array_map(fn (string $r) => match ($r) {
            'game' => 'Game',
            'query' => 'Query',
            'rcon' => 'RCON',
            'sftp' => 'SFTP',
            'extra' => 'Additional',
            default => Str::headline($r),
        }, $roles);

        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);

        return implode(', ', $names).' And '.$last;
    }

    public function protocolLabel(): string
    {
        return self::PROTOCOLS[$this->protocol] ?? mb_strtoupper((string) ($this->protocol ?: 'both'));
    }

    /**
     * Hand a port back to the node pool.
     *
     * The role and protocol go with it. Leaving "rcon" on a free port is how a
     * later reservation ends up claiming to be something it is not.
     */
    public function release(): void
    {
        $this->update(['server_id' => null, 'role' => null, 'protocol' => 'both']);
    }
}
