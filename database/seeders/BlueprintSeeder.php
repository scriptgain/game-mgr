<?php

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The named sizes an operator picks from when creating a server.
 *
 * These are REFERENCE DATA, not demo data, and they used to live in
 * ActivitySeeder alongside fake alerts and sample activity. The panel installer
 * rightly refuses to run that seeder on a real install, so a live panel had no
 * blueprints at all: the create wizard's "pick a size" step had nothing to
 * offer, and the under-provisioned memory warning, which derives its floor from
 * the blueprints published for a template, had nothing to compare against. That
 * is how a Palworld server came to be created with 2 GiB.
 *
 * Keyed on name, so a size added here later still lands on a database that has
 * already been seeded once.
 */
class BlueprintSeeder extends Seeder
{
    public function run(): void
    {
        // Whoever set the panel up, when there is one. Null is fine: these are
        // shipped defaults rather than something a person authored.
        $admin = User::where('root_admin', true)->orderBy('id')->first()
            ?? User::where('email', 'admin@gamemgr.local')->first();

        $specs = [
            ['Minecraft Starter', 'Paper', 'Small friends-and-family server. 2 GiB is plenty for ten people.', 2048, 10240, 100, 1, 1, 5],
            ['Minecraft Modded', 'Forge', 'Sized for a big modpack. Do not try this with less.', 12288, 40960, 400, 2, 2, 10],
            ['Rust Wipe Cycle', 'Rust Vanilla', 'A monthly Rust server with room for a 3000 map.', 16384, 102400, 800, 1, 2, 8],
            ['Competitive CS2', 'CS2 Dedicated', 'Scrim server. CPU matters far more than memory here.', 4096, 51200, 400, 0, 2, 3],
            // Palworld carries no resource hint on the template itself, because
            // templates have no memory column: every server otherwise lands on
            // the 2 GiB default, which Palworld runs out of before anyone has
            // finished loading in. 8 GiB is the floor and 16 GiB is what a full
            // 32 player server actually uses. The CPU allowance looks generous
            // for a single threaded simulation, but the save, network and
            // streaming threads are what use the rest.
            // Three sizes rather than one, because a single figure is wrong at
            // both ends: 16 GiB is an OOM waiting to happen on a 16 GB box once
            // the OS, the daemon and steamcmd have taken their share, and it is
            // not enough for a busy world. The cgroup writes whichever number is
            // picked as a hard memory.max with swap disabled, so an over-set
            // limit is not optimism, it is a kill during world load.
            ['Palworld Test', 'Palworld Dedicated', 'Enough to prove the install and connect a few players. 8 GiB is the floor, not a target.', 8192, 40960, 400, 0, 2, 8],
            ['Palworld Server', 'Palworld Dedicated', 'The recommended size for a real world of a dozen or so players.', 16384, 40960, 400, 0, 2, 8],
            ['Palworld Large', 'Palworld Dedicated', 'A full 32 player world. Needs a host with more than 32 GB, not exactly 32 GB.', 32768, 81920, 600, 0, 2, 8],
        ];

        foreach ($specs as [$name, $templateName, $description, $mem, $disk, $cpu, $dbs, $allocs, $backups]) {
            $template = Template::where('name', $templateName)->first();
            if (! $template) {
                continue;
            }

            // Keyed on the name rather than guarded by "any blueprint exists",
            // so a blueprint added to this list later still lands on a database
            // that has already been seeded once.
            Blueprint::updateOrCreate(['name' => $name], [
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
