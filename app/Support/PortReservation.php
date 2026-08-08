<?php

namespace App\Support;

/**
 * A worked out answer to "where does this server go", before anything is
 * written down.
 *
 * Planning and reserving are separate on purpose. The plan can be rejected,
 * explained, or shown to somebody, and nothing has been half claimed by the
 * time it is. Half a port set is the failure mode this whole design exists to
 * remove: a Palworld server holding 8211 and nothing else is a server whose
 * RCON, query and firewall rules all quietly point at a port somebody else owns.
 */
final class PortReservation
{
    /**
     * @param  array<int, array{port:int, protocol:string, roles:array<int,string>, required:bool, labels:array<int,string>}>  $ports
     */
    public function __construct(
        public readonly string $ip,
        public readonly ?string $ipAlias,
        public readonly array $ports,
        public readonly int $shift,
        public readonly bool $dedicated,
        public readonly ?int $canonicalGamePort,
        public readonly bool $fromPortSet,
        /** Ports not already in the node pool, which reserving will add to it. */
        public readonly array $newPorts = [],
        /** Anything the operator should know that the numbers do not say. */
        public readonly array $notes = [],
    ) {}

    /** Is every port exactly where the game says it should be? */
    public function isCanonical(): bool
    {
        return $this->shift === 0;
    }

    public function gamePort(): ?int
    {
        foreach ($this->ports as $spec) {
            if (in_array('game', $spec['roles'], true)) {
                return $spec['port'];
            }
        }

        return $this->ports[0]['port'] ?? null;
    }

    public function address(): string
    {
        return ($this->ipAlias ?: $this->ip).':'.$this->gamePort();
    }

    /** "8211/udp game, 25575/tcp rcon, 27015/udp query" */
    public function portSummary(): string
    {
        $parts = [];
        foreach ($this->ports as $spec) {
            $parts[] = $spec['port'].'/'.$spec['protocol'].' '.implode('+', $spec['roles']);
        }

        return implode(', ', $parts);
    }

    /**
     * What to tell the person who pressed Create.
     *
     * A shifted set is never reported as a plain success. The number a player
     * types changes, the firewall ranges the node installer writes are built
     * around the canonical port, and both of those are worth a sentence.
     */
    public function flash(): string
    {
        if (! $this->fromPortSet) {
            $line = 'Reserved '.$this->address().'. This template does not declare a port set, so only one port was taken.';
        } elseif ($this->isCanonical()) {
            $line = 'Reserved '.count($this->ports).' '.($this->dedicated ? 'ports on the dedicated address ' : 'ports on ').$this->ip
                .' at their canonical numbers: '.$this->portSummary().'.';
        } else {
            $line = 'The canonical port '.$this->canonicalGamePort.' was already taken on '.$this->ip
                .', so the whole set moved by '.($this->shift > 0 ? '+' : '').$this->shift.'. Players connect to '.$this->address()
                .', not '.$this->canonicalGamePort.'. Ports reserved: '.$this->portSummary().'.';
        }

        return trim($line.' '.implode(' ', $this->notes));
    }
}
