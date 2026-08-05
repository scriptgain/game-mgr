<?php

namespace App\Http\Controllers\Client;

use App\Models\Player;
use App\Models\PlayerEvent;
use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Http\Request;

/**
 * Players.
 *
 * Pterodactyl has no idea who is on a server. To kick somebody you open the
 * console and type the command yourself, correctly, from memory. Here the
 * player list is real data with buttons on it, and every action is recorded.
 */
class PlayerController extends ServerController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'player.read');

        $query = $server->players();
        if ($search = $request->query('q')) {
            $query->where('name', 'like', '%'.$search.'%')->orWhere('identifier', 'like', '%'.$search.'%');
        }

        return view('server.players', [
            'title' => $server->name.' Players',
            'server' => $server->load('node', 'template'),
            'online' => $server->players()->where('is_online', true)->orderBy('name')->get(),
            'players' => $query->orderByDesc('last_seen_at')->limit(200)->get(),
            'events' => $server->playerEvents()->with('player')->limit(30)->get(),
            'search' => $search,
        ]);
    }

    public function kick(Request $request, Server $server, Player $player)
    {
        $this->guard($server, 'player.kick');
        abort_unless($player->server_id === $server->id, 404);

        $reason = (string) $request->input('reason', 'Kicked by an operator');
        $this->rcon($server, 'kick '.$player->name.' '.$reason);

        $player->update(['is_online' => false]);
        $this->event($server, $player, 'kick', $reason);
        $this->log($server, 'player.kick', 'Kicked '.$player->name);

        return back()->with('status', $player->name.' kicked.');
    }

    public function ban(Request $request, Server $server, Player $player)
    {
        $this->guard($server, 'player.ban');
        abort_unless($player->server_id === $server->id, 404);

        $reason = (string) $request->input('reason', 'Banned by an operator');
        $this->rcon($server, 'ban '.$player->name.' '.$reason);

        $player->update(['is_banned' => true, 'is_online' => false, 'ban_reason' => $reason]);
        $this->event($server, $player, 'ban', $reason);
        $this->log($server, 'player.ban', 'Banned '.$player->name);

        return back()->with('status', $player->name.' banned.');
    }

    public function unban(Server $server, Player $player)
    {
        $this->guard($server, 'player.ban');
        abort_unless($player->server_id === $server->id, 404);

        $this->rcon($server, 'pardon '.$player->name);

        $player->update(['is_banned' => false, 'ban_reason' => null]);
        $this->event($server, $player, 'unban', null);
        $this->log($server, 'player.unban', 'Unbanned '.$player->name);

        return back()->with('status', $player->name.' unbanned.');
    }

    public function whitelist(Server $server, Player $player)
    {
        $this->guard($server, 'player.manage');
        abort_unless($player->server_id === $server->id, 404);

        $on = ! $player->is_whitelisted;
        $this->rcon($server, ($on ? 'whitelist add ' : 'whitelist remove ').$player->name);
        $player->update(['is_whitelisted' => $on]);
        $this->log($server, 'player.whitelist', ($on ? 'Whitelisted ' : 'Removed from the whitelist: ').$player->name);

        return back()->with('status', $player->name.($on ? ' whitelisted.' : ' removed from the whitelist.'));
    }

    public function op(Server $server, Player $player)
    {
        $this->guard($server, 'player.manage');
        abort_unless($player->server_id === $server->id, 404);

        $on = ! $player->is_op;
        $this->rcon($server, ($on ? 'op ' : 'deop ').$player->name);
        $player->update(['is_op' => $on]);
        $this->log($server, 'player.op', ($on ? 'Gave operator to ' : 'Removed operator from ').$player->name);

        return back()->with('status', $player->name.($on ? ' is now an operator.' : ' is no longer an operator.'));
    }

    // ------------------------------------------------------------ internals

    /**
     * Best effort. The database is updated either way, because an offline
     * server still needs its ban list to be right when it next starts.
     */
    private function rcon(Server $server, string $command): void
    {
        if ($server->power_state === 'running') {
            NodeClient::for($server->node)->command($server, $command);
        }
    }

    private function event(Server $server, Player $player, string $event, ?string $detail): void
    {
        PlayerEvent::create([
            'server_id' => $server->id,
            'player_id' => $player->id,
            'event' => $event,
            'detail' => $detail,
            'occurred_at' => now(),
        ]);
    }
}
