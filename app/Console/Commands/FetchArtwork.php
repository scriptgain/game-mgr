<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Catalogue\Artwork;
use Illuminate\Console\Command;

/**
 * Backfill cover art for the catalogue.
 *
 * A command rather than something that happens during a page load. Two hundred
 * games is two hundred outbound requests, and no request should ever wait on
 * Valve's CDN. Safe to re-run: anything already fetched is skipped unless
 * --force says otherwise.
 */
class FetchArtwork extends Command
{
    protected $signature = 'gamemgr:fetch-artwork {--force : Re-fetch games that already have art} {--limit=0 : Stop after this many}';

    protected $description = 'Download and cache cover art for games that have a Steam app id';

    public function handle(Artwork $artwork): int
    {
        $query = Game::query()->orderBy('name');
        if (! $this->option('force')) {
            $query->whereNull('artwork_path');
        }

        $games = $query->get();
        if ($limit = (int) $this->option('limit')) {
            $games = $games->take($limit);
        }

        $found = $missing = 0;
        $bar = $this->output->createProgressBar($games->count());

        foreach ($games as $game) {
            $artwork->fetch($game) ? $found++ : $missing++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Art for {$found} game(s). {$missing} had none available and will use a generated tile.");

        return self::SUCCESS;
    }
}
