<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\PlayerResource;
use App\Models\Server;
use App\Models\AuditLog;
use App\Services\NodeClient;
use Illuminate\Http\Request;

/**
 * Player for one server, as its owner sees them.
 *
 * Guarded by player.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class PlayerController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'player.read');

        return $this->paginate($request, $server->players(), PlayerResource::class);
    }

    /**
     * Kick, ban, unban and the rest go through the game itself, so they need a
     * running server and RCON. Refusing early is kinder than a command that
     * vanishes into a stopped process.
     */
    public function action(Request $request, Server $server, $player)
    {
        $data = $request->validate([
            'action' => ['required', 'in:kick,ban,unban,whitelist,unwhitelist,op,deop'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->guard($server, match ($data['action']) {
            'kick' => 'player.kick',
            'ban', 'unban' => 'player.ban',
            default => 'player.manage',
        });

        abort_unless($server->power_state === 'running', 409, 'That server is not running.');

        $record = $server->players()->findOrFail($player);
        $command = match ($data['action']) {
            'kick' => 'kick '.$record->name.' '.($data['reason'] ?? ''),
            'ban' => 'ban '.$record->name.' '.($data['reason'] ?? ''),
            'unban' => 'pardon '.$record->name,
            'whitelist' => 'whitelist add '.$record->name,
            'unwhitelist' => 'whitelist remove '.$record->name,
            'op' => 'op '.$record->name,
            'deop' => 'deop '.$record->name,
        };

        $ok = NodeClient::for($server->node)->command($server, trim($command));
        AuditLog::record('player.'.$data['action'], $data['action'].' '.$record->name.' on "'.$server->name.'" over the API', $server, $server->id);

        return $ok ? $this->done() : response()->json(['message' => 'The node did not accept that command.'], 502);
    }
}
