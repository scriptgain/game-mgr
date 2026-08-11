<?php

namespace App\Models;

use App\Support\ConfigFile;
use App\Support\McJarsPicker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A server template. Pterodactyl calls this an "Egg".
 *
 * The important difference is `runtime`. A Pterodactyl egg can only ever
 * describe a Docker container. A GameMGR template says how it wants to be
 * installed and supervised: as a container, as a native SteamCMD install, or
 * through LinuxGSM. Everything else on this model is deliberately shaped like
 * the egg format so community eggs import without translation.
 */
class Template extends Model
{
    use Concerns\Auditable;

    public const RUNTIMES = [
        'docker' => 'Docker Container',
        'steamcmd' => 'SteamCMD Native',
        'linuxgsm' => 'LinuxGSM',
    ];

    protected $fillable = [
        'uuid', 'game_id', 'name', 'author', 'description', 'runtime',
        'default_port', 'default_protocol',
        'docker_images', 'script_container', 'script_entry', 'data_path', 'script_install',
        'steam_app_id', 'steam_anonymous', 'requires_steam_account', 'steam_branch', 'steam_beta_password', 'curseforge_game_id',
        'lgsm_shortname', 'startup', 'update_command',
        'config_files', 'config_startup', 'config_stop', 'config_logs', 'config_schema', 'mcjars',
        'features', 'file_denylist', 'force_outgoing_ip',
        'rcon_supported', 'rcon_protocol', 'query_protocol',
        'rcon_port_offset', 'query_port_offset', 'mod_sources',
        'imported_from', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'docker_images' => 'array',
            'config_files' => 'array',
            'config_startup' => 'array',
            'config_stop' => 'array',
            'config_logs' => 'array',
            'config_schema' => 'array',
            'mcjars' => 'array',
            'features' => 'array',
            'file_denylist' => 'array',
            'mod_sources' => 'array',
            'steam_anonymous' => 'boolean',
            'requires_steam_account' => 'boolean',
            'rcon_supported' => 'boolean',
            'force_outgoing_ip' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Template $t) {
            $t->uuid ??= (string) Str::uuid();
        });
    }

    // ------------------------------------------------------------ relations

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Every listener this game needs, game port first.
     *
     * This is the source of truth for ports. default_port, default_protocol and
     * the two offset columns are now a mirror of it, kept in step by
     * syncPortColumns() so the older readers keep working.
     */
    public function ports(): HasMany
    {
        return $this->hasMany(TemplatePort::class)->orderBy('sort')->orderBy('id');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function mounts(): BelongsToMany
    {
        return $this->belongsToMany(Mount::class);
    }

    // -------------------------------------------------------------- helpers

    public function runtimeLabel(): string
    {
        return self::RUNTIMES[$this->runtime] ?? ucfirst((string) $this->runtime);
    }

    /** The first docker image, which is the default offered on create. */
    public function defaultImage(): ?string
    {
        $images = $this->docker_images ?: [];

        return $images ? (string) reset($images) : null;
    }

    /**
     * The regex or literal the daemon watches for to decide a server finished
     * booting. Pterodactyl stores this under config_startup.done.
     */
    public function doneMarker(): ?string
    {
        return $this->config_startup['done'] ?? null;
    }

    public function stopCommand(): string
    {
        $stop = $this->config_stop ?? [];
        if (is_array($stop)) {
            return (string) ($stop['value'] ?? 'stop');
        }

        return (string) $stop;
    }

    /** Did this template come in from a Pterodactyl egg? */
    public function wasImported(): bool
    {
        return $this->imported_at !== null;
    }

    public function supportsMods(): bool
    {
        return ! empty($this->mod_sources);
    }

    /**
     * The config files this template lets a customer edit, as objects.
     *
     * @return array<int,ConfigFile>
     */
    public function configFiles(): array
    {
        $out = [];

        foreach ($this->config_schema ?? [] as $index => $file) {
            if (is_array($file) && ! empty($file['file'])) {
                $out[] = ConfigFile::fromArray($file, (int) $index);
            }
        }

        return $out;
    }

    /** Does this template have a Config tab at all? */
    public function hasConfigSchema(): bool
    {
        return $this->configFiles() !== [];
    }

    // ------------------------------------------------------------- minecraft

    /**
     * The MCJars type and version picker for this template, or null when it is
     * not a Minecraft Java template.
     *
     * Carrying an `mcjars` document is the ONLY thing that makes a template
     * Minecraft as far as the panel is concerned. Nothing infers it from the
     * game name or the image, so Palworld cannot grow a Minecraft version
     * picker by being filed under the wrong game.
     */
    public function mcjarsPicker(): ?McJarsPicker
    {
        if (! is_array($this->mcjars) || $this->mcjars === []) {
            return null;
        }

        // The picker walks the variables, so they have to be loaded. Left as a
        // lazy load rather than a required eager one: every caller already
        // eager loads them, and a template fetched on its own should still
        // answer this correctly rather than silently say "not Minecraft".
        $this->loadMissing('variables');

        return McJarsPicker::for($this);
    }

    // ---------------------------------------------------------------- ports

    /** The row every other port hangs off, or null when nobody declared one. */
    public function gamePortRow(): ?TemplatePort
    {
        return $this->ports->firstWhere('role', 'game');
    }

    /**
     * The canonical game port: the number this game is reached on when nothing
     * is in the way. 8211 for Palworld, 25565 for Minecraft Java, every time.
     */
    public function canonicalGamePort(): ?int
    {
        $row = $this->gamePortRow();

        return $row?->port ?: ($this->default_port ?: null);
    }

    /** Has anyone declared what this template listens on? */
    public function hasPortSet(): bool
    {
        return $this->canonicalGamePort() !== null && $this->ports->isNotEmpty();
    }

    /**
     * The whole port set at its canonical position, collapsed to one entry per
     * distinct port number.
     *
     * Collapsing is not tidiness, it is correctness. CS2 wants its game
     * traffic, its A2S queries and its RCON all on 27015, and allocations are
     * unique on (node_id, ip, port), so those three declarations have to become
     * one reservation carrying every role and the union of their protocols.
     * Minecraft Java is the same story with two: TCP for the game and UDP for
     * the query, on 25565, which is one allocation that has to be open on both.
     *
     * @param  int  $shift  Move the entire set by this many ports. Uniform on
     *                      purpose: a set shifted piecemeal is not the layout
     *                      any game documents.
     * @return array<int, array{port:int, protocol:string, roles:array<int,string>, required:bool, labels:array<int,string>}>
     */
    public function portSet(int $shift = 0): array
    {
        $gamePort = $this->canonicalGamePort();
        if ($gamePort === null) {
            return [];
        }

        $byPort = [];

        foreach ($this->ports as $row) {
            $port = ($row->isGame() ? $gamePort : $row->resolve($gamePort)) + $shift;
            if ($port < 1 || $port > 65535) {
                continue;
            }

            if (! isset($byPort[$port])) {
                $byPort[$port] = [
                    'port' => $port,
                    'protocol' => $row->protocol,
                    'roles' => [],
                    'labels' => [],
                    'required' => false,
                ];
            }

            $byPort[$port]['roles'][] = $row->role;
            $byPort[$port]['labels'][] = $row->label ?: $row->roleLabel();
            $byPort[$port]['required'] = $byPort[$port]['required'] || $row->required;
            $byPort[$port]['protocol'] = self::mergeProtocols($byPort[$port]['protocol'], $row->protocol);
        }

        // Game port first so the primary allocation is obvious, then ascending.
        uasort($byPort, function (array $a, array $b) {
            $ga = in_array('game', $a['roles'], true) ? 0 : 1;
            $gb = in_array('game', $b['roles'], true) ? 0 : 1;

            return $ga === $gb ? $a['port'] <=> $b['port'] : $ga <=> $gb;
        });

        return array_values($byPort);
    }

    /** TCP asked for alongside UDP is a port that has to be open on both. */
    public static function mergeProtocols(string $a, string $b): string
    {
        return $a === $b ? $a : 'both';
    }

    /**
     * Write the port set back into the four columns that predate it.
     *
     * BootstrapNode generates its starter allocations from templates.default_port,
     * the template show page reads the two offsets, and neither should have to
     * learn a new table to keep working. They are a cache of the game, query and
     * rcon rows and are refreshed whenever the set is saved.
     */
    public function syncPortColumns(): void
    {
        $ports = $this->relationLoaded('ports') ? $this->ports : $this->ports()->get();
        $game = $ports->firstWhere('role', 'game');

        if (! $game) {
            return;
        }

        $gamePort = (int) $game->port;
        $query = $ports->firstWhere('role', 'query');
        $rcon = $ports->firstWhere('role', 'rcon');

        $this->forceFill([
            'default_port' => $gamePort,
            'default_protocol' => $game->protocol,
            'query_port_offset' => $query ? $query->resolve($gamePort) - $gamePort : 0,
            'rcon_port_offset' => $rcon ? $rcon->resolve($gamePort) - $gamePort : 0,
        ])->save();
    }
}
