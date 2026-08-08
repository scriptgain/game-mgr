<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user granted partial access to somebody else's server.
 *
 * Permission strings mirror Pterodactyl's namespaces on purpose, so an operator
 * moving across already knows what control.console means. player.*, mod.* and
 * world.* are GameMGR additions with no Pterodactyl equivalent.
 */
class Subuser extends Model
{
    protected $fillable = ['server_id', 'user_id', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    /**
     * The permission matrix rendered on the Users tab, grouped for the UI.
     * Each entry is [key => description].
     */
    public const MATRIX = [
        'Control' => [
            'control.console' => 'View the console output',
            'control.command' => 'Send commands to the console',
            'control.start' => 'Start the server',
            'control.stop' => 'Stop the server',
            'control.restart' => 'Restart the server',
        ],
        'Files' => [
            'file.read' => 'Browse and download files',
            'file.create' => 'Create files and folders',
            'file.update' => 'Edit and rename files',
            'file.delete' => 'Delete files',
            'file.archive' => 'Compress and extract archives',
            'file.sftp' => 'Connect over SFTP',
        ],
        'Backups' => [
            'backup.read' => 'See the backup list',
            'backup.create' => 'Take a backup',
            'backup.delete' => 'Delete a backup',
            'backup.download' => 'Download a backup',
            'backup.restore' => 'Restore a backup',
        ],
        'Databases' => [
            'database.read' => 'See databases',
            'database.create' => 'Create a database',
            'database.update' => 'Rotate a password',
            'database.delete' => 'Delete a database',
        ],
        'Configuration' => [
            'config.read' => 'See the game configuration editor',
            'config.update' => 'Change game configuration',
        ],
        'Schedules' => [
            'schedule.read' => 'See schedules',
            'schedule.create' => 'Create a schedule',
            'schedule.update' => 'Edit a schedule',
            'schedule.delete' => 'Delete a schedule',
        ],
        'Players' => [
            'player.read' => 'See the player list',
            'player.kick' => 'Kick a player',
            'player.ban' => 'Ban and unban players',
            'player.manage' => 'Manage ops and the whitelist',
        ],
        'Mods' => [
            'mod.read' => 'See installed mods',
            'mod.install' => 'Install a mod or plugin',
            'mod.update' => 'Update a mod',
            'mod.delete' => 'Remove a mod',
        ],
        'Worlds' => [
            'world.read' => 'See worlds and saves',
            'world.switch' => 'Change the active world',
            'world.upload' => 'Upload a world',
            'world.delete' => 'Delete a world',
        ],
        'Network' => [
            'allocation.read' => 'See allocated ports',
            'allocation.create' => 'Add an allocation',
            'allocation.update' => 'Set the primary allocation',
            'allocation.delete' => 'Remove an allocation',
        ],
        'Settings' => [
            'settings.rename' => 'Rename the server',
            'settings.reinstall' => 'Reinstall the server',
            'startup.read' => 'See startup variables',
            'startup.update' => 'Change startup variables',
            'user.read' => 'See other subusers',
            'user.create' => 'Invite a subuser',
            'user.update' => 'Change a subuser',
            'user.delete' => 'Remove a subuser',
            'activity.read' => 'See the activity log',
        ],
    ];

    /** Every permission key, flattened. */
    public static function allPermissions(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::MATRIX)));
    }

    /** A sensible starting set for a newly invited subuser. */
    public static function defaultPermissions(): array
    {
        return ['control.console', 'control.start', 'control.restart', 'file.read', 'backup.read', 'player.read'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
