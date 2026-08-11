<?php

namespace App\Services\Catalogue;

use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Cover art for a game, fetched once and then served by this panel.
 *
 * A catalogue of two hundred games rendered as two hundred identical glyphs is
 * a list, not a catalogue. Steam publishes header art for anything with an app
 * id, which most of the library has, and the difference between that and a wall
 * of controller icons is the difference between a storefront and a spreadsheet.
 *
 * Never hotlinked. The image is copied into this panel's own storage on the way
 * through, so the catalogue does not depend on Valve's CDN staying reachable,
 * does not leak every viewer's address to them, and works on a panel with no
 * outbound network once the fetch has happened.
 */
class Artwork
{
    /** Where Steam keeps it. Tried in order; the first that answers wins. */
    private const SOURCES = [
        'https://cdn.cloudflare.steamstatic.com/steam/apps/%d/header.jpg',
        'https://cdn.cloudflare.steamstatic.com/steam/apps/%d/capsule_616x353.jpg',
    ];

    /** Anything bigger than this is not a header image and is not worth storing. */
    private const MAX_BYTES = 2_000_000;

    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Fetch and store this game's art, returning the stored path or null.
     *
     * Null is an ordinary outcome, not a failure: plenty of games have no Steam
     * app id at all, and a dedicated server's app id often has no store page of
     * its own. ARK: Survival Ascended is the example that matters, where the
     * server app 2430930 returns 404 and the game is a different number
     * entirely. The caller falls back to a generated tile.
     */
    public function fetch(Game $game): ?string
    {
        // The template's app id first, then the app Steam finds by name.
        //
        // Both are needed, and the second does most of the work. A template
        // carries the DEDICATED SERVER app id, and those very largely have no
        // store page: ARK: Survival Ascended's server is 2430930 and 404s, the
        // game is 2399830. Trying the server id alone found art for 3 games out
        // of 40. Searching Steam by name resolves 7 Days to Die to 251570 and
        // Astroneer to 361420, which is the id with the artwork on it.
        foreach ([$this->appIdFor($game), $this->appIdByName($game)] as $appId) {
            if (! $appId) {
                continue;
            }

            if ($path = $this->tryAppId($game, $appId)) {
                return $path;
            }
        }

        return null;
    }

    private function tryAppId(Game $game, int $appId): ?string
    {
        foreach (self::SOURCES as $pattern) {
            $url = sprintf($pattern, $appId);

            try {
                $response = Http::timeout(20)->get($url);
            } catch (\Throwable) {
                // A panel with no outbound network gets generated tiles and
                // nothing else changes. Not worth an exception.
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                continue;
            }

            // Checked rather than trusted: a CDN that answers 200 with an HTML
            // error page would otherwise be stored and served as a .jpg, and
            // the only symptom is a broken image nobody can explain.
            if (! str_starts_with($body, "\xFF\xD8\xFF")) {
                continue;
            }

            $path = 'games/'.$game->id.'.jpg';
            Storage::disk($this->disk)->put($path, $body);

            $game->forceFill(['artwork_path' => $path])->save();

            return $path;
        }

        return null;
    }

    /**
     * The store app id Steam returns for this game's name.
     *
     * Only the first result, and only when it looks like the same game. Steam's
     * search is generous, so "Rust" alone would happily return something else
     * entirely and put the wrong cover on a catalogue entry. A wrong picture is
     * worse than no picture: a generated tile reads as "no art", a confidently
     * wrong header reads as a bug in the catalogue.
     */
    private function appIdByName(Game $game): ?int
    {
        try {
            $response = Http::timeout(20)->get('https://steamcommunity.com/actions/SearchApps/'.rawurlencode($game->name));
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $first = ($response->json() ?: [])[0] ?? null;
        if (! $first || ! isset($first['appid'], $first['name'])) {
            return null;
        }

        $normalise = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
        if ($normalise($first['name']) !== $normalise($game->name)) {
            return null;
        }

        return (int) $first['appid'];
    }

    /**
     * The app id to ask about, from this game's templates.
     *
     * The lowest one, deliberately. A game with several templates often has a
     * base app plus tooling, and the game itself is nearly always the earlier
     * id.
     */
    private function appIdFor(Game $game): ?int
    {
        $id = $game->templates()
            ->whereNotNull('steam_app_id')
            ->where('steam_app_id', '>', 0)
            ->min('steam_app_id');

        return $id ? (int) $id : null;
    }
}
