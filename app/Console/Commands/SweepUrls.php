<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * Prints every GET URL the app exposes, with real ids substituted for route
 * parameters.
 *
 * This exists because a route sweep that skips every parameterised route is
 * close to worthless: show and edit screens are exactly where rendering breaks,
 * and they are all parameterised. Resolving the ids needs a booted application,
 * which is why it is an artisan command rather than a shell one-liner.
 */
class SweepUrls extends Command
{
    protected $signature = 'gamemgr:sweep-urls';

    protected $description = 'List every GET URL with route parameters filled in from real records';

    public function handle(): int
    {
        $ids = $this->ids();

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = '/'.ltrim($route->uri(), '/');

            if (preg_match_all('/\{(\w+)\??\}/', $uri, $matches)) {
                $resolved = true;
                foreach ($matches[1] as $i => $param) {
                    $value = $ids[$param] ?? null;
                    if ($value === null) {
                        $resolved = false;
                        break;
                    }
                    $uri = str_replace($matches[0][$i], (string) $value, $uri);
                }
                if (! $resolved) {
                    continue;
                }
            }

            $this->line($uri);
        }

        return self::SUCCESS;
    }

    /**
     * One real record per route parameter name. Anything not listed here means
     * its routes are skipped, so a new parameter should be added when it lands.
     */
    private function ids(): array
    {
        $first = fn (string $model, string $column = 'id') => rescue(
            fn () => $model::orderBy('id')->value($column),
            null,
            false,
        );

        return array_filter([
            'server' => $first(\App\Models\Server::class, 'uuid_short'),
            'node' => $first(\App\Models\Node::class),
            'location' => $first(\App\Models\Location::class),
            'game' => $first(\App\Models\Game::class),
            'template' => $first(\App\Models\Template::class),
            'variable' => $first(\App\Models\TemplateVariable::class),
            'user' => $first(\App\Models\User::class),
            'backup' => $first(\App\Models\Backup::class),
            'schedule' => $first(\App\Models\Schedule::class),
            'subuser' => $first(\App\Models\Subuser::class),
            'mount' => $first(\App\Models\Mount::class),
            'blueprint' => $first(\App\Models\Blueprint::class),
            'channel' => $first(\App\Models\NotificationChannel::class),
            'rule' => $first(\App\Models\WatchdogRule::class),
            'webhook' => $first(\App\Models\Webhook::class),
            'host' => $first(\App\Models\DatabaseHost::class),
            'allocation' => $first(\App\Models\Allocation::class),
            'alert' => $first(\App\Models\Alert::class),
            'apiToken' => $first(\App\Models\ApiToken::class),
            'bannedIp' => $first(\App\Models\BannedIp::class),
            'database' => $first(\App\Models\ServerDatabase::class),
            'mod' => $first(\App\Models\Mod::class),
            'world' => $first(\App\Models\World::class),
            'player' => $first(\App\Models\Player::class),
            'slug' => $first(\App\Models\StatusPage::class, 'slug'),
        ], fn ($v) => $v !== null);
    }
}
