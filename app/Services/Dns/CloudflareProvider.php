<?php

namespace App\Services\Dns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare's v4 API, over an API token.
 *
 * Two things are deliberately not configurable:
 *
 * 1. `proxied` is always false. Game traffic is raw UDP and TCP and cannot
 *    traverse Cloudflare's proxy, so an orange record does not degrade a game
 *    server, it silently kills it. The driver refuses to send anything else.
 * 2. The zone id is never hardcoded and never asked for. It is looked up from
 *    the zone name and cached, so an operator pastes a token and a domain and
 *    nothing else.
 *
 * The configured zone is a name suffix (play.scriptgain.com), which is often
 * not the registrable domain the account holds (scriptgain.com). Rather than
 * ask for both, the lookup walks up the labels until a zone matches.
 */
class CloudflareProvider implements Provider
{
    private const BASE = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private readonly string $token,
        private readonly float $timeout = 5.0,
        private readonly float $connectTimeout = 3.0,
    ) {}

    public function name(): string
    {
        return 'cloudflare';
    }

    public function findRecord(string $zone, string $type, string $name): ?DnsRecord
    {
        $body = $this->call('get', '/zones/'.$this->zoneId($zone).'/dns_records', [
            'type' => $type,
            'name' => $name,
            'per_page' => 1,
        ]);

        $row = $body['result'][0] ?? null;

        return $row ? $this->hydrate($row) : null;
    }

    public function upsertRecord(string $zone, string $type, string $name, string $content, ?int $ttl = null): DnsRecord
    {
        $zoneId = $this->zoneId($zone);
        $existing = $this->findRecord($zone, $type, $name);

        $payload = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl ?: (int) config('domains.ttl', 120),
            // Not a preference, not a setting, not an argument. See the class
            // comment: an orange record breaks every game server under it.
            'proxied' => false,
        ];

        $body = $existing
            ? $this->call('put', '/zones/'.$zoneId.'/dns_records/'.$existing->id, $payload)
            : $this->call('post', '/zones/'.$zoneId.'/dns_records', $payload);

        $row = $body['result'] ?? null;
        if (! is_array($row)) {
            throw new DnsException('Cloudflare accepted the write but returned no record.');
        }

        return $this->hydrate($row);
    }

    public function deleteRecord(string $zone, string $type, string $name): bool
    {
        $existing = $this->findRecord($zone, $type, $name);
        if (! $existing) {
            return false;
        }

        $this->call('delete', '/zones/'.$this->zoneId($zone).'/dns_records/'.$existing->id);

        return true;
    }

    // ------------------------------------------------------------ internals

    /**
     * The Cloudflare zone that holds $suffix.
     *
     * play.scriptgain.com is not a zone, scriptgain.com is. Walking up costs at
     * most a handful of requests once an hour, and it means the operator never
     * has to know the difference or paste a zone id.
     */
    private function zoneId(string $suffix): string
    {
        $suffix = mb_strtolower(trim($suffix, " \t\n\r\0\x0B."));
        if ($suffix === '') {
            throw new DnsException('No zone is configured.');
        }

        // Keyed on the token as well, so replacing a token re-resolves rather
        // than serving an id the new token may have no access to.
        $key = 'dns.cloudflare.zone.'.md5($suffix.'|'.$this->token);

        $id = Cache::remember($key, now()->addHour(), function () use ($suffix) {
            $labels = explode('.', $suffix);

            // Try the exact name first, then each parent. Stop at two labels:
            // "com" is never a zone anybody holds.
            while (count($labels) >= 2) {
                $candidate = implode('.', $labels);
                $body = $this->call('get', '/zones', ['name' => $candidate, 'per_page' => 1]);
                $found = $body['result'][0]['id'] ?? null;
                if (is_string($found) && $found !== '') {
                    return $found;
                }
                array_shift($labels);
            }

            return '';
        });

        if (! is_string($id) || $id === '') {
            Cache::forget($key);
            throw new DnsException('No Cloudflare zone found for '.$suffix.'. Check the token has Zone.DNS edit on that domain.');
        }

        return $id;
    }

    private function hydrate(array $row): DnsRecord
    {
        return new DnsRecord(
            id: (string) ($row['id'] ?? ''),
            type: (string) ($row['type'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            content: (string) ($row['content'] ?? ''),
            ttl: (int) ($row['ttl'] ?? 120),
            proxied: (bool) ($row['proxied'] ?? false),
        );
    }

    /**
     * One request, one shape of failure.
     *
     * Cloudflare answers 200 with success:false often enough that checking the
     * status code alone is a bug, so both are checked and both become the same
     * DnsException the callers already handle.
     */
    private function call(string $method, string $path, array $data = []): array
    {
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->{$method}(self::BASE.$path, $data);
        } catch (\Throwable $e) {
            throw new DnsException('Cloudflare unreachable: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful() || ($body['success'] ?? false) !== true) {
            throw new DnsException($this->errorFrom($response->status(), $body));
        }

        return $body;
    }

    private function errorFrom(int $status, array $body): string
    {
        $errors = collect($body['errors'] ?? [])
            ->map(fn ($e) => trim(($e['code'] ?? '').' '.($e['message'] ?? '')))
            ->filter()
            ->all();

        if ($errors) {
            return 'Cloudflare: '.implode('; ', $errors);
        }

        return match ($status) {
            401, 403 => 'Cloudflare rejected the API token. It needs Zone.DNS edit on the zone.',
            429 => 'Cloudflare rate limited the panel. The hourly sync will try again.',
            default => 'Cloudflare returned HTTP '.$status.'.',
        };
    }
}
