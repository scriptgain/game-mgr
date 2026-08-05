<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Two kinds of account. An admin sees the whole panel: nodes, templates, every
 * server, every setting. A client only sees servers they own or have been
 * invited to as a subuser, and never sees that the admin area exists.
 *
 * root_admin is the account that cannot be demoted or deleted, so an install
 * can never lock itself out by mistake.
 */
#[Fillable(['name', 'email', 'password', 'role', 'timezone', 'suspended'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable
{
    use Concerns\Auditable;
    use Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRootAdmin(): bool
    {
        return (bool) $this->root_admin;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && ! empty($this->two_factor_secret);
    }

    // ------------------------------------------------------------ relations

    /** Servers this user owns outright. */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'owner_id');
    }

    /** Server invitations this user holds. */
    public function subusers(): HasMany
    {
        return $this->hasMany(Subuser::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * Every server this account can open, owned or shared. Admins get the lot,
     * which is what makes the same client screens usable for support without a
     * separate impersonation flow.
     */
    public function accessibleServers()
    {
        if ($this->isAdmin()) {
            return Server::query();
        }

        return Server::query()->where(function ($q) {
            $q->where('owner_id', $this->id)
                ->orWhereIn('id', Subuser::where('user_id', $this->id)->select('server_id'));
        });
    }

    public function initials(): string
    {
        return \Illuminate\Support\Str::of($this->name ?: 'User')
            ->explode(' ')->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'root_admin' => 'boolean',
            'suspended' => 'boolean',
        ];
    }
}
