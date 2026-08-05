<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Support\Str;

/**
 * Imports a Pterodactyl egg JSON file and turns it into a GameMGR template.
 *
 * This is deliberate strategy, not a convenience. Pterodactyl's real moat is
 * not its code, it is the thousand community eggs on GitHub covering every game
 * anyone has ever wanted to host. A panel that cannot read those starts with an
 * empty catalogue and loses. A panel that reads them starts with all of them.
 *
 * The egg format has drifted across versions (PTDL_v1 and PTDL_v2, plus a
 * handful of dialects), so every field is read defensively and anything
 * unrecognised is preserved rather than dropped.
 */
class EggImporter
{
    /** @var string[] Warnings worth showing the admin after an import. */
    public array $warnings = [];

    /**
     * @param  array  $egg  Decoded egg JSON.
     * @param  int|null  $gameId  Import into this game, or infer from the egg.
     */
    public function import(array $egg, ?int $gameId = null, ?string $source = null): Template
    {
        $this->warnings = [];

        $this->assertLooksLikeAnEgg($egg);

        $game = $gameId
            ? Game::findOrFail($gameId)
            : $this->resolveGame($egg);

        $runtime = $this->detectRuntime($egg);

        $template = Template::create([
            'game_id' => $game->id,
            'name' => $this->str($egg, 'name', 'Imported Template'),
            'author' => $this->str($egg, 'author'),
            'description' => $this->str($egg, 'description'),
            'runtime' => $runtime,
            'docker_images' => $this->images($egg),
            'script_container' => data_get($egg, 'scripts.installation.container', 'ghcr.io/gamemgr/installers:debian'),
            'script_entry' => data_get($egg, 'scripts.installation.entrypoint', 'bash'),
            // The egg format assumes this path and never states it.
            'data_path' => '/home/container',
            'script_install' => data_get($egg, 'scripts.installation.script'),
            'startup' => $this->str($egg, 'startup'),
            'config_files' => $this->jsonish(data_get($egg, 'config.files')),
            'config_startup' => $this->jsonish(data_get($egg, 'config.startup')),
            'config_stop' => $this->stopConfig($egg),
            'config_logs' => $this->jsonish(data_get($egg, 'config.logs')),
            'features' => data_get($egg, 'features') ?: null,
            'file_denylist' => data_get($egg, 'file_denylist') ?: null,
            'force_outgoing_ip' => (bool) data_get($egg, 'force_outgoing_ip', false),
            'steam_app_id' => $this->steamAppId($egg),
            'steam_anonymous' => true,
            'lgsm_shortname' => null,
            'rcon_supported' => $this->guessRcon($egg),
            'rcon_protocol' => $this->guessRconProtocol($egg, $game),
            'query_protocol' => $this->guessQueryProtocol($egg, $game),
            'mod_sources' => $this->guessModSources($game),
            'imported_from' => $source,
            'imported_at' => now(),
        ]);

        $this->importVariables($template, $egg);

        return $template->fresh('variables');
    }

    /** Convenience wrapper for a raw JSON string. */
    public function importJson(string $json, ?int $gameId = null, ?string $source = null): Template
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('That is not valid JSON.');
        }

        return $this->import($decoded, $gameId, $source);
    }

    // ------------------------------------------------------------- internals

    private function assertLooksLikeAnEgg(array $egg): void
    {
        $version = data_get($egg, 'meta.version');
        if (! $version && ! isset($egg['name'], $egg['startup'])) {
            throw new \InvalidArgumentException(
                'This does not look like a Pterodactyl egg. Expected a "meta.version" key, or at least "name" and "startup".'
            );
        }
        if ($version && ! in_array($version, ['PTDL_v1', 'PTDL_v2'], true)) {
            $this->warnings[] = "Unfamiliar egg format \"{$version}\". Imported anyway; check the startup command and variables.";
        }
    }

    /**
     * An egg names its nest only in a free-text comment, so the game is
     * inferred from the egg name and matched against what already exists
     * rather than guessed at from a field that is not there.
     */
    private function resolveGame(array $egg): Game
    {
        $name = $this->str($egg, 'name', 'Imported');

        // "Vanilla Minecraft" and "Paper" both belong under Minecraft.
        $known = [
            'minecraft' => 'Minecraft', 'paper' => 'Minecraft', 'spigot' => 'Minecraft',
            'forge' => 'Minecraft', 'fabric' => 'Minecraft', 'bungeecord' => 'Minecraft',
            'purpur' => 'Minecraft', 'bedrock' => 'Minecraft',
            'rust' => 'Rust',
            'ark' => 'ARK: Survival Evolved',
            'valheim' => 'Valheim',
            'terraria' => 'Terraria',
            'palworld' => 'Palworld',
            'counter-strike' => 'Counter-Strike 2', 'cs2' => 'Counter-Strike 2', 'csgo' => 'Counter-Strike 2',
            'garry' => "Garry's Mod",
            'satisfactory' => 'Satisfactory',
            'factorio' => 'Factorio',
            'project zomboid' => 'Project Zomboid', 'zomboid' => 'Project Zomboid',
        ];

        $haystack = Str::lower($name.' '.$this->str($egg, 'description'));
        foreach ($known as $needle => $gameName) {
            if (str_contains($haystack, $needle)) {
                return Game::firstOrCreate(
                    ['slug' => Str::slug($gameName)],
                    ['name' => $gameName, 'description' => 'Created automatically during an egg import.', 'icon' => 'controller'],
                );
            }
        }

        $this->warnings[] = "Could not tell which game \"{$name}\" belongs to, so it went into Imported. Move it if you know better.";

        return Game::firstOrCreate(
            ['slug' => 'imported'],
            ['name' => 'Imported', 'description' => 'Templates imported from Pterodactyl eggs.', 'icon' => 'download'],
        );
    }

    /**
     * Eggs are always Docker. The interesting case is an egg whose install
     * script is really just steamcmd wearing a container, which GameMGR can run
     * natively and faster. Flagged, not forced: switching runtime changes
     * behaviour, so the admin decides.
     */
    private function detectRuntime(array $egg): string
    {
        $script = (string) data_get($egg, 'scripts.installation.script', '');
        if (str_contains(Str::lower($script), 'steamcmd')) {
            $this->warnings[] = 'This egg installs through SteamCMD. GameMGR can run it natively with the SteamCMD runtime, which skips the container entirely. Change the runtime on the template if you want that.';
        }

        return 'docker';
    }

    /**
     * PTDL_v1 used a single "image" string, v2 uses a label to image map, and
     * some community eggs use a flat list. All three end up as a label map.
     */
    private function images(array $egg): array
    {
        $images = data_get($egg, 'docker_images');
        if (is_array($images) && $images !== []) {
            if (array_is_list($images)) {
                return array_combine(
                    array_map(fn ($i) => $this->labelFor($i), $images),
                    $images,
                );
            }

            return $images;
        }

        $single = data_get($egg, 'image') ?: data_get($egg, 'docker_image');
        if ($single) {
            return [$this->labelFor($single) => $single];
        }

        $this->warnings[] = 'The egg carried no Docker image. Set one on the template before creating a server from it.';

        return [];
    }

    private function labelFor(string $image): string
    {
        $tag = Str::afterLast($image, ':');

        return $tag && $tag !== $image ? ucfirst($tag) : 'Default';
    }

    /** config.stop is a bare string in v1 and an object in some dialects. */
    private function stopConfig(array $egg): array
    {
        $stop = data_get($egg, 'config.stop');
        if (is_array($stop)) {
            return $stop;
        }

        return ['value' => (string) ($stop ?: 'stop')];
    }

    /**
     * Pterodactyl double-encodes the config blocks: they arrive as JSON
     * strings inside JSON. Decode when that is what happened, keep as-is when
     * it is not.
     */
    private function jsonish(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value ?: null;
        }
        if (is_string($value) && $value !== '' && $value !== '{}') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? ($decoded ?: null) : null;
        }

        return null;
    }

    /** Dug out of the install script, which is where it always hides. */
    private function steamAppId(array $egg): ?int
    {
        $script = (string) data_get($egg, 'scripts.installation.script', '');
        if (preg_match('/app_update\s+(\d{2,8})/i', $script, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/SRCDS_APPID[=:\s]+(\d{2,8})/i', $script, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Word-boundary matched, not a substring search. "serverconfig.txt" contains
     * the letters r-c-o-n and a naive str_contains flagged every Terraria egg as
     * RCON capable, which then put a Players tab on a server that has no way to
     * answer it.
     */
    private function guessRcon(array $egg): bool
    {
        $haystack = Str::lower(json_encode($egg) ?: '');

        return (bool) preg_match('/\brcon\b/', $haystack);
    }

    private function guessRconProtocol(array $egg, Game $game): ?string
    {
        if (! $this->guessRcon($egg)) {
            return null;
        }

        return str_contains(Str::lower($game->name), 'minecraft') ? 'minecraft' : 'source';
    }

    private function guessQueryProtocol(array $egg, Game $game): ?string
    {
        $name = Str::lower($game->name);

        return match (true) {
            str_contains($name, 'minecraft') => 'minecraft',
            $this->steamAppId($egg) !== null => 'a2s',
            default => null,
        };
    }

    private function guessModSources(Game $game): ?array
    {
        $name = Str::lower($game->name);

        return match (true) {
            str_contains($name, 'minecraft') => ['modrinth', 'curseforge', 'spigot'],
            str_contains($name, 'rust'), str_contains($name, 'ark'),
            str_contains($name, 'garry'), str_contains($name, 'counter-strike') => ['workshop'],
            default => null,
        };
    }

    private function importVariables(Template $template, array $egg): void
    {
        $vars = data_get($egg, 'variables', []);
        if (! is_array($vars)) {
            return;
        }

        $sort = 0;
        $seen = [];
        foreach ($vars as $var) {
            $envName = (string) data_get($var, 'env_variable');
            if ($envName === '') {
                continue;
            }
            // Some community eggs ship the same variable twice. First wins.
            if (isset($seen[$envName])) {
                $this->warnings[] = "Variable {$envName} appeared more than once in the egg. Kept the first.";

                continue;
            }
            $seen[$envName] = true;

            TemplateVariable::create([
                'template_id' => $template->id,
                'name' => (string) data_get($var, 'name', $envName),
                'description' => data_get($var, 'description'),
                'env_variable' => $envName,
                'default_value' => data_get($var, 'default_value'),
                'user_viewable' => $this->flag($var, 'user_viewable'),
                'user_editable' => $this->flag($var, 'user_editable'),
                'rules' => (string) (data_get($var, 'rules') ?: 'nullable|string'),
                'sort' => $sort++,
            ]);
        }
    }

    /**
     * Older eggs wrote these as the integers 0 and 1, newer ones as booleans,
     * and a few as the strings "true" and "false". All three mean the same
     * thing and all three appear in the wild.
     */
    private function flag(mixed $var, string $key): bool
    {
        $raw = data_get($var, $key, true);

        return filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $raw;
    }

    private function str(array $egg, string $key, ?string $default = null): ?string
    {
        $v = data_get($egg, $key);

        return is_string($v) && $v !== '' ? $v : $default;
    }
}
