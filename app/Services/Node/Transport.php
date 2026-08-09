<?php

namespace App\Services\Node;

use App\Models\Node;

/**
 * How the panel reaches a node.
 *
 * Two implementations, and the difference is entirely about who dials whom. A
 * direct node has a reachable port, so the panel opens a connection to it. A
 * reverse node is behind NAT with nothing open at all, so the call is parked
 * and the daemon's own outbound poll comes and collects it.
 *
 * Both answer the same question, "what did the node say", and both answer
 * **null** for "the node did not say anything". That single convention is why
 * `NodeClient` needed almost no changes: every call site already treats null as
 * unreachable, and a deadline that expires is exactly that.
 *
 * An abstract class rather than an interface only so `Transport::for()` can
 * live beside the thing it chooses between.
 */
abstract class Transport
{
    public function __construct(protected Node $node) {}

    public static function for(Node $node): self
    {
        return $node->connection_mode === 'reverse'
            ? new ReverseTransport($node)
            : new DirectTransport($node);
    }

    /**
     * Send a request to the node's HTTP API.
     *
     * $body is the JSON payload for a write, or null for a read. Returns null
     * when the node could not be reached at all, which is different from the
     * node answering with an error status.
     */
    abstract public function send(string $method, string $path, array $query = [], ?array $body = null, ?int $timeout = null): ?NodeResponse;

    /**
     * Send raw bytes, for the one endpoint that takes a file rather than JSON.
     *
     * Separate from send() because the two transports differ here in a way that
     * matters to the caller: a direct node takes a stream of any size, and a
     * reverse node has to fit the whole thing in a parked row.
     */
    abstract public function sendRaw(string $method, string $path, array $query, mixed $body, ?int $bytes, ?int $timeout = null): ?NodeResponse;

    /**
     * Can this transport carry an arbitrarily large body, in either direction?
     *
     * False for reverse, and the callers that stream (upload, backup download)
     * ask before starting rather than discovering it partway through a 40 GB
     * archive.
     */
    abstract public function streams(): bool;

    /** Why not, in words a person can act on. Empty when it can. */
    abstract public function streamingLimit(): string;

    /**
     * The credential the daemon expects.
     *
     * Shared by both transports: a reverse node authenticates the same way, it
     * just receives the header over its own poll instead of over a socket the
     * panel opened.
     */
    protected function daemonToken(): string
    {
        if ($secret = $this->node->daemon_secret) {
            return (string) $secret;
        }

        return (string) (config('node.dev_token') ?: env('NODE_TOKEN', 'gamemgr-dev-node-token'));
    }
}
