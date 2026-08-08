<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Models\Template;
use App\Support\PortReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Works out which ports a server gets, and then takes all of them or none.
 *
 * Three rules, in order.
 *
 * 1. A game gets its real port. Palworld is 8211, Minecraft Java is 25565,
 *    Valheim is 2456. Those numbers are in every guide, every firewall rule and
 *    every player's head, and a panel that hands out "whatever was free" is
 *    making its own users explain a port number to everybody who wants to join.
 *    The rival panel picks the lowest free port and that is exactly how a
 *    Palworld server ended up on 2456, which is Valheim's, and was unreachable
 *    because the node's firewall ranges are written around the canonical ports.
 *
 * 2. A dedicated address gets rule one with no exceptions. That is the entire
 *    point of paying for a dedicated IP: nobody else is on it, so nothing can
 *    be in the way, so the port is the port.
 *
 * 3. A shared address can only give the canonical port to one server, so the
 *    rest are shifted. The whole set moves by the same amount, together, so the
 *    relative layout a game documents survives, and the shift is reported
 *    rather than hidden: the second Palworld server on an address is a support
 *    ticket waiting to happen unless somebody is told its port is 8212.
 *
 * The set is reserved inside one transaction. Half a set is worse than none: a
 * server holding its game port and not its RCON port looks fine on the page and
 * fails the first time anybody tries to use it.
 */
class AllocationPlanner
{
    /**
     * How far the whole set may slide off canonical before giving up.
     *
     * A thousand is generous rather than meaningful. In practice the first free
     * shift is one or two, and a node that needs a thousand has run out of
     * addresses, not out of ports.
     */
    public const MAX_SHIFT = 1000;

    // ------------------------------------------------------------- planning

    /**
     * Where this server's ports would go. Nothing is written.
     *
     * Returns null only when there is nothing to reserve at all: a template
     * with no declared port set on a node with no free ports, which is the one
     * case that has always been allowed to produce a server with no address.
     *
     * @throws ValidationException when a declared port set cannot be satisfied.
     */
    public function plan(Node $node, ?Template $template, ?Allocation $preferred = null): ?PortReservation
    {
        $template?->loadMissing('ports');

        if ($preferred && ($preferred->node_id !== $node->id || $preferred->isAssigned())) {
            $preferred = null;
        }

        if (! $template || ! $template->hasPortSet()) {
            return $this->planSinglePort($node, $preferred);
        }

        return $this->planPortSet($node, $template, $preferred);
    }

    /**
     * The old behaviour, kept for templates nobody has declared ports for.
     *
     * One port, from the pool, and no opinion about which. A template with no
     * port set is a template the panel knows nothing about, and inventing a
     * canonical port for it would be a guess dressed up as a fact.
     */
    private function planSinglePort(Node $node, ?Allocation $preferred): ?PortReservation
    {
        $allocation = $preferred ?: $node->allocations()
            ->whereNull('server_id')->orderBy('ip')->orderBy('port')->first();

        if (! $allocation) {
            return null;
        }

        return new PortReservation(
            ip: $allocation->ip,
            ipAlias: $allocation->ip_alias,
            ports: [[
                'port' => (int) $allocation->port,
                'protocol' => 'both',
                'roles' => ['game'],
                'labels' => ['Game Port'],
                'required' => true,
            ]],
            shift: 0,
            dedicated: $this->isDedicated($node, $allocation->ip),
            canonicalGamePort: (int) $allocation->port,
            fromPortSet: false,
        );
    }

    private function planPortSet(Node $node, Template $template, ?Allocation $preferred): PortReservation
    {
        $canonical = (int) $template->canonicalGamePort();
        $wanted = $template->portSet();
        $candidates = $this->candidateIps($node, $preferred);

        if (! $candidates) {
            throw ValidationException::withMessages([
                'allocation_id' => $preferred
                    ? 'That address is already taken.'
                    : $node->name.' has no addresses yet. Add an IP and a port range on the node\'s Allocations page first.',
            ]);
        }

        // Two passes. The first insists that every port in the set is free,
        // including the ones the template marked optional, because a Rust
        // server without its companion app port still works but is worse. Only
        // if no address can manage that does the second pass allow an optional
        // port to be dropped.
        foreach ([true, false] as $strict) {
            foreach ($candidates as $ip => $meta) {
                $taken = $this->assignedPorts($node, $ip);

                foreach ($this->shiftsFor($meta['dedicated'], $ip, $preferred, $canonical) as $shift) {
                    $set = $template->portSet($shift);

                    // A shift that pushed part of the set past 65535 is not the
                    // same set any more, so it is not an answer.
                    if (count($set) !== count($wanted)) {
                        continue;
                    }

                    $kept = [];
                    $dropped = [];
                    $blocked = false;

                    foreach ($set as $spec) {
                        if (! isset($taken[$spec['port']])) {
                            $kept[] = $spec;

                            continue;
                        }
                        if ($spec['required'] || $strict) {
                            $blocked = true;

                            break;
                        }
                        $dropped[] = $spec;
                    }

                    if ($blocked || ! $kept) {
                        continue;
                    }

                    return $this->reservationFor($node, $ip, $meta, $kept, $dropped, $shift, $canonical, $preferred);
                }
            }
        }

        throw ValidationException::withMessages([
            'allocation_id' => $this->cannotPlaceMessage($node, $template, $canonical, $candidates),
        ]);
    }

    private function reservationFor(
        Node $node,
        string $ip,
        array $meta,
        array $kept,
        array $dropped,
        int $shift,
        int $canonical,
        ?Allocation $preferred,
    ): PortReservation {
        $existing = Allocation::where('node_id', $node->id)->where('ip', $ip)
            ->pluck('port')->map(fn ($p) => (int) $p)->all();

        $newPorts = array_values(array_diff(array_column($kept, 'port'), $existing));

        $notes = [];
        if ($newPorts) {
            $notes[] = count($newPorts) === 1
                ? 'Port '.$newPorts[0].' was not in the node pool and has been added to it.'
                : count($newPorts).' ports were not in the node pool and have been added to it: '.implode(', ', $newPorts).'.';
        }
        foreach ($dropped as $spec) {
            $notes[] = 'The optional '.implode(' and ', $spec['labels']).' on '.$spec['port'].' was already taken and has been skipped.';
        }
        if ($preferred && $preferred->ip === $ip && (int) $preferred->port !== ($canonical + $shift)) {
            $notes[] = $meta['dedicated']
                ? 'You picked '.$preferred->port.', but '.$ip.' has no other servers on it, so the game got its real port instead.'
                : 'You picked '.$preferred->port.', but the rest of the set did not fit around it.';
        }

        return new PortReservation(
            ip: $ip,
            ipAlias: $meta['alias'],
            ports: $kept,
            shift: $shift,
            dedicated: $meta['dedicated'],
            canonicalGamePort: $canonical,
            fromPortSet: true,
            newPorts: $newPorts,
            notes: $notes,
        );
    }

    /** Why nothing fitted, in terms somebody can act on. */
    private function cannotPlaceMessage(Node $node, Template $template, int $canonical, array $candidates): string
    {
        $ports = implode(', ', array_column($template->portSet(), 'port'));
        $addresses = count($candidates) === 1 ? 'its only address' : 'any of its '.count($candidates).' addresses';

        return $template->name.' needs '.count($template->portSet()).' ports together ('.$ports.'), and '
            .$node->name.' cannot fit that set on '.$addresses.' even after shifting it. '
            .'Add another IP to the node, or free the servers holding '.$canonical.' and the ports around it.';
    }

    /**
     * Which shifts to try on this address, best first.
     *
     * A dedicated address gets exactly one candidate, zero, because that is the
     * rule and not a preference. If the canonical set cannot be placed on an
     * empty address then something is wrong with the set, not with the address.
     *
     * @return iterable<int>
     */
    private function shiftsFor(bool $dedicated, string $ip, ?Allocation $preferred, int $canonical): iterable
    {
        if ($dedicated) {
            return [0];
        }

        // An operator who picked a port meant it, so try theirs before the
        // canonical one. On a shared address that is a legitimate answer.
        if ($preferred && $preferred->ip === $ip) {
            $wanted = (int) $preferred->port - $canonical;
            if ($wanted !== 0) {
                return array_merge([$wanted], range(0, self::MAX_SHIFT));
            }
        }

        return range(0, self::MAX_SHIFT);
    }

    // ----------------------------------------------------------- reserving

    /**
     * Claim the plan, all of it, or none of it.
     *
     * Runs inside a transaction and re-reads every row under a lock, because
     * planning and reserving are two moments and two operators can create a
     * server in the gap. Anything that turns out to be taken in between aborts
     * the whole thing rather than leaving a server holding half a set.
     *
     * @return Allocation|null The primary, which is the row carrying the game role.
     */
    public function reserve(Server $server, PortReservation $plan): ?Allocation
    {
        return DB::transaction(function () use ($server, $plan) {
            $primary = null;
            $first = null;

            foreach ($plan->ports as $spec) {
                $row = Allocation::firstOrCreate(
                    ['node_id' => $server->node_id, 'ip' => $plan->ip, 'port' => $spec['port']],
                    ['ip_alias' => $plan->ipAlias, 'protocol' => $spec['protocol']],
                );

                // Re-read under a lock. firstOrCreate answers from before the
                // transaction's view of the world in the racing case.
                $row = Allocation::whereKey($row->id)->lockForUpdate()->first();

                if ($row->server_id !== null && $row->server_id !== $server->id) {
                    throw ValidationException::withMessages([
                        'allocation_id' => 'Port '.$spec['port'].' on '.$plan->ip
                            .' was claimed by another server while this one was being created. Nothing was reserved. Try again.',
                    ]);
                }

                $row->update([
                    'server_id' => $server->id,
                    'role' => implode(',', $spec['roles']),
                    'protocol' => $spec['protocol'],
                    'ip_alias' => $row->ip_alias ?: $plan->ipAlias,
                ]);

                $first ??= $row;
                if (in_array('game', $spec['roles'], true)) {
                    $primary = $row;
                }
            }

            return $primary ?: $first;
        });
    }

    // ------------------------------------------------------------ addresses

    /**
     * Is this address this server's alone?
     *
     * There is no node_ips table yet, so there is no operator-declared notion
     * of "dedicated" to read. What there is, and what is honest, is whether
     * anything else is on the address: an IP with no assigned ports has nobody
     * to collide with, so the canonical port is free and must be used.
     *
     * When the dedicated-IP inventory lands this is the single place that has
     * to change: look the address up, and return its declared flag when it has
     * one, falling back to this. Nothing else in the planner asks the question.
     */
    public function isDedicated(Node $node, string $ip, ?int $ignoreServerId = null): bool
    {
        return ! Allocation::where('node_id', $node->id)
            ->where('ip', $ip)
            ->whereNotNull('server_id')
            ->when($ignoreServerId, fn ($q) => $q->where('server_id', '!=', $ignoreServerId))
            ->exists();
    }

    /**
     * Every address this node has, with enough about each to choose one.
     *
     * @return array<string, array{alias:?string, ports:int, used:int, servers:int, dedicated:bool}>
     */
    public function ipInventory(Node $node): array
    {
        $rows = Allocation::where('node_id', $node->id)->get(['ip', 'ip_alias', 'server_id']);
        $out = [];

        foreach ($rows as $row) {
            $ip = (string) $row->ip;
            $out[$ip] ??= ['alias' => null, 'ports' => 0, 'used' => 0, 'servers' => [], 'dedicated' => true];
            $out[$ip]['alias'] ??= $row->ip_alias;
            $out[$ip]['ports']++;
            if ($row->server_id) {
                $out[$ip]['used']++;
                $out[$ip]['servers'][$row->server_id] = true;
            }
        }

        foreach ($out as $ip => $info) {
            $out[$ip]['servers'] = count($info['servers']);
            $out[$ip]['dedicated'] = $out[$ip]['servers'] === 0;
        }

        ksort($out);

        return $out;
    }

    /**
     * Addresses worth trying, best first.
     *
     * Empty addresses come first so a node with spare IPs hands each server its
     * own and every one of them gets canonical ports. That is the outcome
     * anybody buying addresses is paying for.
     *
     * @return array<string, array{alias:?string, dedicated:bool}>
     */
    private function candidateIps(Node $node, ?Allocation $preferred): array
    {
        $inventory = $this->ipInventory($node);

        if ($preferred) {
            $ip = (string) $preferred->ip;

            return isset($inventory[$ip])
                ? [$ip => ['alias' => $preferred->ip_alias ?: $inventory[$ip]['alias'], 'dedicated' => $inventory[$ip]['dedicated']]]
                : [];
        }

        uasort($inventory, fn (array $a, array $b) => [$a['servers'], $a['used']] <=> [$b['servers'], $b['used']]);

        $out = [];
        foreach ($inventory as $ip => $info) {
            $out[$ip] = ['alias' => $info['alias'], 'dedicated' => $info['dedicated']];
        }

        return $out;
    }

    /** Port numbers already spoken for on this address. */
    private function assignedPorts(Node $node, string $ip): array
    {
        return Allocation::where('node_id', $node->id)
            ->where('ip', $ip)
            ->whereNotNull('server_id')
            ->pluck('port')
            ->mapWithKeys(fn ($p) => [(int) $p => true])
            ->all();
    }
}
