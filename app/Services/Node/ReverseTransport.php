<?php

namespace App\Services\Node;

use App\Models\NodeCall;

/**
 * The node dials the panel, so the panel parks the work and waits.
 *
 * There is no socket to open: a reverse node sits behind NAT on somebody's home
 * connection with nothing forwarded. The daemon holds a long poll against the
 * panel, this writes a row, that poll picks it up, and the answer comes back as
 * a second request from the node.
 *
 * The wait below is a busy poll of one indexed row every 100ms rather than
 * anything cleverer. Both the cache and the queue in this app are the database,
 * PHP-FPM cannot hold a socket open between requests, and asking every
 * self-hoster to run Redis or a websocket server to control a node behind their
 * own router would be a worse trade than a few small SELECTs.
 *
 * WHAT IT COSTS, since it is not free: the panel holds one PHP-FPM worker for
 * the duration of each call, and each reverse node holds one more while its
 * poll is parked. Fine for the handful of nodes a self-hosted panel has;
 * not fine for hundreds. config('node.reverse') is where the knobs are.
 */
class ReverseTransport extends Transport
{
    public function send(string $method, string $path, array $query = [], ?array $body = null, ?int $timeout = null): ?NodeResponse
    {
        return $this->dispatch(
            $method,
            $path,
            $query,
            $body === null ? null : json_encode($body),
            $timeout,
        );
    }

    public function sendRaw(string $method, string $path, array $query, mixed $body, ?int $bytes, ?int $timeout = null): ?NodeResponse
    {
        $max = (int) config('node.reverse.max_payload', 8 * 1024 * 1024);

        // Read it here rather than streaming it, because a parked row is the
        // only way through and a row has to hold the whole thing. The cap is
        // checked against the declared length first so an oversized upload is
        // refused before the bytes are read into memory, not after.
        if ($bytes !== null && $bytes > $max) {
            return new NodeResponse(413, json_encode([
                'ok' => false,
                'error' => $this->streamingLimit(),
            ]));
        }

        $raw = is_resource($body) ? stream_get_contents($body) : (string) $body;

        if (strlen($raw) > $max) {
            return new NodeResponse(413, json_encode([
                'ok' => false,
                'error' => $this->streamingLimit(),
            ]));
        }

        return $this->dispatch($method, $path, $query, $raw, $timeout, base64: true);
    }

    /** No. That is the trade a reverse node makes. */
    public function streams(): bool
    {
        return false;
    }

    public function streamingLimit(): string
    {
        $bytes = (int) config('node.reverse.max_payload', 8 * 1024 * 1024);

        // In whichever unit does not read as zero. A cap configured below a
        // megabyte rounded to "0 MB", which tells somebody their upload was
        // refused for no reason at all.
        $size = $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';

        return 'This node connects out to the panel rather than being dialled, so a file has to travel '
            .'inside that connection and is capped at '.$size.'. Larger files need a directly reachable node.';
    }

    /**
     * Park the call, then wait for the daemon to answer it.
     *
     * Checks once before sleeping. A daemon whose poll is already parked
     * answers in a few milliseconds, and sleeping first would add 100ms of
     * latency to every call in the common case.
     */
    private function dispatch(string $method, string $path, array $query, ?string $body, ?int $timeout, bool $base64 = false): ?NodeResponse
    {
        $timeout ??= (int) config('node.timeout', 10);

        $call = NodeCall::park(
            $this->node,
            $method,
            $path,
            $query,
            $body === null ? null : ($base64 ? base64_encode($body) : $body),
            $timeout,
        );

        if ($base64) {
            $call->forceFill(['query' => $query + ['__encoding' => 'base64']])->save();
        }

        $deadline = microtime(true) + $timeout;
        $interval = (int) config('node.reverse.poll_interval_ms', 100) * 1000;

        do {
            $row = NodeCall::select(['state', 'response_status', 'response_body'])->find($call->id);

            if ($row && $row->state === 'done') {
                return new NodeResponse((int) $row->response_status, (string) $row->response_body);
            }

            usleep($interval);
        } while (microtime(true) < $deadline);

        // Nobody came. Null is the same answer a dead direct node gives, which
        // is what every caller already knows how to handle.
        return null;
    }
}
