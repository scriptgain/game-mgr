<?php

namespace App\Services\Dns;

use Illuminate\Support\Facades\Cache;

/**
 * A provider that talks to nothing.
 *
 * This is a real, supported choice rather than a test double. It is what the
 * dev stack runs, and it is what an operator picks who wants names without
 * handing the panel credentials to their DNS account: the panel tells them the
 * record to create, and behaves as though it created it.
 *
 * Writes are remembered in the cache so the node page can still show the
 * difference between "never synced" and "synced, here is the record", which is
 * the whole point of the wildcard row.
 */
class NullProvider implements Provider
{
    public function name(): string
    {
        return 'null';
    }

    public function findRecord(string $zone, string $type, string $name): ?DnsRecord
    {
        $row = Cache::get($this->key($zone, $type, $name));

        return is_array($row) ? new DnsRecord(...$row) : null;
    }

    public function upsertRecord(string $zone, string $type, string $name, string $content, ?int $ttl = null): DnsRecord
    {
        $record = new DnsRecord(
            id: 'null-'.substr(md5($zone.$type.$name), 0, 12),
            type: $type,
            name: $name,
            content: $content,
            ttl: $ttl ?: (int) config('domains.ttl', 120),
            proxied: false,
        );

        Cache::forever($this->key($zone, $type, $name), [
            'id' => $record->id,
            'type' => $record->type,
            'name' => $record->name,
            'content' => $record->content,
            'ttl' => $record->ttl,
            'proxied' => false,
        ]);

        return $record;
    }

    public function deleteRecord(string $zone, string $type, string $name): bool
    {
        $key = $this->key($zone, $type, $name);
        if (! Cache::has($key)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    private function key(string $zone, string $type, string $name): string
    {
        return 'dns.null.'.md5(mb_strtolower($zone.'|'.$type.'|'.$name));
    }
}
