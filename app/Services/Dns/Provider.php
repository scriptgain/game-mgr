<?php

namespace App\Services\Dns;

/**
 * A DNS provider the panel can write to.
 *
 * Three methods, because phase 1 needs exactly three things: put a record
 * there, check it is still there, take it away. Route53, PowerDNS or a manual
 * "here are the records to create yourself" mode all fit this without a single
 * caller changing.
 *
 * Implementations throw DnsException and nothing else. Callers treat a throw as
 * "record the failure and try again later", never as an error to show the user
 * mid-request.
 *
 * $zone is the name suffix the panel builds names under, for example
 * play.scriptgain.com. It is not necessarily the provider's own zone, and
 * mapping one to the other is the driver's problem.
 */
interface Provider
{
    /** cloudflare, null. Shown in the UI so an operator knows what is talking. */
    public function name(): string;

    /** The record as the provider currently holds it, or null if absent. */
    public function findRecord(string $zone, string $type, string $name): ?DnsRecord;

    /**
     * Create the record, or point the existing one at $content.
     *
     * Never proxied. Game traffic is raw UDP and TCP, and an orange-clouded
     * record silently breaks every server under it, so this is not a parameter
     * a caller can get wrong.
     */
    public function upsertRecord(string $zone, string $type, string $name, string $content, ?int $ttl = null): DnsRecord;

    /** True if the record was removed, false if it was not there to begin with. */
    public function deleteRecord(string $zone, string $type, string $name): bool;
}
