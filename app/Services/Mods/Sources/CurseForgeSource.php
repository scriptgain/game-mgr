<?php

namespace App\Services\Mods\Sources;

use App\Models\Server;
use App\Models\Setting;
use App\Services\Mods\Catalogue\CatalogueFile;
use App\Services\Mods\Catalogue\CatalogueProject;
use App\Services\Mods\Catalogue\CatalogueVersion;
use App\Models\Mod;
use App\Services\Mods\Contracts\ModSource;
use App\Services\Mods\Contracts\VariableManagedSource;
use App\Services\Mods\ModTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/**
 * CurseForge, the one source that needs the operator to do something.
 *
 * It is worth having despite that: it is the largest library by a distance and
 * it is the only one of the four that carries games other than Minecraft, which
 * is why the ARK template has always declared it.
 *
 * A KEY PER INSTALL. CurseForge issues API keys per application through
 * console.curseforge.com and a self-hosted panel cannot ship one: a key baked
 * into a public release is a key that gets revoked, and it would put every
 * customer's traffic under one quota. So the key is entered on Settings, Mods,
 * stored with Setting::putSecret the same way the Cloudflare token is, and
 * until one exists this source reports itself unavailable with instructions
 * rather than searching and finding nothing.
 *
 *   GET /v1/mods/search?gameId=&classId=&searchFilter=&gameVersion=&modLoaderType=
 *   GET /v1/mods/{id}
 *   GET /v1/mods/{id}/files?gameVersion=&modLoaderType=
 *
 * ALLOWMODDISTRIBUTION IS A REFUSAL, NOT AN OBSTACLE. An author can switch off
 * third-party downloads, and then the API returns the file with `downloadUrl`
 * null. The well-known workaround is to rebuild the CDN path out of the file id;
 * that is against their terms and it does not go in. Those projects link to
 * their own page instead.
 *
 * Authentication is an `x-api-key` header, verified against the live API: no
 * key and an invalid key both answer 403 "API Key missing or invalid".
 */
final class CurseForgeSource extends HttpSource implements ModSource, VariableManagedSource
{
    /** CurseForge's own game id for Minecraft. */
    private const GAME_MINECRAFT = 432;

    /** Class ids inside Minecraft: 5 is Bukkit plugins, 6 is mods. */
    private const CLASS_PLUGINS = 5;

    private const CLASS_MODS = 6;

    /**
     * modLoaderType, from their docs. The loader decides which of these a
     * search is narrowed to, so a Fabric server is never offered a Forge jar.
     */
    private const LOADERS = [
        'forge' => 1,
        'cauldron' => 2,
        'liteloader' => 3,
        'fabric' => 4,
        'quilt' => 5,
        'neoforge' => 6,
    ];

    public function key(): string
    {
        return 'curseforge';
    }

    public function label(): string
    {
        return 'CurseForge';
    }

    public function available(): bool
    {
        return (bool) config('mods.curseforge.enabled', true) && $this->apiKey() !== null;
    }

    public function unavailableReason(): ?string
    {
        if (! config('mods.curseforge.enabled', true)) {
            return 'CurseForge is turned off in this panel\'s configuration.';
        }

        if ($this->apiKey() === null) {
            return 'No CurseForge API key is saved. Get one free from console.curseforge.com and enter it in Settings, Mods.';
        }

        return null;
    }

    /** Stored encrypted, never in .env, and never echoed back into a form. */
    public function apiKey(): ?string
    {
        $stored = Setting::secret('mods_curseforge_key');

        if (filled($stored)) {
            return $stored;
        }

        $fallback = (string) config('mods.curseforge.api_key', '');

        return $fallback !== '' ? $fallback : null;
    }

    protected function baseUrl(): string
    {
        return (string) config('mods.curseforge.base', 'https://api.curseforge.com');
    }

    protected function client(): PendingRequest
    {
        return parent::client()->withHeaders(['x-api-key' => (string) $this->apiKey()]);
    }

    protected function trustedHost(string $url): bool
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'edge.forgecdn.net'
            || $host === 'mediafilez.forgecdn.net'
            || str_ends_with($host, '.forgecdn.net');
    }

    /**
     * Any game this template names a CurseForge id for.
     *
     * It used to be "does this server run a Minecraft loader", which quietly
     * excluded the one non-Minecraft template that has declared CurseForge
     * since the catalogue was written.
     */
    public function supports(ModTarget $target): bool
    {
        if ($target->curseForgeGameId <= 0) {
            return false;
        }

        return $target->curseForgeIsMinecraft() ? $target->loader !== null : true;
    }

    public function search(string $query, ModTarget $target, int $limit = 20): ?array
    {
        $body = $this->fetch(
            'search:'.md5($query.'|'.$limit.'|'.$target->curseForgeGameId.'|'.$target->loader.'|'.$target->gameVersion),
            '/v1/mods/search',
            array_filter([
                'gameId' => $target->curseForgeGameId,
                // classId, gameVersion and modLoaderType describe Minecraft.
                // Sending them for another game filters everything out.
                'classId' => $target->curseForgeIsMinecraft() ? $this->classFor($target) : null,
                'searchFilter' => $query,
                'gameVersion' => $target->curseForgeIsMinecraft() ? $target->gameVersion : null,
                'modLoaderType' => $target->curseForgeIsMinecraft() ? $this->loaderFor($target) : null,
                'pageSize' => max(1, min(25, $limit)),
                'sortField' => 6,      // total downloads
                'sortOrder' => 'desc',
            ], fn ($v) => $v !== null && $v !== ''),
        );

        if ($body === null) {
            return null;
        }

        $out = [];

        foreach ((array) ($body['data'] ?? []) as $hit) {
            if (is_array($hit) && ($project = $this->toProject($hit, $target->curseForgeIsMinecraft())) !== null) {
                $out[] = $project;
            }
        }

        return $out;
    }

    public function project(string $id): ?CatalogueProject
    {
        $id = self::cleanId($id);

        if ($id === null) {
            return null;
        }

        $body = $this->fetch('project:'.$id, '/v1/mods/'.$id);

        return is_array($body['data'] ?? null) ? $this->toProject($body['data']) : null;
    }

    public function latestVersion(string $id, ModTarget $target): ?CatalogueVersion
    {
        $id = self::cleanId($id);

        if ($id === null) {
            return null;
        }

        $body = $this->fetch(
            'versions:'.$id.':'.$target->loader.':'.$target->gameVersion,
            '/v1/mods/'.$id.'/files',
            array_filter([
                'gameVersion' => $target->curseForgeIsMinecraft() ? $target->gameVersion : null,
                'modLoaderType' => $target->curseForgeIsMinecraft() ? $this->loaderFor($target) : null,
                'pageSize' => 20,
            ], fn ($v) => $v !== null && $v !== ''),
        );

        if ($body === null) {
            return null;
        }

        $files = array_values(array_filter((array) ($body['data'] ?? []), 'is_array'));

        if ($files === []) {
            return null;
        }

        // releaseType 1 is Release, 2 Beta, 3 Alpha. Same reasoning as Hangar's
        // channel rule: nobody asked for a beta build on their live server.
        usort($files, static fn ($a, $b) => [$a['releaseType'] ?? 9, $b['fileDate'] ?? ''] <=> [$b['releaseType'] ?? 9, $a['fileDate'] ?? '']);

        $file = $files[0];
        $number = (string) ($file['displayName'] ?? $file['fileName'] ?? $file['id']);
        $url = self::text($file['downloadUrl'] ?? null);

        // The author switched off third-party distribution. Nothing to fetch,
        // and rebuilding the CDN path is against their terms.
        if ($url === null) {
            return new CatalogueVersion(
                id: (string) ($file['id'] ?? $number),
                number: $number,
                file: null,
                externalUrl: 'https://www.curseforge.com/minecraft/mc-mods/'.$id,
            );
        }

        [$checksum, $algo] = self::hashOf($file);

        return new CatalogueVersion(
            id: (string) ($file['id'] ?? $number),
            number: $number,
            file: new CatalogueFile(
                url: $url,
                filename: (string) ($file['fileName'] ?? $number.'.jar'),
                size: (int) ($file['fileLength'] ?? 0),
                checksum: $checksum,
                checksumAlgo: $algo,
                // Their gameVersions array mixes Minecraft versions and loader
                // names, so the loaders are read out of it rather than guessed;
                // an empty result lets ModTarget fall back to the server's own
                // single directory, and refuse if it is a hybrid.
                loaders: self::loadersOf($file),
            ),
        );
    }

    // ------------------------------------------------- the ARK-shaped install

    /**
     * Which variable carries the list.
     *
     * Only meaningful for a game that fetches its own mods. Minecraft installs
     * still download a file, and this is never consulted for them.
     */
    public function listVariable(): string
    {
        return 'MOD_IDS';
    }

    /**
     * Does this server take a list rather than a file?
     *
     * Asked of the TEMPLATE rather than assumed from the game, so a template
     * that gains the variable later works without a code change.
     */
    public function managesByList(ModTarget $target): bool
    {
        return ! $target->curseForgeIsMinecraft() && $target->hasVariable($this->listVariable());
    }

    public function addToList(Server $server, CatalogueProject $project): array
    {
        $ids = self::idList($server, $this->listVariable());

        if (in_array($project->id, $ids, true)) {
            return ['ok' => false, 'error' => $project->name.' is already in this server\'s mod list.'];
        }

        $ids[] = $project->id;

        if (! $this->writeList($server, $ids)) {
            return ['ok' => false, 'error' => 'This template has no '.$this->listVariable().' variable to write to.'];
        }

        return ['ok' => true, 'list' => implode(',', $ids)];
    }

    public function removeFromList(Server $server, Mod $mod): array
    {
        $ids = array_values(array_diff(self::idList($server, $this->listVariable()), [(string) $mod->remote_id]));

        if (! $this->writeList($server, $ids)) {
            return ['ok' => false, 'error' => 'This template has no '.$this->listVariable().' variable to write to.'];
        }

        return ['ok' => true, 'list' => implode(',', $ids)];
    }

    /** @param array<int,string> $ids */
    private function writeList(Server $server, array $ids): bool
    {
        $variable = $server->template?->variables->firstWhere('env_variable', $this->listVariable());

        if ($variable === null) {
            return false;
        }

        \App\Models\ServerVariable::updateOrCreate(
            ['server_id' => $server->id, 'template_variable_id' => $variable->id],
            ['value' => implode(',', $ids)],
        );

        return true;
    }

    /**
     * The ids currently on the server, read from its own environment so a value
     * set by hand on the Startup tab is respected rather than overwritten.
     *
     * @return array<int,string>
     */
    private static function idList(Server $server, string $variable): array
    {
        $raw = (string) ($server->environment()[$variable] ?? '');

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn ($id) => $id !== '' && preg_match('/^\d+$/', $id) === 1,
        ));
    }

    /** Bukkit plugins and mods are different classes and must not be mixed. */
    private function classFor(ModTarget $target): int
    {
        return in_array('bukkit', $target->loaders, true) || in_array('spigot', $target->loaders, true)
            ? self::CLASS_PLUGINS
            : self::CLASS_MODS;
    }

    private function loaderFor(ModTarget $target): ?int
    {
        foreach ($target->loaders as $loader) {
            if (isset(self::LOADERS[$loader])) {
                return self::LOADERS[$loader];
            }
        }

        return null;
    }

    /** @return array{0:?string,1:?string} */
    private static function hashOf(array $file): array
    {
        // algo 1 is sha1, 2 is md5. sha1 is preferred; md5 is still better than
        // installing something nobody checked.
        foreach ([1 => 'sha1', 2 => 'md5'] as $algo => $name) {
            foreach ((array) ($file['hashes'] ?? []) as $hash) {
                if ((int) ($hash['algo'] ?? 0) === $algo && filled($hash['value'] ?? null)) {
                    return [(string) $hash['value'], $name];
                }
            }
        }

        return [null, null];
    }

    /** @return array<int,string> */
    private static function loadersOf(array $file): array
    {
        $known = array_merge(array_keys(self::LOADERS), ['bukkit', 'spigot', 'paper']);
        $out = [];

        foreach ((array) ($file['gameVersions'] ?? []) as $value) {
            $value = strtolower(trim((string) $value));

            if (in_array($value, $known, true)) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    private function toProject(array $hit, bool $minecraft = true): ?CatalogueProject
    {
        $id = self::text($hit['id'] ?? null);

        if ($id === null) {
            return null;
        }

        $authors = (array) ($hit['authors'] ?? []);

        return new CatalogueProject(
            id: $id,
            slug: (string) ($hit['slug'] ?? $id),
            name: (string) ($hit['name'] ?? 'Untitled'),
            summary: Str::limit((string) ($hit['summary'] ?? ''), 480),
            author: self::text($authors[0]['name'] ?? null),
            downloads: (int) ($hit['downloadCount'] ?? 0),
            icon: self::text($hit['logo']['thumbnailUrl'] ?? null),
            url: self::text($hit['links']['websiteUrl'] ?? null),
            /*
             * allowModDistribution means "may a third party hand out the file",
             * which is the right question when the panel is going to download
             * one. For a game that fetches its own mods it is the wrong
             * question entirely: every ASA mod has it switched off and every
             * one of them installs perfectly well by id. So a non-Minecraft
             * game is always installable here, and Minecraft still respects it.
             */
            installable: $minecraft ? (bool) ($hit['allowModDistribution'] ?? true) : true,
        );
    }

    private static function cleanId(string $id): ?string
    {
        $id = trim($id);

        return preg_match('/^\d{1,12}$/', $id) === 1 ? $id : null;
    }

    private static function text(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
