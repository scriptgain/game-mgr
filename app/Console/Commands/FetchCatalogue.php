<?php

namespace App\Console\Commands;

use App\Services\TemplateImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Vendor the community egg catalogue into the repository.
 *
 * Run by us, not by anybody's panel. The definitions land in database/catalogue/ as
 * ordinary files, get committed, and ship inside the release, so a fresh
 * install has a real catalogue with no outbound network and every install has
 * the SAME catalogue. Refreshing it is then a deliberate act that produces a
 * reviewable diff, rather than something that changes under a running panel
 * because somebody upstream pushed at the wrong moment.
 *
 * Sources are the pelican-eggs organisation, all MIT. catalogue.lock.json
 * records where each file came from so the provenance survives the copy.
 */
class FetchCatalogue extends Command
{
    protected $signature = 'gamemgr:fetch-catalogue
        {--index=https://raw.githubusercontent.com/pelican-eggs/pelican-eggs.github.io/main/content/pterodactyl.json}
        {--only=* : Limit to these categories, for example "Games Steamcmd"}
        {--dry-run : Report what would be written and write nothing}';

    protected $description = 'Download the community egg catalogue into database/catalogue for vendoring';

    public function handle(): int
    {
        $root = database_path('catalogue');
        $index = (string) $this->option('index');

        $this->info('Reading '.$index);
        $response = Http::timeout(60)->get($index);
        if (! $response->successful()) {
            $this->error('The index would not load: HTTP '.$response->status());

            return self::FAILURE;
        }

        $nests = data_get($response->json(), 'nests', []);
        if (! $nests) {
            $this->error('That index has no nests in it. The format may have changed.');

            return self::FAILURE;
        }

        $only = array_map('mb_strtolower', (array) $this->option('only'));
        $dry = (bool) $this->option('dry-run');
        $importer = new TemplateImporter;

        $lock = [];
        $written = $skipped = $failed = 0;

        foreach ($nests as $nest) {
            $category = (string) data_get($nest, 'nest_type', 'Uncategorised');
            if ($only && ! in_array(mb_strtolower($category), $only, true)) {
                continue;
            }

            // "Eggs" and "egg.name" below are upstream's key names, not ours.
            // Pterodactyl calls a template an egg; we do not, but their index
            // does, and renaming a key we are reading is how you get an empty
            // catalogue and no error.
            $definitions = (array) data_get($nest, 'Eggs', []);
            $this->line(sprintf('%-18s %d definitions', $category, count($definitions)));

            foreach ($definitions as $entry) {
                $name = (string) data_get($entry, 'egg.name', '');
                $url = (string) data_get($entry, 'download_url', '');
                if ($name === '' || $url === '') {
                    $failed++;

                    continue;
                }

                $slug = Str::slug($name) ?: Str::slug(basename($url, '.json'));
                $path = $root.'/'.Str::slug($category).'/'.$slug.'.json';

                $definition = Http::timeout(60)->get($url);
                if (! $definition->successful()) {
                    $this->warn("  {$name}: HTTP {$definition->status()}");
                    $failed++;

                    continue;
                }

                $decoded = $definition->json();

                // Validated before it is written, using the same check the
                // importer applies. A file that cannot be imported is worse
                // than a missing one: it fails during a seed on somebody
                // else's machine, long after anyone could ask why.
                try {
                    $importer->assertLooksLikeATemplate((array) $decoded);
                } catch (\Throwable $e) {
                    $this->warn("  {$name}: not a usable egg, skipped ({$e->getMessage()})");
                    $skipped++;

                    continue;
                }

                $body = $definition->body();
                $lock[$slug] = [
                    'name' => $name,
                    'category' => $category,
                    'source' => $url,
                    'sha256' => hash('sha256', $body),
                    'fetched_at' => now()->toDateString(),
                ];

                if ($dry) {
                    $written++;

                    continue;
                }

                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0o755, true);
                }
                file_put_contents($path, $body);
                $written++;
            }
        }

        if (! $dry) {
            ksort($lock);
            file_put_contents(
                $root.'/catalogue.lock.json',
                json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
            );
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d definitions, skipped %d, failed %d.',
            $dry ? 'Would write' : 'Wrote',
            $written, $skipped, $failed
        ));

        return self::SUCCESS;
    }
}
