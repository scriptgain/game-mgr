<?php

namespace App\Services\Dns;

/**
 * One record as a provider sees it. Deliberately tiny: the panel only ever
 * needs to know what a name points at and whether the provider is proxying it.
 */
class DnsRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $content,
        public readonly int $ttl = 120,
        public readonly bool $proxied = false,
    ) {}

    public function matches(string $content): bool
    {
        return mb_strtolower(trim($this->content)) === mb_strtolower(trim($content));
    }
}
