<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Template;
use App\Models\TemplatePort;
use App\Models\TemplateVariable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports a Pterodactyl egg JSON file and turns it into a GameMGR template.
 *
 * This is deliberate strategy, not a convenience. Pterodactyl's real moat is
 * not its code, it is the thousand community definitions on GitHub covering every game
 * anyone has ever wanted to host. A panel that cannot read those starts with an
 * empty catalogue and loses. A panel that reads them starts with all of them.
 *
 * The definition format has drifted across versions (PTDL_v1 and PTDL_v2, plus a
 * handful of dialects), so every field is read defensively and anything
 * unrecognised is preserved rather than dropped.
 */
class TemplateImporter
{
    /** @var string[] Warnings worth showing the admin after an import. */
    public array $warnings = [];

    /**
     * All or nothing.
     *
     * @param  array  $definition  A decoded Pterodactyl format definition.
     * @param  int|null  $gameId  Import into this game, or infer from the definition.
     *
     * An import writes a template, then its variables, then its ports. A
     * failure partway through used to leave the template row behind holding
     * whichever variables had made it, which is worse than no template at all:
     * it looks importable, it appears in the catalogue, and it is missing the
     * settings its startup command reads. Found when two community definitions
     * carried validation rules too long for the column and left half-built
     * templates in the database.
     */
    public function import(array $definition, ?int $gameId = null, ?string $source = null): Template
    {
        return DB::transaction(fn () => $this->importWithin($definition, $gameId, $source));
    }

    private function importWithin(array $definition, ?int $gameId = null, ?string $source = null): Template
    {
        $this->warnings = [];

        $this->assertLooksLikeATemplate($definition);

        $game = $gameId
            ? Game::findOrFail($gameId)
            : $this->resolveGame($definition);

        $runtime = $this->detectRuntime($definition);
        $needsAccount = $this->needsSteamAccount($definition);

        if ($needsAccount) {
            $this->warnings[] = 'This definition logs in to Steam with an account. Its Steam credential variables were not imported; bind the template to a Steam account instead, under Catalogue then Steam Accounts.';
        }

        $template = Template::create([
            'game_id' => $game->id,
            'name' => $this->str($definition, 'name', 'Imported Template'),
            'author' => $this->str($definition, 'author'),
            'description' => $this->str($definition, 'description'),
            'runtime' => $runtime,
            'docker_images' => $this->images($definition),
            'script_container' => data_get($definition, 'scripts.installation.container', 'ghcr.io/gamemgr/installers:debian'),
            'script_entry' => data_get($definition, 'scripts.installation.entrypoint', 'bash'),
            // The definition format assumes this path and never states it.
            'data_path' => '/home/container',
            'script_install' => data_get($definition, 'scripts.installation.script'),
            'startup' => $this->str($definition, 'startup'),
            'config_files' => $this->jsonish(data_get($definition, 'config.files')),
            'config_startup' => $this->jsonish(data_get($definition, 'config.startup')),
            'config_stop' => $this->stopConfig($definition),
            'config_logs' => $this->jsonish(data_get($definition, 'config.logs')),
            'features' => data_get($definition, 'features') ?: null,
            'file_denylist' => data_get($definition, 'file_denylist') ?: null,
            'force_outgoing_ip' => (bool) data_get($definition, 'force_outgoing_ip', false),
            'steam_app_id' => $this->steamAppId($definition),
            // A definition that declares STEAM_USER is telling us the game cannot be
            // downloaded anonymously, which is the one thing the definition format
            // states plainly about Steam. Believe it.
            'steam_anonymous' => ! $needsAccount,
            'requires_steam_account' => $needsAccount,
            'lgsm_shortname' => null,
            'rcon_supported' => $this->guessRcon($definition),
            'rcon_protocol' => $this->guessRconProtocol($definition, $game),
            'query_protocol' => $this->guessQueryProtocol($definition, $game),
            'mod_sources' => $this->guessModSources($game),
            'imported_from' => $source,
            'imported_at' => now(),
        ]);

        $this->importVariables($template, $definition);
        $this->importPorts($template, $definition);

        return $template->fresh('variables', 'ports');
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

    /** Public so the catalogue fetcher can validate a file before vendoring it. */
    public function assertLooksLikeATemplate(array $definition): void
    {
        $version = data_get($definition, 'meta.version');
        if (! $version && ! isset($definition['name'], $definition['startup'])) {
            throw new \InvalidArgumentException(
                'This does not look like a Pterodactyl egg. Expected a "meta.version" key, or at least "name" and "startup".'
            );
        }
        if ($version && ! in_array($version, ['PTDL_v1', 'PTDL_v2'], true)) {
            $this->warnings[] = "Unfamiliar Pterodactyl format \"{$version}\". Imported anyway; check the startup command and variables.";
        }
    }

    /**
     * A definition names its nest only in a free-text comment, so the game is
     * inferred from the definition name and matched against what already exists
     * rather than guessed at from a field that is not there.
     */
    private function resolveGame(array $definition): Game
    {
        $name = $this->str($definition, 'name', 'Imported');

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

        $haystack = Str::lower($name.' '.$this->str($definition, 'description'));
        foreach ($known as $needle => $gameName) {
            if (str_contains($haystack, $needle)) {
                return Game::firstOrCreate(
                    ['slug' => Str::slug($gameName)],
                    ['name' => $gameName, 'description' => 'Created automatically during a definition import.', 'icon' => 'controller'],
                );
            }
        }

        // Unrecognised means "a game we have not heard of", not "junk".
        //
        // This used to drop everything it did not recognise into one game called
        // Imported. With a hand-fed definition now and then that was tidy. With a
        // community library it is a catastrophe: 178 of 248 templates landed in
        // a single bucket, and a catalogue of two hundred games rendered as
        // sixteen, one of which was an unbrowsable pile.
        //
        // So an unknown name becomes its own game, which is almost always right:
        // the definitions are named after the games they run.
        return Game::firstOrCreate(
            ['slug' => Str::slug($this->gameNameFrom($name))],
            [
                'name' => $this->gameNameFrom($name),
                'description' => $this->str($definition, 'description'),
                'icon' => 'controller',
            ],
        );
    }

    /**
     * The game's name, out of the definition's name.
     *
     * Community definitions are named for the server rather than the game, so the
     * catalogue is full of "Valheim Dedicated Server" and "Rust (Staging
     * Branch)". Left alone those become separate games from their own siblings,
     * which is how you get three entries for one game.
     *
     * Only the clearly redundant suffixes are stripped. Anything cleverer starts
     * merging games that genuinely are different, and two entries for one game
     * is a much smaller problem than one entry for two games.
     */
    private function gameNameFrom(string $name): string
    {
        $clean = preg_replace('/\s*\((?:standalone|steamcmd|docker|official|beta|staging)[^)]*\)\s*/i', ' ', $name) ?? $name;
        $clean = preg_replace('/\b(dedicated\s+server|dedicated|server)\b\s*$/i', '', trim($clean)) ?? $clean;
        $clean = trim(preg_replace('/\s{2,}/', ' ', $clean) ?? $clean, " -–—:");

        return $clean !== '' ? $clean : $name;
    }

    /**
     * Definitions are always Docker. The interesting case is a definition whose install
     * script is really just steamcmd wearing a container, which GameMGR can run
     * natively and faster. Flagged, not forced: switching runtime changes
     * behaviour, so the admin decides.
     */
    private function detectRuntime(array $definition): string
    {
        $script = (string) data_get($definition, 'scripts.installation.script', '');
        if (str_contains(Str::lower($script), 'steamcmd')) {
            $this->warnings[] = 'This definition installs through SteamCMD. GameMGR can run it natively with the SteamCMD runtime, which skips the container entirely. Change the runtime on the template if you want that.';
        }

        return 'docker';
    }

    /**
     * PTDL_v1 used a single "image" string, v2 uses a label to image map, and
     * some community definitions use a flat list. All three end up as a label map.
     */
    private function images(array $definition): array
    {
        $images = data_get($definition, 'docker_images');
        if (is_array($images) && $images !== []) {
            if (array_is_list($images)) {
                return array_combine(
                    array_map(fn ($i) => $this->labelFor($i), $images),
                    $images,
                );
            }

            return $images;
        }

        $single = data_get($definition, 'image') ?: data_get($definition, 'docker_image');
        if ($single) {
            return [$this->labelFor($single) => $single];
        }

        $this->warnings[] = 'The definition carried no Docker image. Set one on the template before creating a server from it.';

        return [];
    }

    private function labelFor(string $image): string
    {
        $tag = Str::afterLast($image, ':');

        return $tag && $tag !== $image ? ucfirst($tag) : 'Default';
    }

    /** config.stop is a bare string in v1 and an object in some dialects. */
    private function stopConfig(array $definition): array
    {
        $stop = data_get($definition, 'config.stop');
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

    /**
     * The Steam app id, from the variable that declares it.
     *
     * This used to read the install script alone, and the script almost never
     * carries a number: it writes `+app_update ${SRCDS_APPID}` and the value
     * lives in the definition's variables, as a default on a variable literally named
     * "App ID". So the extractor missed nearly every steamcmd egg in the
     * community catalogue and returned null for games whose id was sitting
     * right there.
     *
     * Variables first, script second, because a script that does contain a
     * literal number is usually a fallback path or a comment rather than the
     * app the definition actually installs.
     */
    private function steamAppId(array $definition): ?int
    {
        foreach ((array) data_get($definition, 'variables', []) as $variable) {
            $name = mb_strtoupper((string) data_get($variable, 'env_variable', ''));
            if (! in_array($name, ['SRCDS_APPID', 'STEAM_APPID', 'APPID', 'APP_ID'], true)) {
                continue;
            }

            $value = trim((string) data_get($variable, 'default_value', ''));
            if (preg_match('/\A\d{2,8}\z/', $value)) {
                return (int) $value;
            }
        }

        $script = (string) data_get($definition, 'scripts.installation.script', '');
        if (preg_match('/app_update\s+(\d{2,8})/i', $script, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/SRCDS_APPID[=:\s]+(\d{2,8})/i', $script, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Environment names a community egg uses to carry Steam credentials.
     *
     * Every steamcmd egg in the catalogue logs in with
     * `+login ${STEAM_USER} ${STEAM_PASS} ${STEAM_AUTH}` and declares those
     * three as ordinary variables, which means an imported template would hold
     * a real Steam password in `server_variables` in plain text, once per
     * server, editable by the client on the Startup tab. That is the exact
     * arrangement `steam_accounts` exists to replace, and importing the
     * catalogue would reintroduce it once per egg.
     *
     * STEAM_AUTH is the Steam Guard code field, which in Pterodactyl is typed
     * before an install and has usually expired by the time the install runs.
     * Here it is answered when Steam actually asks.
     */
    private const CREDENTIAL_VARIABLES = ['STEAM_USER', 'STEAM_PASS', 'STEAM_PASSWORD', 'STEAM_AUTH', 'STEAM_2FA_CODE', 'STEAM_GUARD_CODE'];

    /** Does this egg log in with an account rather than anonymously? */
    private function needsSteamAccount(array $definition): bool
    {
        foreach ((array) data_get($definition, 'variables', []) as $variable) {
            $name = mb_strtoupper((string) data_get($variable, 'env_variable', ''));
            if (in_array($name, self::CREDENTIAL_VARIABLES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Word-boundary matched, not a substring search. "serverconfig.txt" contains
     * the letters r-c-o-n and a naive str_contains flagged every Terraria egg as
     * RCON capable, which then put a Players tab on a server that has no way to
     * answer it.
     */
    private function guessRcon(array $definition): bool
    {
        $haystack = Str::lower(json_encode($definition) ?: '');

        return (bool) preg_match('/\brcon\b/', $haystack);
    }

    private function guessRconProtocol(array $definition, Game $game): ?string
    {
        if (! $this->guessRcon($definition)) {
            return null;
        }

        return str_contains(Str::lower($game->name), 'minecraft') ? 'minecraft' : 'source';
    }

    private function guessQueryProtocol(array $definition, Game $game): ?string
    {
        $name = Str::lower($game->name);

        return match (true) {
            str_contains($name, 'minecraft') => 'minecraft',
            $this->steamAppId($definition) !== null => 'a2s',
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

    private function importVariables(Template $template, array $definition): void
    {
        $vars = data_get($definition, 'variables', []);
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
            // Some community definitions ship the same variable twice. First wins.
            if (isset($seen[$envName])) {
                $this->warnings[] = "Variable {$envName} appeared more than once in the definition. Kept the first.";

                continue;
            }
            $seen[$envName] = true;

            // Steam credentials do not become template variables. The template
            // is bound to a steam_accounts row instead, and the panel injects
            // the real values at dispatch, so the password never lands in
            // server_variables where a client could read it off the Startup tab.
            if (in_array(mb_strtoupper($envName), self::CREDENTIAL_VARIABLES, true)) {
                continue;
            }

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
     * What this game listens on, dug out of a definition that never says.
     *
     * The Pterodactyl format has no port declaration at all. That is not an
     * oversight in the format, it is the thing the format got wrong: the panel
     * hands a server whatever port was free and the definition's startup command reads
     * it back out of SERVER_PORT, so nothing anywhere ever knows that this game
     * is supposed to be on 8211. Every egg that cares about a second port,
     * though, has to expose it as a variable, because there is no other way for
     * the startup command to reach it. So the variables are where the truth is:
     * SERVER_PORT, QUERY_PORT and RCON_PORT, with their defaults.
     *
     * A handful of forks added a top level "ports" key. Read it when it is
     * there, since a declaration beats an inference.
     */
    private function importPorts(Template $template, array $definition): void
    {
        $rows = $this->declaredPorts($definition) ?: $this->inferredPorts($template);

        if (! $rows) {
            $this->warnings[] = 'The definition never says which ports this game uses, so no port set was imported. '
                .'Add one on the template before creating a server, or it will be given whatever port happens to be free.';

            return;
        }

        $sort = 0;
        foreach ($rows as $row) {
            $template->ports()->create($row + ['sort' => $sort++]);
        }

        $template->load('ports');
        $template->syncPortColumns();
    }

    /** A top level "ports" key, as some egg dialects carry. */
    private function declaredPorts(array $definition): array
    {
        $declared = data_get($definition, 'ports');
        if (! is_array($declared) || $declared === []) {
            return [];
        }

        $rows = [];
        $sawGame = false;

        foreach ($declared as $key => $entry) {
            // Both ["game" => 8211] and [["role" => "game", "port" => 8211]].
            $role = is_array($entry) ? (string) data_get($entry, 'role', $key) : (string) $key;
            $port = is_array($entry) ? (int) data_get($entry, 'port', 0) : (int) $entry;
            $role = $this->normaliseRole($role);

            if ($port < 1 || $port > 65535 || $role === '') {
                continue;
            }
            if ($role === 'game') {
                $sawGame = true;
            }

            $rows[$role] = [
                'role' => $role,
                'label' => TemplatePort::ROLES[$role] ?? Str::headline($role).' Port',
                'protocol' => is_array($entry)
                    ? $this->normaliseProtocol((string) data_get($entry, 'protocol', ''), $role)
                    : $this->protocolFor($role),
                'source' => 'fixed',
                'port' => $port,
                'required' => $role !== 'sftp',
            ];
        }

        return $sawGame ? array_values($rows) : [];
    }

    /**
     * Read the port set off the definition's own variables.
     *
     * Only defaults that are actually a port number count. A definition whose
     * SERVER_PORT default is the empty string is telling us it expects the
     * panel to choose, which is precisely the case we cannot infer anything
     * from and must not invent a canonical port for.
     */
    private function inferredPorts(Template $template): array
    {
        $ports = [];

        foreach ($template->variables()->get() as $var) {
            $env = mb_strtoupper((string) $var->env_variable);
            if (! preg_match('/(^|_)PORT$/', $env)) {
                continue;
            }

            $value = trim((string) $var->default_value);
            if (! ctype_digit($value)) {
                continue;
            }

            $port = (int) $value;
            if ($port < 1 || $port > 65535) {
                continue;
            }

            $role = $this->normaliseRole($env);
            $ports[$role] ??= [
                'role' => $role,
                'label' => TemplatePort::ROLES[$role] ?? Str::headline(str_replace('_', ' ', $env)),
                'protocol' => $this->protocolFor($role),
                'source' => 'fixed',
                'port' => $port,
                // Only the game port stops a create. Anything else a definition
                // happens to mention is a best effort, because a definition that says
                // "STATS_PORT" is not saying the game is broken without it.
                'required' => in_array($role, ['game', 'query', 'rcon'], true),
            ];
        }

        if (! isset($ports['game'])) {
            return [];
        }

        // Game first, then the rest in ascending port order, which is how they
        // read on every page that lists them.
        $game = $ports['game'];
        unset($ports['game']);
        usort($ports, fn (array $a, array $b) => $a['port'] <=> $b['port']);

        return array_merge([$game], $ports);
    }

    /** SERVER_PORT, GAME_PORT and a bare PORT all mean the same listener. */
    private function normaliseRole(string $env): string
    {
        $key = mb_strtolower(preg_replace('/(^|_)PORT$/', '', mb_strtoupper($env)) ?: '');
        $key = trim($key, '_');

        return match ($key) {
            '', 'server', 'game', 'main' => 'game',
            'query', 'a2s', 'steam_query' => 'query',
            'rcon', 'remote' => 'rcon',
            'sftp', 'ftp' => 'sftp',
            default => Str::slug($key, '_'),
        };
    }

    private function protocolFor(string $role): string
    {
        return match ($role) {
            // Every query protocol in use is UDP, and RCON is TCP everywhere
            // except BattlEye. The game port itself stays "both": a definition that
            // never said cannot be narrowed without guessing, and guessing wrong
            // means a game that will not accept a connection.
            'query' => 'udp',
            'rcon', 'sftp' => 'tcp',
            default => 'both',
        };
    }

    private function normaliseProtocol(string $given, string $role): string
    {
        $given = mb_strtolower(trim($given));

        return in_array($given, ['tcp', 'udp', 'both'], true) ? $given : $this->protocolFor($role);
    }

    /**
     * Older definitions wrote these as the integers 0 and 1, newer ones as booleans,
     * and a few as the strings "true" and "false". All three mean the same
     * thing and all three appear in the wild.
     */
    private function flag(mixed $var, string $key): bool
    {
        $raw = data_get($var, $key, true);

        return filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $raw;
    }

    private function str(array $definition, string $key, ?string $default = null): ?string
    {
        $v = data_get($definition, $key);

        return is_string($v) && $v !== '' ? $v : $default;
    }
}
