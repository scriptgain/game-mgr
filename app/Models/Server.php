<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A game server. Lives on exactly one node, built from exactly one template,
 * owned by exactly one user, and shared with any number of subusers.
 *
 * Runtime, image and startup are copied off the template at create time rather
 * than read through the relation, so editing a template can never silently
 * re-point a running server underneath its owner.
 */
class Server extends Model
{
    use Concerns\Auditable;

    protected $fillable = [
        'uuid', 'uuid_short', 'name', 'description', 'owner_id', 'node_id',
        'template_id', 'allocation_id', 'runtime', 'image', 'startup',
        'memory', 'swap', 'disk', 'io', 'cpu', 'threads', 'oom_disabled',
        'database_limit', 'allocation_limit', 'backup_limit', 'status',
        'installed_at', 'auto_restart', 'auto_update',

        // Reported by the node daemon, never sourced from a request: no
        // controller validates these and no form posts them. They were left out
        // originally, which meant every write of them was silently discarded and
        // the panel showed the seeded power state forever.
        'power_state', 'stopped_intentionally', 'last_started_at', 'last_crashed_at',
        'cached_cpu', 'cached_memory', 'cached_disk', 'cached_players',
        'cached_max_players', 'cached_at',
    ];

    protected function casts(): array
    {
        return [
            'oom_disabled' => 'boolean',
            'auto_restart' => 'boolean',
            'stopped_intentionally' => 'boolean',
            'auto_update' => 'boolean',
            'installed_at' => 'datetime',
            'last_started_at' => 'datetime',
            'last_crashed_at' => 'datetime',
            'cached_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Server $s) {
            $s->uuid ??= (string) Str::uuid();
            $s->uuid_short ??= substr(str_replace('-', '', $s->uuid), 0, 8);
        });
    }

    /**
     * Client URLs read /server/{uuid_short}. The short id is stable, short
     * enough to type, and does not leak the full daemon-side identity into a
     * browser history or a support screenshot.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid_short';
    }

    // ------------------------------------------------------------ relations

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(ServerVariable::class);
    }

    public function subusers(): HasMany
    {
        return $this->hasMany(Subuser::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(ServerDatabase::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class)->latest('id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ServerMetric::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function playerEvents(): HasMany
    {
        return $this->hasMany(PlayerEvent::class)->latest('occurred_at');
    }

    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class);
    }

    public function worlds(): HasMany
    {
        return $this->hasMany(World::class);
    }

    public function watchdogRules(): HasMany
    {
        return $this->hasMany(WatchdogRule::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class)->latest('id');
    }

    public function statusPage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StatusPage::class);
    }

    public function mounts(): BelongsToMany
    {
        return $this->belongsToMany(Mount::class);
    }

    public function activity(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest('id');
    }

    // ----------------------------------------------------------- state

    public function isInstalling(): bool
    {
        return $this->status === 'installing';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** Can the owner press Start right now? */
    public function isControllable(): bool
    {
        return $this->status === null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'installing' => 'Installing',
            'install_failed' => 'Install Failed',
            'suspended' => 'Suspended',
            'restoring' => 'Restoring',
            'transferring' => 'Transferring',
            default => match ($this->power_state) {
                'running' => 'Running',
                'starting' => 'Starting',
                'stopping' => 'Stopping',
                default => 'Offline',
            },
        };
    }

    public function statusTone(): string
    {
        return match ($this->statusLabel()) {
            'Running' => 'emerald',
            'Starting', 'Stopping', 'Installing', 'Restoring', 'Transferring' => 'amber',
            'Install Failed', 'Suspended' => 'rose',
            default => 'slate',
        };
    }

    // ------------------------------------------------------------ addresses

    public function address(): string
    {
        return $this->allocation?->address() ?? 'Not Allocated';
    }

    /** SFTP login the client area shows. */
    public function sftpUsername(): string
    {
        return Str::slug($this->owner?->name ?? 'user').'.'.$this->uuid_short;
    }

    // ------------------------------------------------------------- capacity

    public function memoryPercent(): float
    {
        return $this->memory > 0
            ? min(100, round($this->cached_memory / $this->memory * 100, 1))
            : 0;
    }

    public function diskPercent(): float
    {
        return $this->disk > 0
            ? min(100, round($this->cached_disk / $this->disk * 100, 1))
            : 0;
    }

    public function cpuPercent(): float
    {
        return $this->cpu > 0
            ? min(100, round($this->cached_cpu / $this->cpu * 100, 1))
            : 0;
    }

    // ---------------------------------------------------------- permissions

    /** The full environment handed to the daemon: template defaults plus overrides. */
    public function environment(): array
    {
        $env = [];
        foreach ($this->template?->variables ?? [] as $var) {
            $env[$var->env_variable] = $var->default_value;
        }
        foreach ($this->variables as $set) {
            if ($set->variable) {
                $env[$set->variable->env_variable] = $set->value;
            }
        }
        $env['SERVER_MEMORY'] = (string) $this->memory;
        $env['SERVER_IP'] = (string) ($this->allocation?->ip ?? '0.0.0.0');
        $env['SERVER_PORT'] = (string) ($this->allocation?->port ?? 0);

        return $env;
    }

    /** Everything the node daemon needs to act on this server. */
    public function daemonPayload(): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'runtime' => $this->runtime,
            'image' => $this->image,
            'startup' => $this->startup,
            'stop_command' => $this->template?->stopCommand() ?? 'stop',
            'data_path' => $this->template?->data_path ?: '/home/container',
            'environment' => $this->environment(),
            'memory_mib' => (int) $this->memory,
            'disk_mib' => (int) $this->disk,
            'cpu_percent' => (int) $this->cpu,
            'ip' => $this->allocation?->ip ?? '127.0.0.1',
            'port' => (int) ($this->allocation?->port ?? 0),
            'steam_app_id' => (int) ($this->template?->steam_app_id ?? 0),
            'steam_anonymous' => (bool) ($this->template?->steam_anonymous ?? true),
            'lgsm_shortname' => (string) ($this->template?->lgsm_shortname ?? ''),
        ];
    }
}
