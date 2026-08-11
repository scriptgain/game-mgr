<?php

namespace App\Models;

use App\Support\SteamGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Steam account paid games are installed with.
 *
 * The admin registers it once; clients never see it, and never choose it
 * either. A server's binding is set by whoever created the server in the admin
 * area, because handing a client a picker of other people's Steam logins is
 * not a feature.
 */
class SteamAccount extends Model
{
    use Concerns\Auditable;

    protected $fillable = ['label', 'username', 'password', 'shared_secret', 'authorized_nodes'];

    /**
     * Neither secret is ever serialised. This is what keeps them out of the
     * API resources, `toArray()` on an audit payload, and any debug dump that
     * happens to hold a model.
     */
    protected $hidden = ['password', 'shared_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'shared_secret' => 'encrypted',
            'authorized_nodes' => 'array',
        ];
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * A fresh Steam Guard code, or an empty string when this account has no
     * shared secret. Empty must always mean "send no code": a wrong code does
     * not fail cleanly, it burns an attempt and can trip a rate limit that
     * looks exactly like a bad password.
     */
    public function guardCode(): string
    {
        return SteamGuard::code($this->shared_secret);
    }

    /** Whether steamcmd has already established a sentry on this node. */
    public function authorizedOn(int|Node $node): bool
    {
        $id = $node instanceof Node ? $node->id : $node;

        return in_array($id, $this->authorized_nodes ?? [], true);
    }

    /**
     * Record that a login succeeded on a node.
     *
     * Called after an install completes rather than when one is dispatched: an
     * install that failed proves nothing about the sentry.
     */
    public function markAuthorized(int|Node $node): void
    {
        $id = $node instanceof Node ? $node->id : $node;
        $nodes = $this->authorized_nodes ?? [];
        if (in_array($id, $nodes, true)) {
            return;
        }

        $nodes[] = $id;
        $this->update(['authorized_nodes' => array_values($nodes)]);
    }
}
