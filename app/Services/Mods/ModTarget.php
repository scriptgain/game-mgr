<?php

namespace App\Services\Mods;

use App\Models\Server;

/**
 * What a given server can actually run, and where the file has to land.
 *
 * Two questions have to be answered before a catalogue is any use at all, and
 * getting either wrong produces a server that will not boot rather than an
 * error anybody can read:
 *
 *   1. Which loader is this? A Paper server runs Bukkit plugins and cannot load
 *      a Fabric mod. Offering one is not a small mistake: the jar sits in the
 *      directory doing nothing, or the server refuses to start.
 *
 *   2. Which directory? Paper, Spigot, Purpur and Folia read `plugins/`.
 *      Fabric, Forge, NeoForge, Quilt and Sponge read `mods/`. Hardcoding
 *      either is how a panel quietly installs a Fabric mod into `plugins/`.
 *
 * Both are derived, never assumed. The loader comes from the server's own
 * environment, because that is what the container is actually told to run, and
 * falls back to the template name only when the environment does not say. When
 * neither answers, this object reports itself unsupported and the install is
 * refused, which is the correct outcome: a guess here writes a file somewhere
 * a customer will have to find and delete by hand.
 *
 * The Minecraft version is treated the same way. `VERSION=LATEST` is the
 * default on the Paper template and it genuinely means "whatever is newest at
 * boot", so it is reported as unknown rather than pretended to be a number. The
 * UI says so instead of silently searching every version ever published.
 */
class ModTarget
{
    /**
     * Loader profiles, keyed by the value the itzg/minecraft-server image takes
     * in TYPE, which is also what every Minecraft template here writes.
     *
     * `loaders` is what goes into the Modrinth facet, and it is a list rather
     * than one value because compatibility runs downhill: a Paper server loads
     * any Bukkit or Spigot plugin, a Purpur server loads any Paper plugin, and
     * a Quilt server loads Fabric mods. Searching only the exact loader would
     * hide most of what the server can run.
     */
    private const PROFILES = [
        'paper' => ['label' => 'Paper', 'loaders' => ['paper', 'spigot', 'bukkit'], 'directory' => 'plugins'],
        'purpur' => ['label' => 'Purpur', 'loaders' => ['purpur', 'paper', 'spigot', 'bukkit'], 'directory' => 'plugins'],
        'folia' => ['label' => 'Folia', 'loaders' => ['folia'], 'directory' => 'plugins'],
        'spigot' => ['label' => 'Spigot', 'loaders' => ['spigot', 'bukkit'], 'directory' => 'plugins'],
        'bukkit' => ['label' => 'Bukkit', 'loaders' => ['bukkit'], 'directory' => 'plugins'],
        'craftbukkit' => ['label' => 'CraftBukkit', 'loaders' => ['bukkit'], 'directory' => 'plugins'],
        'fabric' => ['label' => 'Fabric', 'loaders' => ['fabric'], 'directory' => 'mods'],
        'quilt' => ['label' => 'Quilt', 'loaders' => ['quilt', 'fabric'], 'directory' => 'mods'],
        'forge' => ['label' => 'Forge', 'loaders' => ['forge'], 'directory' => 'mods'],
        'neoforge' => ['label' => 'NeoForge', 'loaders' => ['neoforge'], 'directory' => 'mods'],
        'sponge' => ['label' => 'Sponge', 'loaders' => ['sponge'], 'directory' => 'mods'],
        // Hybrids run both worlds at once, so the directory cannot be decided
        // from the server alone. It is decided per file instead, from the
        // loaders the chosen Modrinth version declares, which is the only thing
        // that actually knows.
        'mohist' => ['label' => 'Mohist', 'loaders' => ['forge', 'paper', 'spigot', 'bukkit'], 'directory' => null],
        'arclight' => ['label' => 'Arclight', 'loaders' => ['forge', 'paper', 'spigot', 'bukkit'], 'directory' => null],
        'magma' => ['label' => 'Magma', 'loaders' => ['forge', 'spigot', 'bukkit'], 'directory' => null],
    ];

    /** Which directory each Modrinth loader reads its files from. */
    private const DIRECTORIES = [
        'bukkit' => 'plugins',
        'spigot' => 'plugins',
        'paper' => 'plugins',
        'purpur' => 'plugins',
        'folia' => 'plugins',
        'fabric' => 'mods',
        'quilt' => 'mods',
        'forge' => 'mods',
        'neoforge' => 'mods',
        'sponge' => 'mods',
        'liteloader' => 'mods',
        'rift' => 'mods',
        'modloader' => 'mods',
    ];

    /**
     * Environment keys that carry the Minecraft version, in the order they are
     * trusted. VERSION is the itzg convention every template here uses;
     * MINECRAFT_VERSION and MC_VERSION show up in imported Pterodactyl eggs.
     */
    private const VERSION_KEYS = ['VERSION', 'MINECRAFT_VERSION', 'MC_VERSION'];

    private function __construct(
        public readonly ?string $loader,
        public readonly string $loaderLabel,
        /** @var array<int,string> */
        public readonly array $loaders,
        public readonly ?string $directory,
        public readonly ?string $gameVersion,
        /** @var array<int,string> */
        public readonly array $sources,
    ) {}

    public static function for(Server $server): self
    {
        $sources = $server->template?->mod_sources ?? [];
        $key = self::detectLoader($server);
        $profile = $key === null ? null : self::PROFILES[$key];

        return new self(
            loader: $key,
            loaderLabel: $profile['label'] ?? 'Unknown',
            loaders: $profile['loaders'] ?? [],
            directory: $profile['directory'] ?? null,
            gameVersion: self::detectGameVersion($server),
            sources: array_values(array_filter($sources, 'is_string')),
        );
    }

    /** Can anything be searched or installed for this server at all? */
    public function supported(): bool
    {
        return $this->loader !== null && in_array('modrinth', $this->sources, true);
    }

    /** Is Modrinth one of the sources this template declares? */
    public function usesModrinth(): bool
    {
        return in_array('modrinth', $this->sources, true);
    }

    /** Did anyone pin a Minecraft version, or is it floating on LATEST? */
    public function versionKnown(): bool
    {
        return $this->gameVersion !== null;
    }

    /**
     * Where a file whose Modrinth version declares these loaders belongs, or
     * null when nothing in it matches what this server runs.
     *
     * The version's own loader list is the authority, not the server's, because
     * a project can publish a Bukkit build and a Fabric build under one id and
     * only the version says which one was picked.
     *
     * @param  array<int,string>  $versionLoaders
     */
    public function directoryFor(array $versionLoaders): ?string
    {
        foreach ($versionLoaders as $loader) {
            $loader = strtolower((string) $loader);

            if (in_array($loader, $this->loaders, true) && isset(self::DIRECTORIES[$loader])) {
                return self::DIRECTORIES[$loader];
            }
        }

        // A version that names its loaders and shares none with this server is
        // refused outright. It should never get this far, because the version
        // list was fetched with a loader filter, but "should never" is not a
        // reason to write a jar into a directory picked by default.
        if ($versionLoaders !== []) {
            return null;
        }

        // Nothing declared at all. A single loader profile can still answer,
        // because "plugins" for a Paper server is unambiguous; a hybrid cannot,
        // and its profile carries null so it refuses.
        return $this->directory;
    }

    /** A short sentence naming what a search is being narrowed to. */
    public function filterSummary(): string
    {
        $parts = [$this->loaderLabel.' Plugins And Mods'];

        $parts[] = $this->versionKnown()
            ? 'Minecraft '.$this->gameVersion
            : 'any Minecraft version';

        return implode(', ', $parts);
    }

    // ---------------------------------------------------------------- inside

    /**
     * Which loader this server runs.
     *
     * The environment wins because it is what the container is handed. TYPE is
     * the itzg variable; SERVER_TYPE and MOD_LOADER cover imported eggs. The
     * template name is the last resort and is matched rather than compared, so
     * "Paper (Java)" and "Fabric 1.21" both land.
     */
    private static function detectLoader(Server $server): ?string
    {
        $environment = $server->environment();

        foreach (['TYPE', 'SERVER_TYPE', 'MOD_LOADER', 'LOADER'] as $key) {
            $value = strtolower(trim((string) ($environment[$key] ?? '')));

            if ($value !== '' && isset(self::PROFILES[$value])) {
                return $value;
            }
        }

        $name = strtolower((string) ($server->template?->name ?? ''));

        if ($name === '') {
            return null;
        }

        // Longest key first, or a template called "NeoForge" matches "forge"
        // and a NeoForge server is handed Forge mods, which do not load.
        $keys = array_keys(self::PROFILES);
        usort($keys, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($keys as $key) {
            if (str_contains($name, $key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The pinned Minecraft version, or null when it is not pinned.
     *
     * LATEST, SNAPSHOT and an empty value are all "we do not know", and so is
     * anything that is not a plain release number. Modrinth's `versions` facet
     * takes exact strings such as 1.21.4, and feeding it "LATEST" returns
     * nothing at all rather than everything, which would read as a broken
     * search rather than an unfiltered one.
     */
    private static function detectGameVersion(Server $server): ?string
    {
        $environment = $server->environment();

        foreach (self::VERSION_KEYS as $key) {
            $value = trim((string) ($environment[$key] ?? ''));

            if (preg_match('/^\d+\.\d+(\.\d+)?$/', $value) === 1) {
                return $value;
            }
        }

        return null;
    }
}
