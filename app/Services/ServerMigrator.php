<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Node;
use App\Models\Server;
use App\Support\Edition;
use Illuminate\Support\Str;

/**
 * Moves a server to another node, picking up a new address on the way.
 *
 * Backup and restore is the transport. Store.Backup and Store.Restore already
 * stream a tar, checksum it, and put the old state back if an unpack fails, so
 * this uses the machinery that has been proven rather than writing a second
 * node-to-node copy with its own failure modes.
 *
 * THE ORDER IS THE DESIGN. Every step is undoable until the last one, and the
 * source is never destroyed until the target is known good. A migration that
 * removes the world and then fails to land it has destroyed the thing it was
 * moving, which is worse than never having tried.
 */
class ServerMigrator
{
    public function __construct(private AllocationPlanner $planner) {}

    /**
     * Check everything that can be checked before anything moves.
     *
     * Returns null when the migration may go ahead, or the reason it may not.
     * Called both by the caller wanting a preflight and by migrate() itself, so
     * the answer cannot differ between asking and doing.
     */
    public function reasonItCannotRun(Server $server, Node $target): ?string
    {
        if ($server->node_id === $target->id) {
            return 'That server is already on that node.';
        }
        if ($server->isSuspended()) {
            return 'That server is suspended. Unsuspend it first.';
        }
        if ($server->status === 'installing' || $server->status === 'migrating') {
            return 'That server is busy. Wait for it to finish.';
        }
        if ($server->power_state !== 'offline') {
            return 'Stop the server first. A migration copies its files, and copying them while it is writing to them produces a world nobody wants.';
        }
        if (! $target->supports($server->runtime)) {
            return $target->name.' cannot run '.$server->runtime.' servers.';
        }
        if ($target->maintenance_mode) {
            return $target->name.' is in maintenance mode.';
        }
        if (! $target->hasRoomFor((int) $server->memory, (int) $server->disk, (int) $server->cpu)) {
            return $target->name.' does not have room for a server that size.';
        }
        if (! Edition::allowsTemplate($server->template)) {
            return 'This edition cannot deploy that template.';
        }

        // Checked last because it is the most expensive question, and because a
        // node with no free address is a fixable problem rather than a refusal.
        if (! $this->planner->plan($target, $server->template)) {
            return $target->name.' has no free address that can take every port this server needs.';
        }

        return null;
    }

    /**
     * Do it. Returns true when the server now lives on the target.
     *
     * $log receives progress lines, so the caller can stream them or write them
     * to a job record.
     */
    public function migrate(Server $server, Node $target, ?callable $log = null): bool
    {
        $say = $log ?? fn () => null;

        if ($reason = $this->reasonItCannotRun($server, $target)) {
            $say('error', $reason);

            return false;
        }

        $source = $server->node;
        $backupUuid = (string) Str::uuid();

        $server->forceFill(['status' => 'migrating'])->save();
        AuditLog::record('server.migrate', 'Started moving "'.$server->name.'" from '.$source->name.' to '.$target->name, $server, $server->id);

        try {
            // 1. Reserve on the target FIRST. If the ports cannot be had, the
            //    migration ends here having changed nothing at all.
            $say('line', 'Reserving ports on '.$target->name);
            $plan = $this->planner->plan($target, $server->template);
            if (! $plan) {
                throw new \RuntimeException($target->name.' has no free address that can take every port this server needs.');
            }

            // 2. Back up on the source. Still nothing destroyed.
            $say('line', 'Backing up on '.$source->name);
            $backup = NodeClient::for($source)->backup($server, $backupUuid);
            if (! $backup) {
                throw new \RuntimeException('The backup on '.$source->name.' failed, so nothing was moved.');
            }

            // 3. Move the row to the target and reserve its new ports. The old
            //    allocations are deliberately still held at this point: if the
            //    restore fails, they are what the server goes back to.
            $say('line', 'Restoring on '.$target->name);
            $oldAllocations = $server->allocations()->pluck('id')->all();
            $oldNodeId = $server->node_id;
            $oldAllocationId = $server->allocation_id;

            $server->forceFill(['node_id' => $target->id])->save();
            $primary = $this->planner->reserve($server, $plan);
            if ($primary) {
                $server->forceFill(['allocation_id' => $primary->id])->save();
            }

            // 4. Carry the archive across and unpack it.
            if (! NodeClient::for($target)->restore($server, $backupUuid)) {
                // Put the row back exactly as it was before touching anything
                // on the source.
                $server->forceFill([
                    'node_id' => $oldNodeId,
                    'allocation_id' => $oldAllocationId,
                    'status' => null,
                ])->save();
                $server->allocations()->whereNotIn('id', $oldAllocations)
                    ->update(['server_id' => null, 'role' => null]);

                throw new \RuntimeException('The restore on '.$target->name.' failed. The server has been left where it was.');
            }

            // 5. Only now is the source expendable. Free its ports and tell it
            //    to remove the container and the files.
            $say('line', 'Removing the old copy from '.$source->name);
            $server->allocations()->whereIn('id', $oldAllocations)
                ->update(['server_id' => null, 'role' => null, 'protocol' => 'both']);

            $sourceCopy = $server->replicate();
            $sourceCopy->node_id = $oldNodeId;
            $sourceCopy->id = $server->id;
            $sourceCopy->exists = true;
            NodeClient::for($source)->destroy($sourceCopy);

            $server->forceFill(['status' => null])->save();

            AuditLog::record('server.migrate',
                'Moved "'.$server->name.'" to '.$target->name.'. Its address is now '.$server->fresh()->address(),
                $server, $server->id);

            $say('done', 'Moved to '.$target->name.'. The address is now '.$server->fresh()->address().'.');

            return true;
        } catch (\Throwable $e) {
            $server->forceFill(['status' => null])->save();
            AuditLog::record('server.migrate.failed',
                'Moving "'.$server->name.'" failed: '.$e->getMessage(), $server, $server->id);
            $say('error', $e->getMessage());

            return false;
        }
    }
}
