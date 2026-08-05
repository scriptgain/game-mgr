<?php

namespace Database\Seeders;

use App\Models\Allocation;
use App\Models\Backup;
use App\Models\DatabaseHost;
use App\Models\Mod;
use App\Models\Node;
use App\Models\Player;
use App\Models\RetentionPolicy;
use App\Models\Schedule;
use App\Models\ScheduleTask;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerMetric;
use App\Models\ServerVariable;
use App\Models\StatusPage;
use App\Models\Subuser;
use App\Models\Template;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Twelve servers spread across every node, runtime and state, then everything
 * that hangs off them.
 *
 * States matter as much as counts here. A demo with twelve happy running
 * servers hides every screen that only appears when something is installing,
 * suspended or broken.
 */
class ServerSeeder extends Seeder
{
    public function run(): void
    {
        $owners = [
            'dana' => User::where('email', 'client@gamemgr.local')->first(),
            'priya' => User::where('email', 'friend@gamemgr.local')->first(),
            'allen' => User::where('email', 'admin@gamemgr.local')->first(),
        ];

        $spec = [
            ['Survival SMP', 'minecraft', 'Paper', 'phx-docker-01', 'dana', null, 4096, 20480, 200],
            ['Creative Build', 'minecraft', 'Paper', 'phx-docker-01', 'dana', null, 2048, 10240, 100],
            ['All The Mods 9', 'minecraft', 'Forge', 'phx-docker-01', 'priya', null, 12288, 40960, 400],
            ['Console Crossplay', 'minecraft', 'Bedrock', 'phx-docker-01', 'allen', null, 2048, 10240, 100],
            ['Rust Monthly', 'rust', 'Rust Vanilla', 'phx-steam-01', 'dana', null, 16384, 102400, 800],
            ['Rust Modded', 'rust', 'Rust Oxide', 'phx-steam-01', 'priya', 'installing', 16384, 102400, 800],
            ['Dust2 Scrims', 'counter-strike-2', 'CS2 Dedicated', 'phx-steam-01', 'allen', null, 4096, 51200, 400],
            ['Viking Nights', 'valheim', 'Valheim Dedicated', 'fra-lgsm-01', 'dana', null, 8192, 30720, 300],
            ['Ragnarok ASA', 'ark-survival-ascended', 'ARK ASA', 'fra-lgsm-01', 'priya', null, 24576, 153600, 800],
            ['Pal Ranch', 'palworld', 'Palworld Dedicated', 'fra-lgsm-01', 'dana', 'suspended', 16384, 51200, 400],
            ['Home Vanilla', 'minecraft', 'Paper', 'home-nuc-01', 'allen', null, 4096, 20480, 200],
            ['Old Test Box', 'minecraft', 'Paper', 'fra-docker-02', 'allen', 'install_failed', 2048, 10240, 100],
        ];

        foreach ($spec as [$name, $gameSlug, $templateName, $nodeName, $ownerKey, $status, $mem, $disk, $cpu]) {
            $node = Node::where('name', $nodeName)->first();
            $template = Template::whereHas('game', fn ($q) => $q->where('slug', $gameSlug))
                ->where('name', $templateName)->first();
            $owner = $owners[$ownerKey];

            if (! $node || ! $template || ! $owner) {
                continue;
            }

            $server = Server::firstOrCreate(['name' => $name], [
                'owner_id' => $owner->id,
                'node_id' => $node->id,
                'template_id' => $template->id,
                'runtime' => $template->runtime,
                'image' => $template->defaultImage(),
                'startup' => $template->startup,
                'memory' => $mem,
                'disk' => $disk,
                'cpu' => $cpu,
                'swap' => 0,
                'io' => 500,
                'database_limit' => 2,
                'allocation_limit' => 3,
                'backup_limit' => 8,
                'status' => $status,
                'installed_at' => $status === 'installing' ? null : now()->subDays(rand(3, 60)),
                'auto_restart' => true,
                'auto_update' => in_array($template->runtime, ['steamcmd', 'linuxgsm'], true),
                'description' => null,
            ]);

            $this->attachAllocation($server, $node);
            $this->fillVariables($server, $template);
            $this->cacheState($server, $status);
            $this->history($server, $status);
            $this->extras($server, $template, $owners);
        }

        $this->shareServers($owners);
    }

    private function attachAllocation(Server $server, Node $node): void
    {
        if ($server->allocation_id) {
            return;
        }

        $alloc = Allocation::where('node_id', $node->id)->whereNull('server_id')->orderBy('port')->first();
        if (! $alloc) {
            return;
        }

        $alloc->update(['server_id' => $server->id]);
        $server->update(['allocation_id' => $alloc->id]);
    }

    private function fillVariables(Server $server, Template $template): void
    {
        foreach ($template->variables as $var) {
            ServerVariable::firstOrCreate(
                ['server_id' => $server->id, 'template_variable_id' => $var->id],
                ['value' => $var->default_value],
            );
        }
    }

    /** The cached power state an index page colours its status dot from. */
    private function cacheState(Server $server, ?string $status): void
    {
        if ($status !== null) {
            $server->update([
                'power_state' => 'offline',
                'cached_at' => now(),
            ]);

            return;
        }

        $running = crc32($server->name) % 4 !== 0; // most up, some down
        $server->update([
            'power_state' => $running ? 'running' : 'offline',
            'cached_cpu' => $running ? round($server->cpu * (0.15 + (crc32($server->name) % 40) / 100), 2) : 0,
            'cached_memory' => $running ? (int) ($server->memory * (0.35 + (crc32($server->name) % 35) / 100)) : 0,
            'cached_disk' => (int) ($server->disk * 0.42),
            'cached_players' => $running ? crc32($server->name) % 14 : 0,
            'cached_max_players' => 20,
            'last_started_at' => $running ? now()->subHours(rand(2, 200)) : null,
            'cached_at' => now(),
        ]);
    }

    /**
     * Thirty days of samples at hourly resolution. This is the table that makes
     * "show me last month" possible at all, and Pterodactyl has no equivalent.
     */
    private function history(Server $server, ?string $status): void
    {
        if ($status !== null || $server->metrics()->exists()) {
            return;
        }

        $days = (int) 30;
        $rows = [];
        $seed = crc32($server->name);

        for ($i = $days * 24; $i >= 0; $i--) {
            $at = now()->subHours($i);
            // Prime time in the evening, quiet at dawn, plus a weekend lift.
            $hourly = (sin(($at->hour / 24) * 2 * M_PI - 1.9) + 1) / 2;
            $weekend = $at->isWeekend() ? 1.35 : 1.0;
            $load = min(1.0, $hourly * $weekend);

            $rows[] = [
                'server_id' => $server->id,
                'sampled_at' => $at,
                'cpu' => round($server->cpu * (0.08 + $load * 0.55), 2),
                'memory' => (int) ($server->memory * (0.3 + $load * 0.45)),
                'disk' => (int) ($server->disk * (0.35 + ($days * 24 - $i) / ($days * 24) * 0.12)),
                'net_rx' => (int) (8000 + $load * 120000 + $seed % 3000),
                'net_tx' => (int) (6000 + $load * 190000 + $seed % 4000),
                'players' => (int) round($load * (10 + $seed % 9)),
                'tick_rate' => round(20 - $load * 2.4, 2),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ServerMetric::insert($chunk);
        }
    }

    private function extras(Server $server, Template $template, array $owners): void
    {
        $this->backups($server);
        $this->schedules($server, $template);
        $this->players($server);
        $this->mods($server, $template);
        $this->worlds($server, $template);
        $this->databases($server);
        $this->statusPage($server);
    }

    private function backups(Server $server): void
    {
        if ($server->backups()->exists() || $server->status !== null) {
            return;
        }

        $policy = RetentionPolicy::where('is_default', true)->first();

        for ($i = 0; $i < 5; $i++) {
            $at = now()->subDays($i)->setTime(4, 12);
            Backup::create([
                'server_id' => $server->id,
                'retention_policy_id' => $policy?->id,
                'name' => 'Scheduled '.$at->format('Y-m-d'),
                'disk' => $i === 0 ? 's3' : 'local',
                'bytes' => 220 * 1024 * 1024 + (crc32($server->name.$i) % (900 * 1024 * 1024)),
                'checksum' => substr(hash('sha256', $server->uuid.$i), 0, 32),
                'is_successful' => true,
                'is_locked' => $i === 4,
                'completed_at' => $at,
                'created_at' => $at->copy()->subMinutes(6),
                'updated_at' => $at,
            ]);
        }

        // One failure, because the failure row is a state the UI has to render
        // and a seed of nothing but successes never exercises it.
        if (crc32($server->name) % 3 === 0) {
            Backup::create([
                'server_id' => $server->id,
                'name' => 'Manual before update',
                'disk' => 'local',
                'bytes' => 0,
                'is_successful' => false,
                'failure_reason' => 'Node ran out of disk part way through the archive.',
                'completed_at' => now()->subDays(6),
            ]);
        }
    }

    private function schedules(Server $server, Template $template): void
    {
        if ($server->schedules()->exists() || $server->status !== null) {
            return;
        }

        $restart = Schedule::create([
            'server_id' => $server->id,
            'name' => 'Nightly Restart',
            'cron_minute' => '0',
            'cron_hour' => '5',
            'is_active' => true,
            'only_when_online' => true,
            'last_run_at' => now()->subHours(rand(2, 26)),
            'next_run_at' => now()->addHours(rand(1, 23)),
        ]);

        // A chain, which is the thing schedules exist for: warn, wait, warn
        // again, then actually restart.
        ScheduleTask::insert([
            ['schedule_id' => $restart->id, 'sequence' => 1, 'action' => 'command', 'payload' => 'say Server restarting in 5 minutes', 'time_offset' => 0, 'continue_on_failure' => true, 'is_queued' => false, 'created_at' => now(), 'updated_at' => now()],
            ['schedule_id' => $restart->id, 'sequence' => 2, 'action' => 'command', 'payload' => 'say Server restarting in 1 minute', 'time_offset' => 240, 'continue_on_failure' => true, 'is_queued' => false, 'created_at' => now(), 'updated_at' => now()],
            ['schedule_id' => $restart->id, 'sequence' => 3, 'action' => 'power', 'payload' => 'restart', 'time_offset' => 60, 'continue_on_failure' => false, 'is_queued' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $backup = Schedule::create([
            'server_id' => $server->id,
            'name' => 'Daily Backup',
            'cron_minute' => '0',
            'cron_hour' => '4',
            'is_active' => true,
            'last_run_at' => now()->subHours(rand(2, 26)),
            'next_run_at' => now()->addHours(rand(1, 23)),
        ]);
        ScheduleTask::create([
            'schedule_id' => $backup->id, 'sequence' => 1, 'action' => 'backup', 'payload' => null, 'time_offset' => 0,
        ]);

        if (in_array($template->runtime, ['steamcmd', 'linuxgsm'], true)) {
            $update = Schedule::create([
                'server_id' => $server->id,
                'name' => 'Weekly Game Update',
                'cron_minute' => '30',
                'cron_hour' => '6',
                'cron_day_of_week' => '2',
                'is_active' => true,
                'next_run_at' => now()->addDays(rand(1, 6)),
            ]);
            ScheduleTask::insert([
                ['schedule_id' => $update->id, 'sequence' => 1, 'action' => 'command', 'payload' => 'say Updating game files, back shortly', 'time_offset' => 0, 'continue_on_failure' => true, 'is_queued' => false, 'created_at' => now(), 'updated_at' => now()],
                ['schedule_id' => $update->id, 'sequence' => 2, 'action' => 'update', 'payload' => null, 'time_offset' => 60, 'continue_on_failure' => false, 'is_queued' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    private function players(Server $server): void
    {
        if ($server->players()->exists() || $server->status !== null) {
            return;
        }

        $names = ['Roundabout', 'PixelHermit', 'GravelKing', 'Nyx_', 'Torvald', 'MossyLog',
            'Cinder', 'QuietBrick', 'Halberd', 'Sunflower99', 'DeepFriedTNT', 'Wisp'];
        $online = $server->cached_players;

        foreach ($names as $i => $name) {
            Player::create([
                'server_id' => $server->id,
                'identifier' => $server->runtime === 'docker'
                    ? sprintf('%08x-%04x-4%03x-8%03x-%012x', crc32($name), $i, $i, $i, crc32($name.$server->id))
                    : (string) (76561198000000000 + crc32($name) % 99999999),
                'name' => $name,
                'ip' => '198.51.100.'.(10 + $i),
                'first_seen_at' => now()->subDays(rand(10, 120)),
                'last_seen_at' => $i < $online ? now() : now()->subHours(rand(1, 400)),
                'playtime_seconds' => rand(1800, 400000),
                'is_online' => $i < $online,
                'is_banned' => $i === 11,
                'is_op' => $i === 0,
                'is_whitelisted' => $i < 6,
                'ban_reason' => $i === 11 ? 'Griefing spawn, appealed and denied.' : null,
            ]);
        }
    }

    private function mods(Server $server, Template $template): void
    {
        if (! $template->supportsMods() || $server->mods()->exists() || $server->status !== null) {
            return;
        }

        $catalogue = match (true) {
            in_array('spigot', $template->mod_sources ?? [], true) => [
                ['modrinth', 'EssentialsX', '2.20.1', '2.21.0', 'EssentialsTeam', 'The command set every server ends up wanting.'],
                ['modrinth', 'LuckPerms', '5.4.145', '5.4.145', 'Luck', 'Permissions, done properly.'],
                ['spigot', 'CoreProtect', '22.4', '22.4', 'Intelli', 'Block logging and rollback.'],
                ['modrinth', 'Chunky', '1.4.28', '1.4.36', 'pop4959', 'Pre-generates chunks so players do not lag the server generating them.'],
                ['curseforge', 'WorldEdit', '7.3.6', '7.3.6', 'EngineHub', 'In-game map editing.'],
            ],
            default => [
                ['workshop', 'Structures Plus', '1.4.2', '1.4.9', 'orionsun', 'Quality of life building overhaul.'],
                ['workshop', 'Awesome SpyGlass', '3.1.0', '3.1.0', 'ghazlawl', 'Detailed creature stats at a glance.'],
                ['workshop', 'Death Recovery Mod', '2.2.1', '2.2.4', 'Grebog', 'Recover your gear after an unfortunate cliff.'],
            ],
        };

        foreach ($catalogue as $i => [$source, $name, $version, $latest, $author, $summary]) {
            Mod::create([
                'server_id' => $server->id,
                'source' => $source,
                'remote_id' => Str::lower(Str::slug($name)),
                'name' => $name,
                'slug' => Str::slug($name),
                'author' => $author,
                'summary' => $summary,
                'version' => $version,
                'latest_version' => $latest,
                'path' => '/plugins/'.Str::studly($name).'.jar',
                'bytes' => 400_000 + crc32($name) % 8_000_000,
                'enabled' => $i !== 4,
                'installed_at' => now()->subDays(rand(3, 90)),
                'checked_at' => now()->subHours(rand(1, 12)),
            ]);
        }
    }

    private function worlds(Server $server, Template $template): void
    {
        if ($server->worlds()->exists() || $server->status !== null) {
            return;
        }

        $isMinecraft = str_contains(Str::lower($template->game?->name ?? ''), 'minecraft');
        $worlds = $isMinecraft
            ? [['world', '/world', '-4172144997902289642', 'default', true],
                ['world_nether', '/world_nether', '-4172144997902289642', 'default', false],
                ['season-3-archive', '/backups/season-3', '881344023', 'amplified', false]]
            : [['Main', '/saves/Main', null, null, true],
                ['Backup Before Wipe', '/saves/prewipe', null, null, false]];

        foreach ($worlds as [$name, $path, $seed, $type, $active]) {
            World::create([
                'server_id' => $server->id,
                'name' => $name,
                'path' => $path,
                'seed' => $seed,
                'level_type' => $type,
                'bytes' => 120_000_000 + crc32($name) % 3_000_000_000,
                'is_active' => $active,
                'last_played_at' => $active ? now()->subMinutes(rand(1, 90)) : now()->subDays(rand(20, 200)),
            ]);
        }
    }

    private function databases(Server $server): void
    {
        if ($server->databases()->exists() || $server->status !== null) {
            return;
        }
        if (crc32($server->name) % 2 !== 0) {
            return;
        }

        $host = DatabaseHost::first();
        if (! $host) {
            return;
        }

        ServerDatabase::create([
            'server_id' => $server->id,
            'database_host_id' => $host->id,
            'database' => 's'.$server->id.'_main',
            'username' => 'u'.$server->id.'_'.Str::lower(Str::random(6)),
            'password' => Str::random(24),
            'remote' => '%',
            'bytes' => rand(2, 900) * 1024 * 1024,
        ]);
    }

    private function statusPage(Server $server): void
    {
        if ($server->statusPage()->exists() || $server->status !== null) {
            return;
        }
        // Only a couple, so the "not set up yet" empty state also gets seen.
        if (crc32($server->name) % 4 !== 0) {
            return;
        }

        StatusPage::create([
            'server_id' => $server->id,
            'slug' => Str::slug($server->name),
            'headline' => $server->name.' Status',
            'is_public' => true,
        ]);
    }

    /**
     * Share two servers so the subuser permission matrix has something real
     * behind it. Priya gets console and files on Dana's SMP but no power
     * control, which is the sort of split the matrix exists to express.
     */
    private function shareServers(array $owners): void
    {
        $smp = Server::where('name', 'Survival SMP')->first();
        $rust = Server::where('name', 'Rust Monthly')->first();

        if ($smp && $owners['priya']) {
            Subuser::firstOrCreate(
                ['server_id' => $smp->id, 'user_id' => $owners['priya']->id],
                ['permissions' => [
                    'control.console', 'control.command', 'control.restart',
                    'file.read', 'file.update',
                    'backup.read', 'backup.create',
                    'player.read', 'player.kick',
                    'schedule.read', 'activity.read',
                ]],
            );
        }

        if ($rust && $owners['priya']) {
            Subuser::firstOrCreate(
                ['server_id' => $rust->id, 'user_id' => $owners['priya']->id],
                ['permissions' => ['control.console', 'player.read', 'backup.read']],
            );
        }
    }
}
