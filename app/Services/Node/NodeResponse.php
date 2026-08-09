<?php

namespace App\Services\Node;

/**
 * What a node said, whichever way the call reached it.
 *
 * Deliberately smaller than an HTTP response. A direct call has headers, a
 * cookie jar and a PSR stream; a call that travelled as a parked row has a
 * status and some bytes. Narrowing to what both can honestly provide is what
 * lets `NodeClient` stop caring which transport it used.
 */
final class NodeResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {}

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** The body as an array, or null if it was not JSON. */
    public function json(): ?array
    {
        if ($this->body === '') {
            return null;
        }

        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
