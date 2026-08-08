<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\Blueprint;
use App\Models\DatabaseHost;
use App\Models\Game;
use App\Models\Location;
use App\Models\Mod;
use App\Models\Mount;
use App\Models\Node;
use App\Models\NotificationChannel;
use App\Models\Player;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\Subuser;
use App\Models\Template;
use App\Models\User;
use App\Models\WatchdogRule;
use App\Models\World;
use App\Models\Webhook;
use App\Models\AuditLog;
use App\Services\Mods\ModInstaller;
use App\Services\NodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One endpoint for every bulk action in the panel.
 *
 * The alternative is fifteen near-identical controller methods, each with its
 * own idea of who is allowed to do what. This keeps authorisation in a single
 * table you can read in one sitting, which matters far more for a destructive
 * operation than for a normal one.
 *
 * Two rules that are not negotiable:
 *
 *   1. A resource key that is not in the registry is a 403, never a 500 and
 *      never a silent success. A crafted POST must not reach a model nobody
 *      meant to expose.
 *   2. A bulk action can never do something the single-item action would have
 *      refused. Server-scoped resources check the same ServerPolicy permission
 *      string, and every id is verified to belong to that server before
 *      anything is touched.
 */
class BulkActionController extends Controller
{
    /**
     * resource key => [model, permission, [allowed actions], singular noun]
     *
     * The permission is either 'admin' (admin area only) or a ServerPolicy
     * permission string, in which case the resource is server scoped.
     */
    private const ADMIN_RESOURCES = [
        'servers' => [Server::class, ['suspend', 'unsuspend', 'reinstall', 'delete'], 'server'],
        'nodes' => [Node::class, ['maintenance-on', 'maintenance-off', 'public-on', 'public-off', 'delete'], 'node'],
        'alerts' => [Alert::class, ['acknowledge', 'delete'], 'alert'],
        'templates' => [Template::class, ['delete'], 'template'],
        'games' => [Game::class, ['delete'], 'game'],
        'locations' => [Location::class, ['delete'], 'location'],
        'users' => [User::class, ['suspend', 'unsuspend', 'delete'], 'user'],
        'blueprints' => [Blueprint::class, ['delete'], 'blueprint'],
        'mounts' => [Mount::class, ['delete'], 'mount'],
        'database-hosts' => [DatabaseHost::class, ['delete'], 'host'],
        'watchdog' => [WatchdogRule::class, ['enable', 'disable', 'delete'], 'rule'],
        'channels' => [NotificationChannel::class, ['enable', 'disable', 'delete'], 'channel'],
        'webhooks' => [Webhook::class, ['enable', 'disable', 'delete'], 'webhook'],
    ];

    /**
     * resource key => [model, [action => ServerPolicy permission], noun]
     *
     * Deliberately a permission per ACTION, not per resource. The obvious
     * shortcut is one permission for the whole resource, and it silently makes
     * this endpoint the cheapest way past the permission model: a subuser given
     * mod.update so they can toggle a plugin could then bulk delete every mod
     * on the server, because deleting was covered by the same key as toggling.
     *
     * Every string here is the one its single-item controller in
     * App\Http\Controllers\Client already enforces. If the two ever disagree,
     * this file is wrong.
     */
    private const SERVER_RESOURCES = [
        'backups' => [Backup::class, [
            'lock' => 'backup.create',
            'unlock' => 'backup.create',
            'delete' => 'backup.delete',
        ], 'backup'],
        'schedules' => [Schedule::class, [
            'enable' => 'schedule.update',
            'disable' => 'schedule.update',
            'delete' => 'schedule.delete',
        ], 'schedule'],
        'mods' => [Mod::class, [
            'enable' => 'mod.update',
            'disable' => 'mod.update',
            'delete' => 'mod.delete',
        ], 'mod'],
        'worlds' => [World::class, [
            'delete' => 'world.delete',
        ], 'world'],
        'players' => [Player::class, [
            'kick' => 'player.kick',
            'ban' => 'player.ban',
            'unban' => 'player.ban',
            'whitelist' => 'player.manage',
            'unwhitelist' => 'player.manage',
        ], 'player'],
        'allocations' => [Allocation::class, [
            'release' => 'allocation.delete',
        ], 'allocation'],
        'subusers' => [Subuser::class, [
            'delete' => 'user.delete',
        ], 'user'],
        'databases' => [ServerDatabase::class, [
            'delete' => 'database.delete',
        ], 'database'],
    ];

    // ------------------------------------------------------------------ admin

    public function admin(Request $request, string $resource)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless(isset(self::ADMIN_RESOURCES[$resource]), 403, 'That is not something you can act on in bulk.');

        [$model, $allowed, $noun] = self::ADMIN_RESOURCES[$resource];
        [$action, $ids] = $this->input($request, $allowed);

        $rows = $model::whereIn('id', $ids)->get();
        $done = 0;
        $skipped = [];

        foreach ($rows as $row) {
            $result = $this->applyAdmin($resource, $row, $action);
            $result === true ? $done++ : $skipped[] = $result;
        }

        return $this->finish($resource, $action, $done, $noun, $skipped);
    }

    // ----------------------------------------------------------------- server

    public function server(Request $request, Server $server, string $resource)
    {
        abort_unless(isset(self::SERVER_RESOURCES[$resource]), 403, 'That is not something you can act on in bulk.');

        [$model, $permissions, $noun] = self::SERVER_RESOURCES[$resource];

        [$action, $ids] = $this->input($request, array_keys($permissions));

        // Checked AFTER the action is known, because the permission depends on
        // it. Deleting a mod is not the same right as disabling one.
        abort_unless(
            auth()->user()->can('check', [$server, $permissions[$action]]),
            403,
            'Your access to this server does not include that.'
        );

        // Scoped to this server, so ids belonging to somebody else's server are
        // simply not found rather than quietly acted on.
        $rows = $model::whereIn('id', $ids)->where('server_id', $server->id)->get();

        $done = 0;
        $skipped = [];

        foreach ($rows as $row) {
            $result = $this->applyServer($resource, $server, $row, $action);
            $result === true ? $done++ : $skipped[] = $result;
        }

        return $this->finish($resource, $action, $done, $noun, $skipped, $server);
    }

    // -------------------------------------------------------------- internals

    /** @return array{0: string, 1: array<int>} */
    private function input(Request $request, array $allowed): array
    {
        $data = $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        abort_unless(in_array($data['action'], $allowed, true), 403, 'That action is not allowed here.');

        return [$data['action'], $data['ids']];
    }

    /** True on success, or a string explaining why the row was skipped. */
    private function applyAdmin(string $resource, $row, string $action): true|string
    {
        // Guards that mirror the single-item controllers. Skipping with a
        // reason beats either failing the whole batch or pretending it worked.
        if ($resource === 'users') {
            if ($row->isRootAdmin()) {
                return $row->name.' is the root admin';
            }
            if ($row->id === auth()->id()) {
                return 'you cannot act on your own account';
            }
            if ($action === 'delete' && $row->servers()->exists()) {
                return $row->name.' still owns servers';
            }
        }

        if ($action === 'delete') {
            $blocker = match ($resource) {
                'nodes' => $row->servers()->exists() ? $row->name.' still hosts servers' : null,
                'templates' => $row->servers()->exists() ? $row->name.' is still in use' : null,
                'games' => $row->templates()->exists() ? $row->name.' still has templates' : null,
                'locations' => $row->nodes()->exists() ? $row->name.' still has nodes' : null,
                'database-hosts' => $row->databases()->exists() ? $row->name.' still holds databases' : null,
                default => null,
            };
            if ($blocker) {
                return $blocker;
            }
        }

        match ($action) {
            'delete' => $this->deleteRow($resource, $row),
            // Suspending is as deliberate as stopping, so it carries the same
            // marker: a suspended server must not be restarted by the watchdog.
            'suspend' => $row->update($resource === 'users'
                ? ['suspended' => true]
                : ['status' => 'suspended', 'stopped_intentionally' => true]),
            'unsuspend' => $row->update($resource === 'users' ? ['suspended' => false] : ['status' => null]),
            'reinstall' => $row->update(['status' => 'installing', 'installed_at' => null]),
            'maintenance-on' => $row->update(['maintenance_mode' => true]),
            'maintenance-off' => $row->update(['maintenance_mode' => false]),
            'public-on' => $row->update(['public' => true]),
            'public-off' => $row->update(['public' => false]),
            'enable' => $row->update(['is_active' => true]),
            'disable' => $row->update(['is_active' => false]),
            'acknowledge' => $row->update(['acknowledged_at' => now(), 'acknowledged_by' => auth()->id()]),
            default => null,
        };

        return true;
    }

    private function applyServer(string $resource, Server $server, $row, string $action): true|string
    {
        if ($resource === 'backups' && $row->is_locked && $action === 'delete') {
            return $row->name.' is locked';
        }
        if ($resource === 'allocations' && $server->allocation_id === $row->id) {
            return 'that is the primary address';
        }
        if ($resource === 'worlds' && $row->is_active) {
            return $row->name.' is the active world';
        }

        // Mods are files on a node, not rows. Flipping `enabled` here without
        // renaming the jar would leave the panel saying "disabled" while the
        // server carries on loading it, which is the exact lie the Mods tab
        // exists to stop, so the batch goes through the same installer the
        // single-item buttons do.
        if ($resource === 'mods' && in_array($action, ['enable', 'disable', 'delete'], true)) {
            $installer = app(ModInstaller::class);

            $result = $action === 'delete'
                ? $installer->remove($server, $row)
                : $installer->setEnabled($server, $row, $action === 'enable');

            return $result['ok'] === true ? true : (string) ($result['error'] ?? $row->name.' could not be changed');
        }

        match ($action) {
            'delete' => $row->delete(),
            // Allocation::release() also clears the role, so a freed port does
            // not go back to the pool still claiming to be somebody's RCON.
            'release' => $row instanceof Allocation ? $row->release() : $row->update(['server_id' => null]),
            'lock' => $row->update(['is_locked' => true]),
            'unlock' => $row->update(['is_locked' => false]),
            'enable' => $row->update($resource === 'mods' ? ['enabled' => true] : ['is_active' => true]),
            'disable' => $row->update($resource === 'mods' ? ['enabled' => false] : ['is_active' => false]),
            'ban' => $this->rconThen($server, 'ban '.$row->name, fn () => $row->update(['is_banned' => true, 'is_online' => false])),
            'unban' => $this->rconThen($server, 'pardon '.$row->name, fn () => $row->update(['is_banned' => false, 'ban_reason' => null])),
            'kick' => $this->rconThen($server, 'kick '.$row->name, fn () => $row->update(['is_online' => false])),
            'whitelist' => $this->rconThen($server, 'whitelist add '.$row->name, fn () => $row->update(['is_whitelisted' => true])),
            'unwhitelist' => $this->rconThen($server, 'whitelist remove '.$row->name, fn () => $row->update(['is_whitelisted' => false])),
            default => null,
        };

        return true;
    }

    /**
     * Send the command if the server is up, then record it either way. An
     * offline server still needs its ban list correct for when it next starts.
     */
    private function rconThen(Server $server, string $command, callable $then): void
    {
        if ($server->power_state === 'running') {
            NodeClient::for($server->node)->command($server, $command);
        }
        $then();
    }

    private function deleteRow(string $resource, $row): void
    {
        // A server's ports belong to its node, not to the server, so they are
        // released rather than cascaded away.
        if ($resource === 'servers') {
            Allocation::where('server_id', $row->id)->update(['server_id' => null]);
        }

        $row->delete();
    }

    private function finish(string $resource, string $action, int $done, string $noun, array $skipped, ?Server $server = null)
    {
        $verb = str_replace('-', ' ', $action);
        $summary = $done.' '.Str::plural($noun, $done).' '.$this->pastTense($action);

        AuditLog::record(
            'bulk.'.$resource.'.'.$action,
            'Bulk '.$verb.': '.$summary,
            null,
            $server?->id,
            ['count' => $done, 'skipped' => count($skipped)],
        );

        if ($done === 0 && $skipped) {
            return back()->with('error', 'Nothing was changed. '.$this->reasons($skipped));
        }

        $message = ucfirst($summary).'.';
        if ($skipped) {
            // Naming what was skipped and why, rather than reporting a clean
            // success and leaving the operator to notice the difference later.
            return back()->with('warning', $message.' '.$this->reasons($skipped));
        }

        return back()->with('status', $message);
    }

    private function reasons(array $skipped): string
    {
        $unique = array_values(array_unique($skipped));
        $shown = array_slice($unique, 0, 3);
        $rest = count($unique) - count($shown);

        return 'Skipped '.count($skipped).': '.implode('; ', $shown).($rest > 0 ? '; and '.$rest.' more' : '').'.';
    }

    private function pastTense(string $action): string
    {
        return match ($action) {
            'delete' => 'deleted',
            'release' => 'released',
            'suspend' => 'suspended',
            'unsuspend' => 'unsuspended',
            'reinstall' => 'queued for reinstall',
            'maintenance-on' => 'put into maintenance',
            'maintenance-off' => 'taken out of maintenance',
            'public-on' => 'opened to auto placement',
            'public-off' => 'closed to auto placement',
            'enable' => 'enabled',
            'disable' => 'disabled',
            'acknowledge' => 'acknowledged',
            'lock' => 'locked',
            'unlock' => 'unlocked',
            'ban' => 'banned',
            'unban' => 'unbanned',
            'kick' => 'kicked',
            'whitelist' => 'whitelisted',
            'unwhitelist' => 'removed from the whitelist',
            default => $action.'d',
        };
    }
}
