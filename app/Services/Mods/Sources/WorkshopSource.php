<?php

namespace App\Services\Mods\Sources;

use App\Models\Server;
use App\Models\Setting;
use App\Services\Mods\Catalogue\CatalogueFile;
use App\Services\Mods\Catalogue\CatalogueProject;
use App\Services\Mods\Catalogue\CatalogueVersion;
use App\Services\Mods\Contracts\ModSource;
use App\Services\Mods\Contracts\NodeInstalledSource;
use App\Services\Mods\ModTarget;
use App\Services\NodeClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The Steam Workshop, which is unlike the other three in every way that matters.
 *
 * There is no jar and no directory convention. A Workshop item is fetched by
 * steamcmd ON THE NODE, because Valve serves it only to an authenticated Steam
 * client, so the panel never sees the bytes and there is no checksum to verify:
 * Steam is the transport and Steam is the verification. That is why this source
 * implements NodeInstalledSource and takes a different path through the
 * installer.
 *
 * TWO WAYS IN, and the one that needs no key is the normal one:
 *
 *   By id or URL. ISteamRemoteStorage/GetPublishedFileDetails is a POST, takes
 *   ids, and needs no key at all. This is how people actually arrive: they find
 *   an item on the Workshop website and come back with a link.
 *
 *   By search. IPublishedFileService/QueryFiles DOES need a Steam Web API key,
 *   free from steamcommunity.com/dev/apikey and entered on Settings, Mods. With
 *   no key the search box is simply absent rather than broken.
 *
 * WHERE IT LANDS, and what is not done. steamcmd puts the item in
 * <server>/steamapps/workshop/content/<app>/<item>, which is where the games
 * that read Workshop content look, so it is left there. ARK is the exception:
 * it wants mods unpacked into ShooterGame/Content/Mods with its own .z files
 * expanded, and that conversion is NOT implemented. An ARK item downloads
 * correctly and is not yet where ARK reads it. Counter-Strike and Garry's Mod,
 * which read straight out of the workshop directory, work today. Saying that
 * out loud beats an ARK owner discovering it.
 */
final class WorkshopSource extends HttpSource implements ModSource, NodeInstalledSource
{
    public function key(): string
    {
        return 'workshop';
    }

    public function label(): string
    {
        return 'Steam Workshop';
    }

    /** Installing by id needs nothing, so this source is always usable. */
    public function available(): bool
    {
        return (bool) config('mods.workshop.enabled', true);
    }

    public function unavailableReason(): ?string
    {
        return $this->available() ? null : 'The Steam Workshop is turned off in this panel\'s configuration.';
    }

    /** Only searching needs a credential. */
    public function canSearch(): bool
    {
        return $this->apiKey() !== null;
    }

    public function apiKey(): ?string
    {
        $stored = Setting::secret('mods_steam_key');

        if (filled($stored)) {
            return $stored;
        }

        $fallback = (string) config('mods.workshop.api_key', '');

        return $fallback !== '' ? $fallback : null;
    }

    protected function baseUrl(): string
    {
        return (string) config('mods.workshop.base', 'https://api.steampowered.com');
    }

    /** Nothing is ever downloaded by the panel, so nothing is ever trusted. */
    protected function trustedHost(string $url): bool
    {
        return false;
    }

    /** Steam games only, and only where the template says the game uses it. */
    public function supports(ModTarget $target): bool
    {
        return in_array('workshop', $target->sources, true);
    }

    public function search(string $query, ModTarget $target, int $limit = 20): ?array
    {
        // Without a key there is no search endpoint to call. An empty list, not
        // null: nothing failed, there is simply nothing to search with, and the
        // screen tells them to paste an id instead.
        if (! $this->canSearch()) {
            return [];
        }

        $body = $this->fetch(
            'search:'.md5($query.'|'.$limit.'|'.$this->appId($target)),
            '/IPublishedFileService/QueryFiles/v1/',
            [
                'key' => $this->apiKey(),
                'search_text' => $query,
                'appid' => $this->appId($target),
                'numperpage' => max(1, min(25, $limit)),
                'return_short_description' => true,
                'query_type' => 3,     // ranked by text match
                'filetype' => 0,       // published items, not screenshots
            ],
        );

        if ($body === null) {
            return null;
        }

        $out = [];

        foreach ((array) ($body['response']['publishedfiledetails'] ?? []) as $hit) {
            if (is_array($hit) && ($project = $this->toProject($hit)) !== null) {
                $out[] = $project;
            }
        }

        return $out;
    }

    /**
     * One item by id, with no key.
     *
     * A URL is accepted as well as a bare number, because that is what people
     * have in their clipboard when they come back from the Workshop.
     */
    public function project(string $id): ?CatalogueProject
    {
        $id = self::cleanId($id);

        if ($id === null) {
            return null;
        }

        try {
            // A POST with form fields, which is what this endpoint takes, so it
            // does not go through the cached GET helper.
            $response = Http::asForm()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->timeout($this->seconds())
                ->post(
                    rtrim($this->baseUrl(), '/').'/ISteamRemoteStorage/GetPublishedFileDetails/v1/',
                    ['itemcount' => 1, 'publishedfileids[0]' => $id],
                );

            if (! $response->successful()) {
                $this->markDown();

                return null;
            }

            $item = $response->json('response.publishedfiledetails.0');
        } catch (Throwable) {
            $this->markDown();

            return null;
        }

        // result 1 is success. Anything else means no such item, or it is
        // hidden or removed, all of which are "not found" from here.
        if (! is_array($item) || (int) ($item['result'] ?? 0) !== 1) {
            return null;
        }

        return $this->toProject($item);
    }

    /**
     * Workshop items have no version numbers, only an updated timestamp, which
     * is what "is there an update" has to compare.
     */
    public function latestVersion(string $id, ModTarget $target): ?CatalogueVersion
    {
        $project = $this->project($id);

        if ($project === null) {
            return null;
        }

        // The file is null on purpose: nothing here is fetched by the panel.
        // ModInstaller sees NodeInstalledSource and takes the other path.
        return new CatalogueVersion(
            id: $project->id,
            number: $project->slug,
            file: null,
            externalUrl: $project->url,
        );
    }

    /** Never called: the panel does not download Workshop content. */
    public function download(CatalogueFile $file, int $maxBytes): array
    {
        return ['ok' => false, 'error' => 'Workshop items are fetched by steamcmd on the node, not by the panel.'];
    }

    public function installOnNode(Server $server, CatalogueProject $project): array
    {
        $appId = (int) ($server->template?->steam_app_id ?? 0);

        if ($appId <= 0) {
            return ['ok' => false, 'error' => 'This template has no Steam app id, so there is no Workshop to fetch from.'];
        }

        $result = NodeClient::for($server->node)->workshopInstall($server, $appId, (int) $project->id);

        if (! ($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'The node could not fetch that Workshop item.')];
        }

        return [
            'ok' => true,
            'path' => (string) ($result['path'] ?? ''),
            'version' => $project->slug,
        ];
    }

    /** The app whose Workshop is being searched. */
    private function appId(ModTarget $target): int
    {
        return (int) ($target->steamAppId ?? 0);
    }

    private function toProject(array $item): ?CatalogueProject
    {
        $id = self::text($item['publishedfileid'] ?? null);

        if ($id === null) {
            return null;
        }

        // Updated-at stands in for a version, because that is the only thing
        // that changes when an author republishes an item.
        $updated = (int) ($item['time_updated'] ?? $item['time_created'] ?? 0);

        return new CatalogueProject(
            id: $id,
            slug: $updated > 0 ? date('Y-m-d', $updated) : 'unknown',
            name: (string) ($item['title'] ?? 'Workshop item '.$id),
            summary: Str::limit(strip_tags((string) ($item['short_description'] ?? $item['description'] ?? '')), 480),
            author: null,
            downloads: (int) ($item['subscriptions'] ?? $item['favorited'] ?? 0),
            icon: self::text($item['preview_url'] ?? null),
            url: 'https://steamcommunity.com/sharedfiles/filedetails/?id='.$id,
        );
    }

    /** A bare id, or the id out of any Workshop URL somebody pasted. */
    public static function cleanId(string $id): ?string
    {
        $id = trim($id);

        if (preg_match('/^\d{1,20}$/', $id) === 1) {
            return $id;
        }

        if (preg_match('/[?&]id=(\d{1,20})/', $id, $m) === 1) {
            return $m[1];
        }

        return null;
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
