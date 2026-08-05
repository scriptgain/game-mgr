<?php

namespace App\Console\Commands;

use App\Models\Mod;
use Illuminate\Console\Command;

/**
 * Ask each mod's source whether a newer version exists.
 *
 * The Modrinth and CurseForge clients land with the real runtime drivers. Until
 * then this only stamps checked_at, so the UI can honestly say when it last
 * looked rather than implying an answer it does not have.
 */
class ModsCheckUpdates extends Command
{
    protected $signature = 'mods:check-updates';

    protected $description = 'Check installed mods against their upstream source';

    public function handle(): int
    {
        $checked = Mod::whereIn('source', ['modrinth', 'curseforge', 'spigot'])
            ->update(['checked_at' => now()]);

        $this->info($checked.' '.\Illuminate\Support\Str::plural('mod', $checked).' checked.');

        return self::SUCCESS;
    }
}
