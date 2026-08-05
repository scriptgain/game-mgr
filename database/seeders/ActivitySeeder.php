<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Blueprint;
use App\Models\Node;
use App\Models\PlayerEvent;
use App\Models\Server;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Activity, alerts, player events and blueprints. This is what makes the
 * dashboard and the Activity tabs look like a panel that has been running for
 * a while rather than one installed thirty seconds ago.
 */
class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $this->activity();
        $this->playerEvents();
        $this->alerts();
        $this->blueprints();
    }

    private function activity(): void
    {
        if (AuditLog::count() > 20) {
            return;
        }

        $admin = User::where('email', 'admin@gamemgr.local')->first();
        $dana = User::where('email', 'client@gamemgr.local')->first();
        $servers = Server::whereNull('status')->get();

        $lines = [
            ['power.start', 'Started the server', $dana],
            ['power.restart', 'Restarted the server', $dana],
            ['file.write', 'Edited server.properties', $dana],
            ['backup.create', 'Took a manual backup', $dana],
            ['player.kick', 'Kicked GravelKing', $dana],
            ['mod.install', 'Installed Chunky 1.4.28', $dana],
            ['schedule.update', 'Changed Nightly Restart to 05:00', $admin],
            ['startup.update', 'Changed MINECRAFT_VERSION to latest', $admin],
            ['power.stop', 'Stopped the server', $admin],
            ['world.switch', 'Switched the active world to season-3-archive', $admin],
        ];

        foreach ($servers as $server) {
            foreach ($lines as $i => [$action, $description, $actor]) {
                $at = now()->subHours($i * 7 + crc32($server->name) % 5);
                AuditLog::create([
                    'user_id' => $actor?->id,
                    'server_id' => $server->id,
                    'action' => $action,
                    'subject_type' => 'Server',
                    'subject_id' => $server->id,
                    'description' => $description,
                    'ip' => '198.51.100.'.(20 + $i),
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }

        // Panel-wide entries with no server, which is what the admin audit log
        // is actually for.
        $panel = [
            ['login', 'Signed in'],
            ['node.created', 'Node "home-nuc-01" enrolled from 10.0.10.24'],
            ['template.imported', 'Imported a Pterodactyl egg as template "Rust Oxide"'],
            ['user.created', 'User "Priya Raman" created'],
            ['settings.update', 'Changed metric history to 30 days'],
        ];
        foreach ($panel as $i => [$action, $description]) {
            $at = now()->subDays($i)->subHours(2);
            AuditLog::create([
                'user_id' => $admin?->id,
                'action' => $action,
                'description' => $description,
                'ip' => '203.0.113.7',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    private function playerEvents(): void
    {
        if (PlayerEvent::exists()) {
            return;
        }

        foreach (Server::whereNull('status')->with('players')->get() as $server) {
            $rows = [];
            foreach ($server->players as $i => $player) {
                $joined = now()->subHours($i * 3 + 1);
                $rows[] = [
                    'server_id' => $server->id, 'player_id' => $player->id,
                    'event' => 'join', 'detail' => null, 'occurred_at' => $joined,
                ];
                if (! $player->is_online) {
                    $rows[] = [
                        'server_id' => $server->id, 'player_id' => $player->id,
                        'event' => 'leave', 'detail' => null,
                        'occurred_at' => $joined->copy()->addMinutes(rand(12, 300)),
                    ];
                }
                if ($player->is_banned) {
                    $rows[] = [
                        'server_id' => $server->id, 'player_id' => $player->id,
                        'event' => 'ban', 'detail' => $player->ban_reason,
                        'occurred_at' => $joined->copy()->addMinutes(45),
                    ];
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                PlayerEvent::insert($chunk);
            }
        }
    }

    private function alerts(): void
    {
        if (Alert::exists()) {
            return;
        }

        $failed = Server::where('status', 'install_failed')->first();
        $nuc = Node::where('name', 'home-nuc-01')->first();
        $ark = Server::where('name', 'Ragnarok ASA')->first();
        $suspended = Server::where('status', 'suspended')->first();

        if ($failed) {
            Alert::create([
                'server_id' => $failed->id,
                'severity' => 'critical',
                'title' => 'Install failed on '.$failed->name,
                'detail' => 'The install script exited 1. Last line: "No space left on device". The node is out of disk.',
                'created_at' => now()->subHours(31),
                'updated_at' => now()->subHours(31),
            ]);
        }

        if ($nuc) {
            Alert::create([
                'node_id' => $nuc->id,
                'severity' => 'warning',
                'title' => 'home-nuc-01 missed three heartbeats',
                'detail' => 'Reverse connection dropped for 9 minutes. Domestic uplink, so this is probably the ISP rather than the node.',
                'created_at' => now()->subMinutes(9),
                'updated_at' => now()->subMinutes(9),
            ]);
        }

        if ($ark) {
            Alert::create([
                'server_id' => $ark->id,
                'severity' => 'warning',
                'title' => 'Ragnarok ASA is close to its memory limit',
                'detail' => 'Sustained above 92% of 24 GiB for the last hour. ARK will be killed by the OOM handler before it warns you.',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ]);
        }

        if ($suspended) {
            Alert::create([
                'server_id' => $suspended->id,
                'severity' => 'info',
                'title' => $suspended->name.' was suspended',
                'detail' => 'Suspended by an administrator. Files are untouched and the server can be resumed at any time.',
                'acknowledged_at' => now()->subDays(2),
                'created_at' => now()->subDays(2)->subHours(1),
                'updated_at' => now()->subDays(2),
            ]);
        }
    }

    private function blueprints(): void
    {
        if (Blueprint::exists()) {
            return;
        }

        $admin = User::where('email', 'admin@gamemgr.local')->first();

        $specs = [
            ['Minecraft Starter', 'Paper', 'Small friends-and-family server. 2 GiB is plenty for ten people.', 2048, 10240, 100, 1, 1, 5],
            ['Minecraft Modded', 'Forge', 'Sized for a big modpack. Do not try this with less.', 12288, 40960, 400, 2, 2, 10],
            ['Rust Wipe Cycle', 'Rust Vanilla', 'A monthly Rust server with room for a 3000 map.', 16384, 102400, 800, 1, 2, 8],
            ['Competitive CS2', 'CS2 Dedicated', 'Scrim server. CPU matters far more than memory here.', 4096, 51200, 400, 0, 2, 3],
        ];

        foreach ($specs as [$name, $templateName, $description, $mem, $disk, $cpu, $dbs, $allocs, $backups]) {
            $template = Template::where('name', $templateName)->first();
            if (! $template) {
                continue;
            }

            Blueprint::create([
                'name' => $name,
                'description' => $description,
                'template_id' => $template->id,
                'limits' => ['memory' => $mem, 'disk' => $disk, 'cpu' => $cpu, 'swap' => 0, 'io' => 500],
                'feature_limits' => ['databases' => $dbs, 'allocations' => $allocs, 'backups' => $backups],
                'environment' => null,
                'created_by' => $admin?->id,
            ]);
        }
    }
}
