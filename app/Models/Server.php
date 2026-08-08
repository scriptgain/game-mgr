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

    /**
     * ServerController writes "Created server X" with the acting user. The
     * trait's generic "Server X created" is the same event said twice, and the
     * activity feed showed both, one line apart.
     */
    public bool $auditsOwnCreation = true;

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

        // Set by the Config tab when a save lands, so the "restart before this
        // is real" banner can go away by itself once the server restarts.
        'config_dirty_at',

        // The connection name, denormalised. Assigned by the model itself at
        // creation, not by any controller, so every creation path gets one.
        'dns_label', 'connect_name',
    ];

    protected function casts(): array
    {
        return [
            'oom_disabled' => 'boolean',
            'auto_restart' => 'boolean',
            'stopped_intentionally' => 'boolean',
            'auto_update' => 'boolean',
            'installed_at' => 'datetime',
            'install_started_at' => 'datetime',
            'last_started_at' => 'datetime',
            'last_crashed_at' => 'datetime',
            'cached_at' => 'datetime',
            'config_dirty_at' => 'datetime',
        ];
    }

    /**
     * Has config been edited since the game last read it?
     *
     * Games read their config files at boot and never again, so a saved change
     * is not a live change. This is what the Config tab checks before telling
     * somebody their day/night cycle is running at the speed they just typed.
     */
    public function configNeedsRestart(): bool
    {
        if ($this->config_dirty_at === null || $this->power_state === 'offline') {
            return false;
        }

        return $this->last_started_at === null || $this->config_dirty_at->gt($this->last_started_at);
    }

    protected static function booted(): void
    {
        static::creating(function (Server $s) {
            $s->uuid ??= (string) Str::uuid();
            $s->uuid_short ??= substr(str_replace('-', '', $s->uuid), 0, 8);

            // A connection name costs two queries and no network call, because
            // the node's wildcard already answers for it. Doing it here rather
            // than in a controller means the admin form, the client form, the
            // API and any seeder all get one, and none of them can forget.
            (new \App\Services\Dns\WildcardManager)->nameServer($s);
        });

        // A server that moves to another node moves to another name: the
        // wildcard that answers for it belongs to the node it is on. Phase 1
        // accepts that and shows the new name rather than pretending otherwise.
        static::updating(function (Server $s) {
            if ($s->isDirty('node_id')) {
                (new \App\Services\Dns\WildcardManager)->nameServer($s);
            }
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

    /**
     * What can actually be done to this server right now.
     *
     * Every power button used to share one condition, "is this server not
     * installing or suspended", so Start was clickable on a running server and
     * Stop on a stopped one. Pressing them did nothing useful and told the
     * operator nothing, which is worse than the button not being there.
     */
    public function canStart(): bool
    {
        return $this->isControllable() && $this->power_state !== 'running' && $this->power_state !== 'starting';
    }

    public function canStop(): bool
    {
        return $this->isControllable() && in_array($this->power_state, ['running', 'starting'], true);
    }

    /** Restarting something that is not running is just a start with extra steps. */
    public function canRestart(): bool
    {
        return $this->isControllable() && $this->power_state === 'running';
    }

    /** Kill is for a server that has stopped answering, so it needs to be up. */
    public function canKill(): bool
    {
        return $this->isControllable() && $this->power_state !== 'offline';
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

    /**
     * The direct address, and the default everywhere.
     *
     * This is deliberately untouched by the domains feature. It depends on no
     * DNS, no certificate and no third party, so if every name in the panel
     * stops resolving, every server is still reachable exactly as it was. A
     * name is an additional address, never a replacement for this one.
     */
    public function address(): string
    {
        return $this->allocation?->address() ?? 'Not Allocated';
    }

    /**
     * The name a player can type instead, or null when there isn't one.
     *
     * Read straight off the column: no DNS lookup, no string rebuilt out of
     * three relations. Gated on the feature being on, so turning domains off
     * puts every screen back exactly as it was without touching a row.
     */
    public function connectName(): ?string
    {
        if (! \App\Services\Dns\DnsConfig::active()) {
            return null;
        }

        return filled($this->connect_name) ? $this->connect_name : null;
    }

    /** The name with the port on it, which is what a player actually types. */
    public function connectAddress(): ?string
    {
        $name = $this->connectName();

        if ($name === null || ! $this->allocation) {
            return null;
        }

        return $name.':'.$this->allocation->port;
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

    /**
     * What Minecraft software this server is actually set to run, or null when
     * it is not a Minecraft one.
     *
     * Read straight off the environment rather than off the picker, because the
     * environment is what the container is handed. If somebody edits TYPE by
     * hand, through the API or through a blueprint, this says what will really
     * boot rather than what a form last offered.
     *
     * @return array{type:string, version:string, build:?string}|null
     */
    public function minecraft(): ?array
    {
        $picker = $this->template?->mcjarsPicker();

        if (! $picker) {
            return null;
        }

        $env = $this->environment();
        $type = trim((string) ($env[$picker->typeVariable->env_variable] ?? ''));
        $version = trim((string) ($env[$picker->versionVariable->env_variable] ?? ''));

        if ($type === '') {
            return null;
        }

        $buildVariable = $picker->buildVariables[mb_strtoupper($type)] ?? null;
        $build = $buildVariable ? trim((string) ($env[$buildVariable->env_variable] ?? '')) : '';

        return [
            'type' => $type,
            // Blank is meaningful here: the image resolves the newest release
            // when VERSION is unset, and saying "Latest" is more honest than
            // showing an empty cell.
            'version' => $version === '' ? 'Latest' : $version,
            'build' => $build === '' ? null : $build,
        ];
    }

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

        // The reserved query and RCON ports, when this server actually holds
        // them. Startup commands used to derive these with shell arithmetic off
        // SERVER_PORT, which is only right while the set sits on its canonical
        // numbers. Namespaced with SERVER_ so they cannot collide with the
        // RCON_PORT and QUERY_PORT template variables some templates expose.
        foreach ($this->portMap() as $role => $port) {
            if (in_array($role, ['query', 'rcon'], true)) {
                $env['SERVER_'.mb_strtoupper($role).'_PORT'] = (string) $port;
            }
        }

        return $env;
    }

    /**
     * Every port this server holds, keyed by role.
     *
     * One allocation can carry several roles, because several roles genuinely
     * land on one number: CS2 takes game, query and RCON all on 27015.
     *
     * @return array<string, int>
     */
    public function portMap(): array
    {
        $map = [];
        foreach ($this->allocations as $allocation) {
            foreach ($allocation->roles() as $role) {
                $map[$role] ??= (int) $allocation->port;
            }
        }

        return $map;
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
            // Every port this server holds, not just the one players type.
            // The daemon opens exactly these on the firewall, publishes exactly
            // these on the container, and needs the protocol to do either: a
            // Minecraft server wants 25565 open on TCP for play and on UDP for
            // query, and "port 25565" alone cannot say that.
            'ports' => $this->daemonPorts(),
            'steam_app_id' => (int) ($this->template?->steam_app_id ?? 0),
            'steam_anonymous' => (bool) ($this->template?->steam_anonymous ?? true),
            'lgsm_shortname' => (string) ($this->template?->lgsm_shortname ?? ''),
        ];
    }

    /**
     * The port list the daemon acts on, game port first.
     *
     * Shape, because the firewall side of the daemon is built against it:
     *
     *   [{"port":8211,"protocol":"udp","roles":["game"],"primary":true},
     *    {"port":25575,"protocol":"tcp","roles":["rcon"],"primary":false}]
     *
     * protocol is tcp, udp or both. roles is a list because one port can serve
     * several. primary marks the address players connect to, and there is
     * always exactly one of it whenever the server has any allocation at all.
     *
     * @return array<int, array{port:int, protocol:string, roles:array<int,string>, primary:bool}>
     */
    public function daemonPorts(): array
    {
        $primaryId = $this->allocation_id;
        $rows = $this->allocations->sortBy('port')->values();

        $ports = $rows->map(fn (Allocation $a) => [
            'port' => (int) $a->port,
            'protocol' => (string) ($a->protocol ?: 'both'),
            'roles' => $a->roles() ?: ['extra'],
            'primary' => $a->id === $primaryId,
        ])->all();

        // A server whose allocations were never loaded, or that holds only the
        // primary, still has to report something the daemon can open.
        if (! $ports && $this->allocation) {
            $ports = [[
                'port' => (int) $this->allocation->port,
                'protocol' => (string) ($this->allocation->protocol ?: 'both'),
                'roles' => $this->allocation->roles() ?: ['game'],
                'primary' => true,
            ]];
        }

        usort($ports, fn (array $a, array $b) => [! $a['primary'], $a['port']] <=> [! $b['primary'], $b['port']]);

        return $ports;
    }
}
