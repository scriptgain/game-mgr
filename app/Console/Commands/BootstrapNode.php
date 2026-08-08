<?php

namespace App\Console\Commands;

use App\Models\Allocation;
use App\Models\Location;
use App\Models\Node;
use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Turns a freshly installed panel into one that can actually create a server.
 *
 * Out of the box an install has settings and a game catalogue and nothing else:
 * no location, no node, no ports. Creating a server therefore means knowing to
 * make a location first, then a node, then guessing which ports each game wants
 * and typing the ranges by hand. Every one of those is knowledge the panel
 * already has or can find out, so it does them.
 *
 * Safe to re-run. Nothing here overwrites an existing row, and an install that
 * already has a node is left completely alone.
 */
class BootstrapNode extends Command
{
    protected $signature = 'gamemgr:bootstrap-node
        {--ip= : Public address game servers are reached on. Detected when omitted.}
        {--location=Default : Name for the location that gets created}
        {--short= : Short code for that location, derived from the name when omitted}
        {--node=local : Name for the node that gets created}
        {--per-game=8 : How many consecutive ports to open per game}
        {--force : Add ports even when the node already has some}';

    protected $description = 'Create a starter location, a local node, and allocations for every game in the catalogue';

    public function handle(): int
    {
        $location = $this->location();
        $node = $this->node($location);

        if ($node->allocations()->exists() && ! $this->option('force')) {
            $this->line('  This node already has allocations, leaving them alone. Pass --force to add more.');
            $this->summary($node);

            return self::SUCCESS;
        }

        $this->allocations($node);
        $this->summary($node);

        return self::SUCCESS;
    }

    private function location(): Location
    {
        if ($existing = Location::first()) {
            $this->line('  Location: '.$existing->name.' (already present)');

            return $existing;
        }

        $name = (string) $this->option('location');
        $short = Str::of((string) ($this->option('short') ?: $name))->slug()->limit(16, '')->value();

        $location = Location::create([
            'name' => $name,
            'short' => $short ?: 'default',
            'description' => 'Created during installation. Rename it to wherever this machine actually is.',
        ]);

        $this->info('  Location created: '.$location->name.' ('.$location->short.')');

        return $location;
    }

    private function node(Location $location): Node
    {
        if ($existing = Node::first()) {
            $this->line('  Node: '.$existing->name.' (already present)');

            return $existing;
        }

        // Loopback on purpose. This is the panel talking to a daemon on the same
        // machine, and the daemon serves plain HTTP with no certificate of its
        // own; nginx only fronts /daemon/ for the browser's console stream.
        $node = Node::create([
            'name' => (string) $this->option('node'),
            'description' => 'This machine. Created during installation.',
            'location_id' => $location->id,
            'connection_mode' => 'direct',
            'scheme' => 'http',
            'fqdn' => '127.0.0.1',
            'daemon_port' => (int) config('node.default_port', 8942),
            'memory' => $this->detectMemoryMib(),
            'disk' => $this->detectDiskMib(),
            'cpu' => $this->detectCpuPercent(),
            'daemon_base' => '/var/lib/gamemgr/volumes',
            'public' => true,
        ]);

        $this->info('  Node created: '.$node->name.', daemon at '.$node->daemonUrl());
        $this->line('    Capacity guessed from this machine: '
            .\App\Support\Format::mib($node->memory).' memory, '
            .\App\Support\Format::mib($node->disk).' disk, '.$node->cpu.'% cpu. Adjust it on the node page.');

        return $node;
    }

    /**
     * One block of consecutive ports per distinct game port in the catalogue.
     *
     * Generated from templates.default_port rather than a hardcoded list, so a
     * template imported later brings its own port with it and an operator who
     * has never heard of 19132 does not have to find out.
     */
    private function allocations(Node $node): void
    {
        $ip = $this->ip();
        $span = max(1, (int) $this->option('per-game'));

        $wanted = Template::query()
            ->whereNotNull('default_port')
            ->orderBy('default_port')
            ->get(['name', 'default_port'])
            ->groupBy('default_port');

        if ($wanted->isEmpty()) {
            $this->warn('  No template has a default port, so no allocations were created.');

            return;
        }

        $made = 0;
        $taken = [];
        foreach ($wanted as $port => $templates) {
            for ($i = 0; $i < $span; $i++) {
                $candidate = (int) $port + $i;
                // Two games whose ranges would overlap, Rust at 28015 and a
                // neighbour eight ports later, must not fight over a number.
                // First claim wins and the second range simply starts short.
                if (isset($taken[$candidate]) || $candidate > 65535) {
                    continue;
                }
                $taken[$candidate] = true;

                $allocation = Allocation::firstOrCreate(
                    ['node_id' => $node->id, 'ip' => $ip, 'port' => $candidate],
                    ['notes' => $templates->pluck('name')->join(', ')],
                );
                if ($allocation->wasRecentlyCreated) {
                    $made++;
                }
            }
        }

        $this->info('  Allocations: '.$made.' ports on '.$ip.', '.$span.' per game.');
    }

    /**
     * The address players will actually connect to.
     *
     * Asks the kernel which source address it would use to reach the internet,
     * which is right on a box with several addresses and needs no network call.
     * Hostname lookup is the fallback, and it is only a fallback because on a
     * default cloud image it resolves to 127.0.1.1.
     */
    private function ip(): string
    {
        if ($given = trim((string) $this->option('ip'))) {
            return $given;
        }

        $out = @shell_exec('ip -4 route get 1.1.1.1 2>/dev/null');
        if ($out && preg_match('/\bsrc\s+(\d+\.\d+\.\d+\.\d+)/', $out, $m)) {
            $this->line('  Detected this machine\'s address as '.$m[1].'.');

            return $m[1];
        }

        $host = gethostbyname(gethostname() ?: '');
        if (filter_var($host, FILTER_VALIDATE_IP) && ! str_starts_with($host, '127.')) {
            return $host;
        }

        $this->warn('  Could not work out this machine\'s public address; using 0.0.0.0.');
        $this->warn('  Fix it on the node\'s Allocations page, or re-run with --ip.');

        return '0.0.0.0';
    }

    private function detectMemoryMib(): int
    {
        $info = @file_get_contents('/proc/meminfo');
        if ($info && preg_match('/MemTotal:\s+(\d+) kB/', $info, $m)) {
            // Three quarters of the machine. The rest is the OS, the daemon and
            // SteamCMD, and a node that promises everything it has is a node
            // that gets its game servers OOM killed.
            return (int) floor(((int) $m[1] / 1024) * 0.75);
        }

        return 4096;
    }

    private function detectDiskMib(): int
    {
        $free = @disk_total_space('/');

        return $free ? (int) floor($free / 1024 / 1024 * 0.8) : 51200;
    }

    private function detectCpuPercent(): int
    {
        $info = @file_get_contents('/proc/cpuinfo');
        $cores = $info ? substr_count($info, 'processor') : 1;

        return max(100, $cores * 100);
    }

    private function summary(Node $node): void
    {
        $free = $node->allocations()->whereNull('server_id')->count();
        $this->newLine();
        $this->line('  <options=bold>Ready.</> '.$free.' free ports on '.$node->name.'.');
        $this->line('  Enroll the daemon on this machine from Admin, Nodes, '.$node->name.', Enroll.');
    }
}
