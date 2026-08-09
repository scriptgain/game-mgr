<?php

namespace App\Support;

use App\Models\Game;
use App\Models\Node;
use App\Models\Server;
use App\Models\Template;
use App\Services\LicenceClient;

/**
 * Which edition this install behaves as, and what that allows.
 *
 * One accessor, so every gate in the panel asks the same question and there is
 * one place to change the answer. The rules themselves live in
 * config/editions.php rather than in code, so an edition can be repriced
 * without a release.
 *
 * Two things this class will never do.
 *
 * It never stops something that is already running. Every method here answers
 * "may another one be created", and nothing calls out to suspend, stop or
 * delete. An install whose licence lapses keeps every server it has.
 *
 * And it never throws. A licensing question that cannot be answered resolves to
 * the free edition and a banner, because a panel that 500s over a licence check
 * is a panel that cannot be used to get somebody's server back up.
 */
class Edition
{
    /** The edition this install currently behaves as. */
    public static function current(): string
    {
        $status = LicenceClient::status();

        if (! empty($status['ok'])) {
            /*
             * Read from the features map first.
             *
             * scriptgain already signs a features map with a per-licence value
             * overriding the product default, which is precisely what an
             * edition is. Adding a bespoke top-level field would have been a
             * second mechanism for the same thing, and the vendor would then
             * have to keep them in step.
             *
             * The top-level key is still honoured, so a vendor response that
             * does name one directly keeps working.
             */
            $named = $status['licence']['features']['edition']
                ?? $status['licence']['edition']
                ?? null;

            // A licence that verifies but names no edition still belongs to
            // somebody who paid. The benefit of the doubt goes to them and the
            // status message says what happened, rather than silently treating
            // a paying customer as unlicensed.
            $edition = is_string($named) ? strtolower(trim($named)) : config('editions.licensed_default', 'basic');

            if (static::exists($edition)) {
                return $edition;
            }
        }

        $default = (string) config('editions.default', 'free');

        return static::exists($default) ? $default : 'free';
    }

    public static function exists(?string $edition): bool
    {
        return $edition !== null && array_key_exists($edition, (array) config('editions.tiers', []));
    }

    /** Every edition, cheapest first. */
    public static function all(): array
    {
        return (array) config('editions.tiers', []);
    }

    public static function label(?string $edition = null): string
    {
        $edition ??= static::current();

        return (string) (config('editions.tiers.'.$edition.'.label') ?? ucfirst($edition));
    }

    /** How the editions rank. Position in the config list is the ranking. */
    public static function rank(string $edition): int
    {
        $index = array_search($edition, array_keys(static::all()), true);

        return $index === false ? -1 : $index;
    }

    /** Is the current edition at least this one? */
    public static function atLeast(string $edition): bool
    {
        return static::rank(static::current()) >= static::rank($edition);
    }

    /**
     * The cheapest edition that includes a feature, so a refusal can name the
     * thing somebody has to buy instead of just saying no.
     */
    public static function cheapestWith(string $feature): ?string
    {
        foreach (static::all() as $name => $tier) {
            if (in_array($feature, $tier['features'] ?? [], true)) {
                return $name;
            }
        }

        return null;
    }

    // ------------------------------------------------------------- features

    public static function allows(string $feature): bool
    {
        $features = (array) config('editions.tiers.'.static::current().'.features', []);

        return in_array($feature, $features, true);
    }

    // --------------------------------------------------------------- limits

    /** A numeric ceiling, or null for unlimited. */
    public static function limit(string $name): ?int
    {
        $value = config('editions.tiers.'.static::current().'.'.$name);

        return $value === null ? null : (int) $value;
    }

    /** Is there room for another server on this install? */
    public static function roomForServer(): bool
    {
        $limit = static::limit('servers');

        return $limit === null || Server::count() < $limit;
    }

    public static function roomForNode(): bool
    {
        $limit = static::limit('nodes');

        return $limit === null || Node::count() < $limit;
    }

    // ---------------------------------------------------------------- games

    /**
     * May this install deploy this game?
     *
     * A null list in the config means every game in the catalogue. An imported
     * template is a separate question, handled by the templates.import feature,
     * because an egg somebody imported is not "a game in the catalogue".
     */
    public static function allowsGame(?Game $game): bool
    {
        $allowed = config('editions.tiers.'.static::current().'.games');

        if ($allowed === null) {
            return true;
        }
        if (! $game) {
            return false;
        }

        return in_array($game->slug, (array) $allowed, true);
    }

    public static function allowsTemplate(?Template $template): bool
    {
        if (! $template) {
            return false;
        }

        // An imported template belongs to whoever imported it rather than to
        // the shipped catalogue, so it is gated as an import, not as a game.
        if ($template->imported_at !== null) {
            return static::allows('templates.import');
        }

        return static::allowsGame($template->game);
    }

    /**
     * The cheapest edition that can deploy a game, for the same reason as
     * cheapestWith: a refusal should name the way out of it.
     */
    public static function cheapestWithGame(?Game $game): ?string
    {
        foreach (static::all() as $name => $tier) {
            $games = $tier['games'] ?? null;
            if ($games === null || ($game && in_array($game->slug, (array) $games, true))) {
                return $name;
            }
        }

        return null;
    }
}
