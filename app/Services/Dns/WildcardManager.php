<?php

namespace App\Services\Dns;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

/**
 * The per-node wildcard record, and the names that live under it.
 *
 * One record per node, not one per server:
 *
 *     *.lax1.play.scriptgain.com.  A  45.63.49.152
 *
 * so creating a server makes no API call at all, deleting one leaves nothing
 * behind, and a provider outage cannot stop either. The cost is that moving a
 * server between nodes changes its name, which phase 1 accepts.
 *
 * Nothing here throws. Every failure is written to the node as a status and a
 * message and left for the hourly sync to repair, because the alternative is a
 * DNS provider's bad afternoon taking out server creation.
 */
class WildcardManager
{
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_UNLABELLED = 'unlabelled';
    public const STATUS_NO_IP = 'no_ip';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRIFT = 'drift';
    public const STATUS_FAILED = 'failed';

    public function __construct(private readonly NameAllocator $names = new NameAllocator) {}

    /**
     * Put this node's wildcard where it should be and confirm it is there.
     *
     * @return string one of the STATUS_ constants
     */
    public function sync(Node $node): string
    {
        if (! DnsConfig::active()) {
            return $this->record($node, self::STATUS_DISABLED, null);
        }

        if (! filled($node->dns_label)) {
            return $this->record($node, self::STATUS_UNLABELLED,
                'This node has no DNS label, so no name can be built for the servers on it.');
        }

        // Names first, and outside the try. A name does not depend on the API
        // call succeeding, only on the record eventually existing, so a
        // provider outage must not leave servers unnamed as well as unresolved.
        $this->refreshServerNames($node);

        $name = $this->names->wildcardName($node);
        $target = $node->dnsTargetIp();

        if (! $target) {
            return $this->record($node, self::STATUS_NO_IP,
                'No public IPv4 address is known for this node. Set the node hostname to its IP address, or add an allocation on it.');
        }

        try {
            $provider = DnsConfig::provider();
            $provider->upsertRecord(DnsConfig::zone(), 'A', $name, $target, DnsConfig::ttl());

            // Confirm rather than assume. "The API said 200" and "the record is
            // there" are different claims, and the node page promises the
            // second one.
            $found = $provider->findRecord(DnsConfig::zone(), 'A', $name);

            if (! $found) {
                return $this->record($node, self::STATUS_DRIFT,
                    'The record was written but reading it straight back found nothing.');
            }

            if ($found->proxied) {
                return $this->record($node, self::STATUS_DRIFT,
                    'That record is proxied. Game traffic is raw UDP and TCP and cannot pass through a proxy. Set it to DNS only, the grey cloud.');
            }

            if (! $found->matches($target)) {
                return $this->record($node, self::STATUS_DRIFT,
                    'The record points at '.$found->content.', not '.$target.'.');
            }

            return $this->record($node, self::STATUS_ACTIVE, null);
        } catch (\Throwable $e) {
            // Deliberately swallowed. The node page shows this message, and the
            // hourly sync will try again; a page must not 500 because a third
            // party is down.
            Log::warning('DNS wildcard sync failed for node '.$node->name.': '.$e->getMessage());

            return $this->record($node, self::STATUS_FAILED, $e->getMessage());
        }
    }

    /** Every node, worst case one provider round trip each. Never throws. */
    public function syncAll(): array
    {
        $results = [];

        foreach (Node::query()->orderBy('name')->get() as $node) {
            $results[$node->name] = $this->sync($node);
        }

        return $results;
    }

    /**
     * Take a node's wildcard away, for example when the node is deleted.
     * A failure here is logged and swallowed: a stale record is untidy, and
     * blocking a node delete on somebody else's API is worse.
     */
    public function remove(Node $node): bool
    {
        $name = $this->names->wildcardName($node);

        if (! DnsConfig::active() || ! $name) {
            return false;
        }

        try {
            return DnsConfig::provider()->deleteRecord(DnsConfig::zone(), 'A', $name);
        } catch (\Throwable $e) {
            Log::warning('DNS wildcard delete failed for node '.$node->name.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Give a server the label and name it should have, without saving.
     *
     * This is the whole of the server-creation path: no network call, two
     * queries, and it is skipped entirely when the feature is off.
     */
    public function nameServer(Server $server, ?Node $node = null): void
    {
        // Never the cached relation when it disagrees with the column: a server
        // being moved has the old node loaded and would be named for it.
        $node ??= $server->relationLoaded('node') && $server->node?->id === $server->node_id
            ? $server->node
            : Node::find($server->node_id);

        if (! DnsConfig::active() || ! $node || ! filled($node->dns_label)) {
            $server->connect_name = null;

            return;
        }

        $label = $server->dns_label;

        // Keep an existing label unless it has stopped being usable, so a name
        // a customer has already handed to their players does not move on its
        // own. A node move is the case where it has to be re-checked.
        if (! filled($label) || $this->names->reserved($label) || $this->taken($label, $server, $node)) {
            $label = $this->names->allocate((string) ($server->dns_label ?: $server->name), $node, $server->id);
        }

        $server->dns_label = $label;
        $server->connect_name = $this->names->connectName($label, $node);
    }

    /**
     * Backfill and repair every server on a node.
     *
     * Servers created while the feature was off have no label at all, and a
     * changed zone leaves every connect_name stale. Both are fixed here rather
     * than by anything a customer has to press.
     *
     * @return int how many rows changed
     */
    public function refreshServerNames(Node $node): int
    {
        $changed = 0;

        foreach ($node->servers()->orderBy('id')->get() as $server) {
            $before = [$server->dns_label, $server->connect_name];
            $this->nameServer($server, $node);

            if ($before !== [$server->dns_label, $server->connect_name]) {
                $server->saveQuietly();
                $changed++;
            }
        }

        return $changed;
    }

    private function taken(string $label, Server $server, Node $node): bool
    {
        return Server::query()
            ->where('node_id', $node->id)
            ->where('dns_label', $label)
            ->when($server->exists, fn ($q) => $q->where('id', '!=', $server->id))
            ->exists();
    }

    private function record(Node $node, string $status, ?string $error): string
    {
        $node->forceFill([
            'wildcard_status' => $status,
            'wildcard_error' => $error === null ? null : mb_substr($error, 0, 480),
            'wildcard_checked_at' => now(),
        ])->saveQuietly();

        return $status;
    }
}
