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
            'behind_proxy' => 'boolean',
            'maintenance_mode' => 'boolean',
            'last_seen_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'enroll_token_expires_at' => 'datetime',
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
    public function hasRoomFor(int $memoryMib, int $diskMib): bool
    {
        if ($this->maintenance_mode) {
            return false;
        }

        return $this->memoryAllocated() + $memoryMib <= $this->memoryCapacity()
            && $this->diskAllocated() + $diskMib <= $this->diskCapacity();
    }

    public function freeAllocations(): HasMany
    {
        return $this->allocations()->whereNull('server_id');
    }

    // ------------------------------------------------------------ transport

    public function daemonUrl(string $path = ''): string
    {
        $host = $this->fqdn ?: '127.0.0.1';

        return rtrim($this->scheme.'://'.$host.':'.$this->daemon_port, '/').'/'.ltrim($path, '/');
    }
}
