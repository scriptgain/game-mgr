<?php

namespace App\Services\Mods;

use App\Services\Mods\Contracts\ModSource;

/**
 * Which catalogues exist, and which of them can serve a given server.
 *
 * Three separate questions get conflated if this is not in one place, and
 * getting any of them wrong produces a screen that lies:
 *
 *   Is it BUILT? A template can declare a source this panel has no client for.
 *   Every Minecraft template already declares curseforge, and until there is a
 *   client that means nothing.
 *
 *   Is it AVAILABLE? A built source can still be unusable, because it is turned
 *   off or because it needs an API key nobody has entered yet.
 *
 *   Does it SUIT this server? Hangar has Bukkit plugins and a Fabric server can
 *   load none of them.
 *
 * A source that fails the last two is still worth naming on screen, with the
 * reason, which is why `all()` exists alongside `for()`. Silently dropping it
 * is how a finished feature comes to look like a missing one.
 */
class ModSourceRegistry
{
    /** @var array<string,ModSource> */
    private array $sources = [];

    /** @param iterable<ModSource> $sources */
    public function __construct(iterable $sources = [])
    {
        foreach ($sources as $source) {
            $this->sources[$source->key()] = $source;
        }
    }

    public function get(string $key): ?ModSource
    {
        return $this->sources[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    /** @return array<string,ModSource> */
    public function all(): array
    {
        return $this->sources;
    }

    /**
     * The sources this server can actually be served by, in a stable order.
     *
     * Intersected with the template's own `mod_sources`, because a template
     * saying "this game takes Workshop items" is a statement about the game and
     * should not be overridden by the panel happening to have a Modrinth
     * client.
     *
     * @return array<int,ModSource>
     */
    public function for(ModTarget $target): array
    {
        return array_values(array_filter(
            $this->declared($target),
            fn (ModSource $s) => $s->available() && $s->supports($target),
        ));
    }

    /**
     * Everything the template declares that this panel has a client for,
     * whether or not it is usable right now.
     *
     * @return array<int,ModSource>
     */
    public function declared(ModTarget $target): array
    {
        $out = [];

        foreach ($target->sources as $key) {
            if ($source = $this->get($key)) {
                $out[] = $source;
            }
        }

        return $out;
    }

    /**
     * Sources named on a template that this panel cannot serve, and why.
     *
     * `manual` is not in here: it means "upload it yourself in the file
     * manager", which is a real answer rather than a missing client.
     *
     * @return array<string,string> label => reason
     */
    public function unusable(ModTarget $target): array
    {
        $out = [];

        foreach ($target->sources as $key) {
            if ($key === 'manual') {
                continue;
            }

            $source = $this->get($key);

            if ($source === null) {
                $out[\App\Models\Mod::SOURCES[$key] ?? ucfirst($key)] =
                    'This panel has no client for it yet, so it cannot be searched from here.';

                continue;
            }

            if (! $source->available()) {
                $out[$source->label()] = $source->unavailableReason() ?? 'It is not available on this install.';

                continue;
            }

            if (! $source->supports($target)) {
                $out[$source->label()] = 'It has nothing for a '.$target->loaderLabel.' server.';
            }
        }

        return $out;
    }
}
