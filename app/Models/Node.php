<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A machine that runs game servers. One panel, nodes anywhere: a node is any
 * Linux box with the daemon on it, whether that is a VPS in Frankfurt, a
 * dedicated server, a Proxmox VM or a spare desktop at home.
 *
 * Two transports. direct means the panel dials the daemon and the node needs a
 * reachable port. reverse means the daemon holds an outbound connection to the
 * panel and work is pushed down it, which is the only way a NAT'd home box is
 * usable at all. Pterodactyl has no equivalent, which is why its users are
 * stuck with public IPs and port forwarding.
 */
class Node extends Model
{
    use Concerns\Auditable;

    public const RUNTIMES = ['docker', 'steamcmd', 'linuxgsm'];

    protected $fillable = [
        'uuid', 'name', 'description', 'location_id', 'connection_mode', 'scheme',
        'fqdn', 'daemon_port', 'sftp_port', 'behind_proxy', 'memory', 'memory_overallocate',
        'disk', 'disk_overallocate', 'cpu', 'cpu_overallocate', 'upload_size', 'runtimes',
        'public', 'maintenance_mode', 'daemon_base',

        // The middle label of every connection name on this node: lax1 in
        // alpha.lax1.play.scriptgain.com. The wildcard status columns beside it
        // are written by the DNS sync, never posted, so they are not here.
        'dns_label',
    ];

    protected $hidden = ['daemon_token', 'daemon_secret', 'enroll_token'];

    protected function casts(): array
    {
        return [
            'runtimes' => 'array',
            // The plaintext the panel presents when it calls this node.
            // Encrypted at rest: a database leak must not hand over live
            // control of every node.
            'daemon_secret' => 'encrypted',
            'public' => 'boolean',
            'sftp_enabled' => 'boolean',
            'behind_proxy' => 'boolean',
            'maintenance_mode' => 'boolean',
            'last_seen_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'enroll_token_expires_at' => 'datetime',
            'wildcard_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Node $n) {
            $n->uuid ??= (string) Str::uuid();
            $n->runtimes ??= ['docker'];
        });
    }

    // ------------------------------------------------------------ relations

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(NodeMetric::class);
    }

    public function mounts(): BelongsToMany
    {
        return $this->belongsToMany(Mount::class);
    }

    // --------------------------------------------------------------- health

    /**
     * A node is online when its last heartbeat is recent. Deliberately not
     * "did the last request succeed": a node can be busy without being dead,
     * and a panel page must never block on a network call to decide a colour.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds((int) config('node.offline_after', 120)));
    }

    public function statusLabel(): string
    {
        if ($this->maintenance_mode) {
            return 'Maintenance';
        }
        if (! $this->enrolled_at) {
            return 'Awaiting Enrollment';
        }

        return $this->isOnline() ? 'Online' : 'Offline';
    }

    /** slate, emerald, amber or rose, for the status dot. */
    public function statusTone(): string
    {
        return match ($this->statusLabel()) {
            'Online' => 'emerald',
            'Maintenance' => 'amber',
            'Awaiting Enrollment' => 'slate',
            default => 'rose',
        };
    }

    public function supports(string $runtime): bool
    {
        return in_array($runtime, $this->runtimes ?? [], true);
    }

    // ------------------------------------------------------------- capacity

    /** Memory in MiB this node may hand out, including over-allocation. */
    public function memoryCapacity(): int
    {
        return (int) round($this->memory * (1 + $this->memory_overallocate / 100));
    }

    public function diskCapacity(): int
    {
        return (int) round($this->disk * (1 + $this->disk_overallocate / 100));
    }

    public function memoryAllocated(): int
    {
        return (int) $this->servers()->sum('memory');
    }

    public function diskAllocated(): int
    {
        return (int) $this->servers()->sum('disk');
    }

    /**
     * CPU percent this node may hand out, where 100 is one core.
     *
     * cpu_overallocate has been stored and validated since the first import and
     * read by nothing, so the field on the node form changed a number in the
     * database and nothing else. CPU is the one people genuinely want to
     * oversell, because a game server's threads are idle far more often than
     * they are busy.
     */
    public function cpuCapacity(): int
    {
        return (int) round($this->cpu * (1 + $this->cpu_overallocate / 100));
    }

    public function cpuAllocated(): int
    {
        return (int) $this->servers()->sum('cpu');
    }

    public function cpuPressure(): float
    {
        $cap = $this->cpuCapacity();

        return $cap > 0 ? round($this->cpuAllocated() / $cap * 100, 1) : 0;
    }

    /** Percent of allocatable memory already promised to servers. */
    public function memoryPressure(): float
    {
        $cap = $this->memoryCapacity();

        return $cap > 0 ? round($this->memoryAllocated() / $cap * 100, 1) : 0;
    }

    public function diskPressure(): float
    {
        $cap = $this->diskCapacity();

        return $cap > 0 ? round($this->diskAllocated() / $cap * 100, 1) : 0;
    }

    /**
     * Can this node take another server of the given size? Used by the create
     * form and by auto placement.
     */
    public function hasRoomFor(int $memoryMib, int $diskMib, int $cpuPercent = 0): bool
    {
        if ($this->maintenance_mode) {
            return false;
        }

        // CPU is checked only when the caller asks about it and the node
        // declares a CPU budget at all. A node left at cpu = 0 means "not
        // tracked", and turning that into "no room for anything" would refuse
        // every placement on every node that has never set it.
        if ($cpuPercent > 0 && $this->cpu > 0
            && $this->cpuAllocated() + $cpuPercent > $this->cpuCapacity()) {
            return false;
        }

        return $this->memoryAllocated() + $memoryMib <= $this->memoryCapacity()
            && $this->diskAllocated() + $diskMib <= $this->diskCapacity();
    }

    public function freeAllocations(): HasMany
    {
        return $this->allocations()->whereNull('server_id');
    }

    // ------------------------------------------------------------------ dns

    /** The wildcard that answers for every server here, or null if unlabelled. */
    public function wildcardName(): ?string
    {
        return (new \App\Services\Dns\NameAllocator)->wildcardName($this);
    }

    /**
     * The IPv4 address the wildcard should point at.
     *
     * The node's hostname first, when it is already an address, because that is
     * the one an operator typed and can see. Otherwise the address most of this
     * node's allocations sit on, which is what players are connecting to today.
     * Loopback and unspecified addresses are never a public answer.
     */
    public function dnsTargetIp(): ?string
    {
        if ($this->isUsableIpv4($this->fqdn)) {
            return $this->fqdn;
        }

        $ip = $this->allocations()
            ->selectRaw('ip, COUNT(*) as total')
            ->groupBy('ip')
            ->orderByDesc('total')
            ->pluck('ip')
            ->first(fn ($candidate) => $this->isUsableIpv4($candidate));

        return $ip ?: null;
    }

    /** Plain English for the node page, and the tone of the dot beside it. */
    public function wildcardStatusLabel(): string
    {
        return match ($this->wildcard_status) {
            \App\Services\Dns\WildcardManager::STATUS_ACTIVE => 'Confirmed',
            \App\Services\Dns\WildcardManager::STATUS_DRIFT => 'Wrong Record',
            \App\Services\Dns\WildcardManager::STATUS_FAILED => 'Provider Error',
            \App\Services\Dns\WildcardManager::STATUS_NO_IP => 'No Address',
            \App\Services\Dns\WildcardManager::STATUS_UNLABELLED => 'No Label',
            \App\Services\Dns\WildcardManager::STATUS_DISABLED => 'Turned Off',
            default => 'Never Checked',
        };
    }

    public function wildcardTone(): string
    {
        return match ($this->wildcard_status) {
            \App\Services\Dns\WildcardManager::STATUS_ACTIVE => 'emerald',
            \App\Services\Dns\WildcardManager::STATUS_DRIFT, \App\Services\Dns\WildcardManager::STATUS_FAILED => 'rose',
            \App\Services\Dns\WildcardManager::STATUS_NO_IP, \App\Services\Dns\WildcardManager::STATUS_UNLABELLED => 'amber',
            default => 'slate',
        };
    }

    private function isUsableIpv4(?string $candidate): bool
    {
        if (! filled($candidate) || ! filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        return ! in_array($candidate, ['0.0.0.0', '127.0.0.1'], true)
            && ! str_starts_with($candidate, '127.');
    }

    // ------------------------------------------------------------ transport

    /**
     * Where the panel dials this node.
     *
     * Only ever meaningful for a direct node. The old fallback to 127.0.0.1
     * meant a reverse node, which has no fqdn by design, sent every call to the
     * PANEL's own localhost: a request that either failed confusingly or, on a
     * box running both, reached something it had no business reaching. A node
     * with nowhere to dial now says so.
     */
    public function daemonUrl(string $path = ''): string
    {
        $host = $this->fqdn ?: ($this->connection_mode === 'reverse' ? '' : '127.0.0.1');

        if ($host === '') {
            throw new \RuntimeException(
                'Node "'.$this->name.'" connects out to the panel and has no address to dial. '
                .'This call should have gone through App\\Services\\Node\\Transport.'
            );
        }

        return rtrim($this->scheme.'://'.$host.':'.$this->daemon_port, '/').'/'.ltrim($path, '/');
    }
}
