<?php

namespace App\Models;

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
        'docker_images', 'script_container', 'script_entry', 'data_path', 'script_install',
        'steam_app_id', 'steam_anonymous', 'steam_branch', 'steam_beta_password',
        'lgsm_shortname', 'startup', 'update_command',
        'config_files', 'config_startup', 'config_stop', 'config_logs',
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
            'features' => 'array',
            'file_denylist' => 'array',
            'mod_sources' => 'array',
            'steam_anonymous' => 'boolean',
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
}
