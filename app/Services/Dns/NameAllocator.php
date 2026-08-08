<?php

namespace App\Services\Dns;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Support\Str;

/**
 * Turns a server's name into the label that goes in front of the node's, and
 * makes sure two servers on one node never claim the same one.
 *
 * Uniqueness is per node, because that is the scope the wildcard answers in:
 * alpha.lax1 and alpha.fra1 are two different names and neither shadows the
 * other. A server moved between nodes is re-checked against its new node, so a
 * move can never land on a label that is already taken there.
 */
class NameAllocator
{
    /**
     * A free label for this server on this node.
     *
     * Never fails and never throws. Server creation does not get to break
     * because somebody called their server "www": a reserved or taken label is
     * simply suffixed until it is free.
     */
    public function allocate(string $desired, Node $node, ?int $ignoreServerId = null): string
    {
        $base = $this->slug($desired);

        for ($n = 1; $n <= 99; $n++) {
            $candidate = $n === 1 ? $base : $this->suffix($base, $n);

            if (! $this->reserved($candidate) && $this->free($candidate, $node, $ignoreServerId)) {
                return $candidate;
            }
        }

        // A hundred servers called the same thing on one node. Fall back to
        // something that cannot collide rather than giving up on a name.
        return $this->suffix($base, random_int(100, 999));
    }

    /**
     * The full name a player types, or null when there is nothing to build one
     * from. Never guesses: a missing zone or an unlabelled node means no name,
     * not a broken one.
     */
    public function connectName(?string $label, ?Node $node): ?string
    {
        $zone = DnsConfig::zone();

        if (! filled($label) || ! $node || ! filled($node->dns_label) || $zone === '') {
            return null;
        }

        return $label.'.'.$node->dns_label.'.'.$zone;
    }

    /** The wildcard that covers every server on a node. */
    public function wildcardName(?Node $node): ?string
    {
        $zone = DnsConfig::zone();

        if (! $node || ! filled($node->dns_label) || $zone === '') {
            return null;
        }

        return '*.'.$node->dns_label.'.'.$zone;
    }

    /**
     * A DNS label from whatever somebody typed into the name box.
     *
     * Lowercase, a-z 0-9 and hyphens, no leading or trailing hyphen, capped so
     * the result is something a player can retype without getting it wrong.
     */
    public function slug(string $name): string
    {
        $label = Str::slug($name);
        $label = preg_replace('/[^a-z0-9-]+/', '', mb_strtolower($label)) ?? '';
        $label = trim(preg_replace('/-+/', '-', $label) ?? '', '-');

        $max = max(3, (int) config('domains.max_label', 24));
        if (mb_strlen($label) > $max) {
            $label = trim(mb_substr($label, 0, $max), '-');
        }

        return $label !== '' ? $label : 'server';
    }

    /**
     * Labels nothing may take.
     *
     * The configured list plus every node label. Without the second half a
     * server called "lax1" on node lax1 reads as a name for the node itself in
     * every support conversation about it, and phase 2's SRV records would
     * write under it.
     */
    public function reserved(string $label): bool
    {
        $label = mb_strtolower($label);

        if (in_array($label, array_map('mb_strtolower', (array) config('domains.reserved_labels', [])), true)) {
            return true;
        }

        return Node::query()->whereNotNull('dns_label')->where('dns_label', $label)->exists();
    }

    /**
     * The same check with a voice, for anywhere a human types a label directly.
     * Phase 1 has no such screen; phase 3 does, and this is the rule it uses.
     */
    public function assertAllowed(string $label): void
    {
        if ($this->slug($label) !== mb_strtolower($label)) {
            throw new DnsException('"'.$label.'" is not a usable DNS label. Use letters, numbers and hyphens.');
        }

        if ($this->reserved($label)) {
            throw new DnsException('"'.$label.'" is reserved and cannot be used as a server name.');
        }
    }

    private function free(string $label, Node $node, ?int $ignoreServerId): bool
    {
        return ! Server::query()
            ->where('node_id', $node->id)
            ->where('dns_label', $label)
            ->when($ignoreServerId, fn ($q) => $q->where('id', '!=', $ignoreServerId))
            ->exists();
    }

    /** alpha becomes alpha-2, and stays inside the length cap while doing it. */
    private function suffix(string $base, int $n): string
    {
        $max = max(3, (int) config('domains.max_label', 24));
        $tail = '-'.$n;

        if (mb_strlen($base) + mb_strlen($tail) > $max) {
            $base = trim(mb_substr($base, 0, $max - mb_strlen($tail)), '-');
        }

        return ($base !== '' ? $base : 'server').$tail;
    }
}
