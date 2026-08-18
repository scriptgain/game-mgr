<?php

namespace App\Services\Node;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The panel opens a connection to the node. What GameMGR has always done, moved
 * here unchanged so the reverse case has something to be an alternative to.
 */
class DirectTransport extends Transport
{
    public function send(string $method, string $path, array $query = [], ?array $body = null, ?int $timeout = null): ?NodeResponse
    {
        try {
            $client = $this->http();
            if ($timeout !== null) {
                $client = $client->timeout($timeout);
            }

            $url = $this->node->daemonUrl($path);

            $res = strtoupper($method) === 'GET'
                ? $client->get($url, $query)
                : $client->send(strtoupper($method), $url.$this->queryString($query), ['json' => $body ?? []]);

            return new NodeResponse($res->status(), $res->body());
        } catch (\Throwable $e) {
            $this->note($e);

            return null;
        }
    }

    public function sendRaw(string $method, string $path, array $query, mixed $body, ?int $bytes, ?int $timeout = null): ?NodeResponse
    {
        try {
            $request = Http::withToken($this->daemonToken())
                ->when(! $this->node->verifyTls(), fn ($c) => $c->withoutVerifying())
                ->withOptions([
                    'connect_timeout' => 10,
                    // A large file over a slow link is minutes, not seconds, so
                    // the ten second default every other call uses would abort
                    // exactly the uploads this exists for.
                    'read_timeout' => $timeout ?? (int) config('node.upload_timeout', 3600),
                    'timeout' => $timeout ?? (int) config('node.upload_timeout', 3600),
                ])
                ->withBody($body, 'application/octet-stream');

            // Guzzle cannot measure a stream it did not open, and without a
            // length it falls back to chunked. The daemon copes either way, but
            // a declared length lets it refuse an oversized upload before the
            // bytes are sent rather than after.
            if ($bytes !== null) {
                $request = $request->withHeaders(['Content-Length' => (string) $bytes]);
            }

            $res = $request->send(strtoupper($method), $this->node->daemonUrl($path).$this->queryString($query));

            return new NodeResponse($res->status(), $res->body());
        } catch (\Throwable $e) {
            $this->note($e);

            return null;
        }
    }

    /** A reachable node takes as many bytes as the two ends are willing to move. */
    public function streams(): bool
    {
        return true;
    }

    public function streamingLimit(): string
    {
        return '';
    }

    /**
     * Open a stream on a large response, which only a direct node can do.
     *
     * Kept on this class rather than on Transport: a reverse node has no answer
     * to it, and an abstract method every caller has to guard is worse than a
     * capability they ask about first with streams().
     */
    public function stream(string $path)
    {
        try {
            $response = Http::withToken($this->daemonToken())
                ->when(! $this->node->verifyTls(), fn ($c) => $c->withoutVerifying())
                ->withOptions(['stream' => true, 'connect_timeout' => 10, 'read_timeout' => 3600])
                ->get($this->node->daemonUrl($path));

            return $response->successful() ? $response->toPsrResponse()->getBody() : null;
        } catch (\Throwable $e) {
            $this->note($e);

            return null;
        }
    }

    private function http()
    {
        return Http::withToken($this->daemonToken())
            ->timeout((int) config('node.timeout', 10))
            ->acceptJson()
            // Self-signed certificates are the norm on a freshly installed
            // node, and the bearer token is what actually authenticates the
            // call. A node behind a proxy has a real cert, so verify there;
            // Node::verifyTls() decides, and NODE_TLS_VERIFY can force it on.
            ->when(! $this->node->verifyTls(), fn ($c) => $c->withoutVerifying());
    }

    private function queryString(array $query): string
    {
        return $query === [] ? '' : '?'.http_build_query($query);
    }

    private function note(\Throwable $e): void
    {
        Log::debug('node call failed', ['node' => $this->node->name, 'error' => $e->getMessage()]);
    }
}
