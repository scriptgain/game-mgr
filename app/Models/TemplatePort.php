<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One listener a game needs.
 *
 * A template owns a set of these, and the set is what gets reserved when a
 * server is created. Everything that used to be inferred from arithmetic on a
 * single port lives here as a declared fact instead.
 *
 * `source` decides how the number is worked out:
 *
 *   fixed   the port is exactly what `port` says, which is what a canonical
 *           port is: Palworld's RCON is 25575 because that is the number the
 *           game's own documentation and every guide uses.
 *   offset  the port is the game port plus `port_offset`, which is right for
 *           games that genuinely define it that way. Valheim answers A2S on the
 *           game port plus one, whatever the game port turns out to be.
 *
 * Both kinds move together when the allocator has to shift a set off its
 * canonical position, so the relative layout a game expects survives.
 */
class TemplatePort extends Model
{
    /** Roles the panel knows how to reason about. Anything else is an extra. */
    public const ROLES = [
        'game' => 'Game Port',
        'query' => 'Query Port',
        'rcon' => 'RCON Port',
        'sftp' => 'SFTP Port',
    ];

    public const PROTOCOLS = [
        'tcp' => 'TCP',
        'udp' => 'UDP',
        'both' => 'TCP And UDP',
    ];

    protected $fillable = [
        'template_id', 'role', 'label', 'protocol', 'source',
        'port', 'port_offset', 'required', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'port_offset' => 'integer',
            'required' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * The number this row resolves to, given the game port it hangs off.
     *
     * The game row resolves to itself: it is always fixed, because "the game
     * port offset from the game port" is not a thing anyone can act on.
     */
    public function resolve(int $gamePort): int
    {
        if ($this->source === 'offset') {
            return $gamePort + (int) $this->port_offset;
        }

        return (int) $this->port;
    }

    public function isGame(): bool
    {
        return $this->role === 'game';
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ($this->label ?: \Illuminate\Support\Str::headline($this->role));
    }

    public function protocolLabel(): string
    {
        return self::PROTOCOLS[$this->protocol] ?? mb_strtoupper((string) $this->protocol);
    }

    /** How the number is arrived at, in words, for the template pages. */
    public function derivationLabel(): string
    {
        if ($this->source !== 'offset') {
            return 'Fixed';
        }
        if ((int) $this->port_offset === 0) {
            return 'Game Port';
        }

        return 'Game Port '.((int) $this->port_offset > 0 ? '+' : '').$this->port_offset;
    }
}
