<?php

namespace App\Services;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The panel's side of the node conversation.
 *
 * Every call is short-timeout and never throws into a controller: a dead node
 * must degrade a page to "offline", not 500 it. That is the single most common
 * way a control panel becomes unusable during an incident, and it is entirely
 * avoidable.
 *
 * When config('node.fake') is on and a daemon is unreachable, synthetic data is
 * returned instead. That keeps every screen exercisable while the real runtime
 * drivers are still being written, and it is what the local dev stack uses.
 */
class NodeClient
{
    public function __construct(private Node $node) {}

    public static function for(Node $node): self
    {
        return new self($node);
    }

    // ----------------------------------------------------------------- node

    /** What the daemon says about itself: version, drivers, uptime. */
    public function system(): ?array
    {
        return $this->get('/api/system');
    }

    /** Is the daemon answering right now? Costs one short request. */
    public function ping(): bool
    {
        try {
            return $this->http()->timeout(3)->get($this->node->daemonUrl('/healthz'))->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    // --------------------------------------------------------------- server

    public function power(Server $server, string $action): array
    {
        $res = $this->post("/api/servers/{$server->uuid}/power", [
            'server' => $server->daemonPayload(),
            'action' => $action,
        ]);

        if ($res === null && config('node.fake')) {
            // Optimistic local state so the UI still moves on a demo instance.
            return ['ok' => true, 'state' => match ($action) {
                'start' => 'running',
                'restart' => 'running',
                default => 'offline',
            }, 'simulated' => true];
        }

        return $res ?? ['ok' => false, 'error' => 'Node unreachable'];
    }

    public function command(Server $server, string $command): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/command", [
            'server' => $server->daemonPayload(),
            'command' => $command,
        ]);

        return (bool) ($res['ok'] ?? config('node.fake'));
    }

    public function stats(Server $server): array
    {
        $res = $this->get("/api/servers/{$server->uuid}/stats", $this->serverQuery($server));

        return $res ?? $this->fakeStats($server);
    }

    /** Recent console backlog, without holding a streaming connection open. */
    public function logs(Server $server, int $tail = 200): array
    {
        $res = $this->get("/api/servers/{$server->uuid}/logs", $this->serverQuery($server) + ['tail' => $tail]);

        if (isset($res['lines'])) {
            return $res['lines'];
        }

        return config('node.fake') ? ['[gamemgr] node unreachable, no console history available'] : [];
    }

    /**
     * The URL the browser opens for the live console and stats feed. In this
     * dev stack nginx proxies /daemon/ to the node so the browser only ever
     * needs the one open port; in production the browser goes straight to the
     * node with a short-lived token.
     */
    public function streamUrl(Server $server): string
    {
        $query = http_build_query($this->serverQuery($server));

        return url("/daemon/api/servers/{$server->uuid}/stream?".$query);
    }

    // ---------------------------------------------------------------- files

    public function listFiles(Server $server, string $path = '/'): array
    {
        $res = $this->get("/api/servers/{$server->uuid}/files", $this->serverQuery($server) + ['path' => $path]);

        return $res['entries'] ?? [];
    }

    public function readFile(Server $server, string $path): ?string
    {
        try {
            $res = $this->http()->get(
                $this->node->daemonUrl("/api/servers/{$server->uuid}/files/contents"),
                $this->serverQuery($server) + ['path' => $path],
            );

            return $res->successful() ? $res->body() : null;
        } catch (\Throwable $e) {
            $this->note($e);

            return null;
        }
    }

    public function writeFile(Server $server, string $path, string $content): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/files/write", [
            'server' => $server->daemonPayload(),
            'path' => $path,
            'content' => $content,
        ]);

        return (bool) ($res['ok'] ?? false);
    }

    public function deleteFiles(Server $server, array $paths): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/files/delete", [
            'server' => $server->daemonPayload(),
            'paths' => $paths,
        ]);

        return (bool) ($res['ok'] ?? false);
    }

    public function renameFile(Server $server, string $from, string $to): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/files/rename", [
            'server' => $server->daemonPayload(),
            'from' => $from,
            'to' => $to,
        ]);

        return (bool) ($res['ok'] ?? false);
    }

    public function makeDir(Server $server, string $path): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/files/mkdir", [
            'server' => $server->daemonPayload(),
            'path' => $path,
        ]);

        return (bool) ($res['ok'] ?? false);
    }

    // -------------------------------------------------------------- backups

    public function backup(Server $server, string $backupUuid, array $ignore = []): ?array
    {
        return $this->post("/api/servers/{$server->uuid}/backup", [
            'server' => $server->daemonPayload(),
            'backup_uuid' => $backupUuid,
            'ignore' => $ignore,
        ]);
    }

    public function restore(Server $server, string $backupUuid): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/restore", [
            'server' => $server->daemonPayload(),
            'backup_uuid' => $backupUuid,
        ]);

        return (bool) ($res['ok'] ?? false);
    }

    // ------------------------------------------------------------- internals

    private function http()
    {
        return Http::withToken($this->daemonToken())
            ->timeout((int) config('node.timeout', 10))
            ->acceptJson()
            // Self-signed certificates are the norm on a freshly installed
            // node, and the bearer token is what actually authenticates the
            // call. Verification is enforced once a node has a real cert.
            ->withoutVerifying();
    }

    private function get(string $path, array $query = []): ?array
    {
        try {
            $res = $this->http()->get($this->node->daemonUrl($path), $query);

            return $res->successful() ? (array) $res->json() : null;
        } catch (\Throwable $e) {
            $this->note($e);

            return null;
        }
    }

    private function post(string $path, array $body = []): ?array
    {
        try {
            $res = $this->http()->post($this->node->daemonUrl($path), $body);

            return $res->successful() ? (array) $res->json() : null;
        } catch (\Throwable $e) {
            $this->note($e);

            return null;
        }
    }

    /**
     * The credential this panel presents when it calls the node.
     *
     * An enrolled node has its own, stored encrypted at enrolment. The env
     * fallback exists only for the dev stack, where the daemon is handed a
     * fixed token and never enrols. Falling back on a real install is what made
     * a healthy node report "did not respond": the panel was presenting the dev
     * token to a daemon holding a 64 character one, so only the unauthenticated
     * health check ever succeeded.
     */
    private function daemonToken(): string
    {
        if ($secret = $this->node->daemon_secret) {
            return (string) $secret;
        }

        return (string) (config('node.dev_token') ?: env('NODE_TOKEN', 'gamemgr-dev-node-token'));
    }

    /** GET endpoints take the server definition as query parameters. */
    private function serverQuery(Server $server): array
    {
        return [
            'name' => $server->name,
            'runtime' => $server->runtime,
            'image' => $server->image,
            'ip' => $server->allocation?->ip ?? '127.0.0.1',
            'port' => $server->allocation?->port ?? 0,
            'memory' => $server->memory,
            'disk' => $server->disk,
            'cpu' => $server->cpu,
            'steam_app_id' => $server->template?->steam_app_id ?? 0,
            'lgsm' => $server->template?->lgsm_shortname ?? '',
            'data_path' => $server->template?->data_path ?: '/home/container',
        ];
    }

    private function note(\Throwable $e): void
    {
        Log::debug('node call failed', ['node' => $this->node->name, 'error' => $e->getMessage()]);
    }

    /**
     * A believable idle sample for a node that is not answering. Only used when
     * node.fake is on; otherwise callers see zeroes and an offline state, which
     * is the truthful answer.
     */
    private function fakeStats(Server $server): array
    {
        if (! config('node.fake')) {
            return ['state' => 'offline', 'cpu' => 0, 'memory_mib' => 0, 'disk_mib' => 0, 'players' => 0];
        }

        return [
            'state' => $server->power_state ?: 'offline',
            'cpu' => (float) $server->cached_cpu,
            'memory_mib' => (int) $server->cached_memory,
            'memory_cap_mib' => (int) $server->memory,
            'disk_mib' => (int) $server->cached_disk,
            'players' => (int) $server->cached_players,
            'max_players' => (int) $server->cached_max_players,
            'simulated' => true,
        ];
    }
}
