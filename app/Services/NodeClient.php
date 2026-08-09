<?php

namespace App\Services;

use App\Models\Node;
use App\Models\Server;
use App\Services\Node\DirectTransport;
use App\Services\Node\Transport;
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
 *
 * HOW IT REACHES THE NODE is not this class's business. A direct node is
 * dialled and a reverse node behind NAT has the work parked for its own poll to
 * collect, and both come back through App\Services\Node\Transport answering the
 * same way: a response, or null for "did not answer". Every method here was
 * written against that convention before reverse mode existed, which is why
 * adding it changed almost nothing below.
 */
class NodeClient
{
    public function __construct(private Node $node) {}

    private ?Transport $transport = null;

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

    /**
     * Is the daemon answering right now? Costs one short request.
     *
     * Only ever asked of a direct node: `nodes:poll` judges a reverse node on
     * how recently it called in, because dialling one is the thing that cannot
     * be done. Answered here anyway, over whichever transport it has, so a
     * caller that asks about a reverse node gets the truth rather than a false
     * "offline".
     */
    public function ping(): bool
    {
        return (bool) $this->transport()->send('GET', '/healthz', timeout: 3)?->ok();
    }

    // --------------------------------------------------------------- server

    public function power(Server $server, string $action): array
    {
        // Longer than the shared timeout on purpose: stopping waits for the
        // game to save. Reporting a node as unreachable because it was busy
        // doing what we asked is worse than waiting for it.
        $res = $this->post("/api/servers/{$server->uuid}/power", [
            'server' => $server->daemonPayload(),
            'action' => $action,
        ], (int) config('node.power_timeout', 90));

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
    public function streamUrl(Server $server): ?string
    {
        // Null for a reverse node. There is no socket to hold open to a box
        // behind somebody's router, so the console falls back to polling the
        // panel, which reaches the node over the tunnel like everything else.
        // The console already had that fallback for a node whose SSE never
        // opened; this just tells it not to try first.
        if (! $this->transport() instanceof DirectTransport) {
            return null;
        }

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
        // Not get(): a file's contents are the body, and decoding them as JSON
        // would turn every config file that happens to start with a brace into
        // an array and everything else into null.
        $res = $this->transport()->send(
            'GET',
            "/api/servers/{$server->uuid}/files/contents",
            $this->serverQuery($server) + ['path' => $path],
        );

        return $res?->ok() ? $res->body : null;
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

    /**
     * Stream a file to the node.
     *
     * $body is an open stream resource, not a string, and that is the whole
     * point. writeFile sends its content as a JSON string field, so a 200 MiB
     * modpack through that path would be the file in memory here, base64 in
     * memory again while json_encode runs, and a third copy on the daemon as it
     * decodes: three copies of a file that is going straight to disk. This hands
     * Guzzle the handle and the bytes go from the temporary upload file to the
     * socket without the panel ever holding them.
     *
     * Returns the daemon's reply, or an ['error' => ...] the caller can show.
     * Never throws: the file manager stays usable when a node is down.
     */
    public function upload(Server $server, string $path, $body, int $maxBytes, ?int $bytes = null): array
    {
        $res = $this->transport()->sendRaw(
            'POST',
            "/api/servers/{$server->uuid}/files/upload",
            $this->serverQuery($server) + ['path' => $path, 'max_bytes' => $maxBytes],
            $body,
            $bytes,
        );

        if ($res === null) {
            return ['ok' => false, 'error' => 'Lost contact with the node during the upload.'];
        }

        if ($res->ok()) {
            return (array) $res->json();
        }

        // The daemon's own wording, which is more specific than anything that
        // could be invented here, and 413 in particular is a message the person
        // uploading can act on. A reverse node's size refusal arrives the same
        // way, so there is one path for "too big" rather than two.
        return ['ok' => false, 'error' => (string) (($res->json()['error'] ?? null)
            ?: 'The node refused the upload (HTTP '.$res->status.').')];
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

    /**
     * Open a stream on a backup held by the node.
     *
     * Returned as a stream, not a string. These are routinely tens of
     * gigabytes and reading one into a variable is how a panel runs out of
     * memory serving a file it never needed to hold.
     */
    public function downloadBackup(Server $server, string $backupUuid)
    {
        $transport = $this->transport();

        // A reverse node has no way to hand over forty gigabytes: the only
        // channel to it is a parked row. Null here is what the caller already
        // renders as "the node could not provide this backup", and the file
        // manager's message names the reason.
        if (! $transport instanceof DirectTransport) {
            return null;
        }

        return $transport->stream("/api/servers/{$server->uuid}/backups/{$backupUuid}");
    }

    /**
     * Tell this node to pull a backup from another one.
     *
     * The step that makes a migration work. Backup writes the archive on the
     * node it ran on and Restore reads it from the node it runs on, so without
     * this the target looks for a file that was never sent. Node to node,
     * because the alternative is tens of gigabytes through a PHP worker.
     */
    public function fetchBackup(Server $server, string $url, string $backupUuid): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/backups/fetch", [
            'server' => $server->daemonPayload(),
            'url' => $url,
            'backup_uuid' => $backupUuid,
        ], 7200);

        return (bool) ($res['ok'] ?? false);
    }

    /**
     * Have the node fetch a Steam Workshop item with steamcmd.
     *
     * Long timeout on purpose: a Workshop item can be a texture pack or it can
     * be several gigabytes, and Steam is not quick about either.
     *
     * @return array{ok:bool,path?:string,error?:string}
     */
    public function workshopInstall(Server $server, int $appId, int $itemId): array
    {
        $res = $this->post("/api/servers/{$server->uuid}/workshop/install", [
            'server' => $server->daemonPayload(),
            'app_id' => $appId,
            'item_id' => $itemId,
        ], 3600);

        return is_array($res) ? $res + ['ok' => (bool) ($res['ok'] ?? false)] : ['ok' => false];
    }

    /** Compress paths into one archive inside the server's own directory. */
    public function archive(Server $server, array $paths, string $target): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/files/archive", [
            'server' => $server->daemonPayload(),
            'paths' => array_values($paths),
            'target' => $target,
        ], 600);

        return (bool) ($res['ok'] ?? false);
    }

    /**
     * Unpack an archive where it sits.
     *
     * The node refuses any entry that would land outside the server's own
     * directory, because an archive is a list of paths chosen by whoever built
     * it and "../../etc/cron.d/x" is a legal name inside one.
     */
    public function extract(Server $server, string $path): bool
    {
        $res = $this->post("/api/servers/{$server->uuid}/files/extract", [
            'server' => $server->daemonPayload(),
            'path' => $path,
        ], 600);

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

    /**
     * Run the install on the node, streaming its output back line by line.
     *
     * This is the call that did not exist. Creating a server set status to
     * "installing" and stopped there, so the daemon was never told to fetch
     * anything and a server could sit at "installing" indefinitely with no
     * files, no error and nothing in the node's log. The endpoint was there the
     * whole time; nothing called it.
     *
     * The response is Server Sent Events for the lifetime of the install, which
     * for a large SteamCMD app is minutes to hours. It therefore gets its own
     * client with no read timeout rather than the ten second one every other
     * call uses, and it must only ever be run from a queued job.
     *
     * $onLine receives (event, data) per event. Returns false if the daemon
     * refused the request or the stream ended in an error event.
     */
    /**
     * Install or reinstall, streaming the node's output back line by line.
     *
     * $wipe empties the data directory first. The node moves the old contents
     * aside rather than deleting them and only drops them once the reinstall
     * has succeeded, so a wipe that fails leaves the server as it was.
     */
    /**
     * Remove a server from the node entirely: container and data directory.
     *
     * Nothing called this before it existed, which meant deleting a server in
     * the panel removed the row, freed the allocations, and left the container
     * and every byte of its files on the node forever. On a panel driven by
     * billing that is a disk leak on a monthly schedule.
     *
     * Returns false rather than throwing when the node cannot be reached. The
     * row still has to be deletable when a node is down, or a dead node means
     * customers cannot be terminated; the caller reports what happened.
     */
    public function destroy(Server $server): bool
    {
        return (bool) $this->transport()->send(
            'DELETE',
            "/api/servers/{$server->uuid}",
            body: ['server' => $server->daemonPayload()],
            timeout: 60,
        )?->ok();
    }

    /**
     * Run an install and report every line of it as it happens.
     *
     * Two shapes, because an install is the one call that streams for hours.
     * A direct node holds an SSE connection open. A reverse node cannot, so the
     * daemon posts its output back in batches against the parked call and this
     * reads them as they land: the same callback, the same progress bar, a
     * second or two behind.
     */
    public function install(Server $server, callable $onLine, int $maxSeconds = 21600, bool $wipe = false): bool
    {
        $transport = $this->transport();

        return $transport instanceof DirectTransport
            ? $this->installDirect($transport, $server, $onLine, $maxSeconds, $wipe)
            : $this->installRelayed($server, $onLine, $maxSeconds, $wipe);
    }

    /** The streaming install, unchanged: hold the SSE open and read lines. */
    private function installDirect(DirectTransport $transport, Server $server, callable $onLine, int $maxSeconds, bool $wipe): bool
    {
        try {
            $response = Http::withToken($this->daemonToken())
                ->withoutVerifying()
                ->withOptions([
                    'stream' => true,
                    // Connect timeout only. A read timeout here would kill the
                    // install at the first quiet moment, and SteamCMD is quiet
                    // for long stretches while it verifies.
                    'connect_timeout' => 10,
                    'read_timeout' => $maxSeconds,
                    'timeout' => $maxSeconds,
                ])
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->post($this->node->daemonUrl("/api/servers/{$server->uuid}/install"), [
                    'server' => $server->daemonPayload(),
                    'wipe' => $wipe,
                ]);

            if (! $response->successful()) {
                $onLine('error', 'The node refused the install: HTTP '.$response->status());

                return false;
            }

            $body = $response->toPsrResponse()->getBody();
            $event = 'message';
            $failed = false;
            $deadline = time() + $maxSeconds;

            while (! $body->eof()) {
                if (time() > $deadline) {
                    $onLine('error', 'The install ran past '.$maxSeconds.' seconds and was abandoned.');

                    return false;
                }

                // readLine, not read(8192). fread on this stream waits to fill
                // the buffer, so a whole install's output arrived in one lump
                // at the end instead of line by line, which for a progress
                // stream is the same as no stream at all. readLine reads to the
                // next newline and returns.
                $line = \GuzzleHttp\Psr7\Utils::readLine($body);
                if ($line === '') {
                    continue;
                }

                $line = rtrim($line, "\r\n");

                if ($line === '') {
                    // Blank line ends an event; the next one starts fresh.
                    $event = 'message';
                    continue;
                }
                if (str_starts_with($line, 'event: ')) {
                    $event = substr($line, 7);
                    continue;
                }
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);
                    if ($event === 'error') {
                        $failed = true;
                    }
                    $onLine($event, $data);
                }
            }

            return ! $failed;
        } catch (\Throwable $e) {
            $onLine('error', 'Lost contact with the node: '.$e->getMessage());

            return false;
        }
    }

    /**
     * The reverse case: park the install, then follow the progress it appends.
     *
     * Polled once a second rather than the transport's 100ms. An install is
     * minutes to hours and its output is already batched by the daemon, so a
     * tighter loop would be thousands of extra queries to learn nothing.
     */
    private function installRelayed(Server $server, callable $onLine, int $maxSeconds, bool $wipe): bool
    {
        $call = \App\Models\NodeCall::park(
            $this->node,
            'POST',
            "/api/servers/{$server->uuid}/install",
            [],
            json_encode(['server' => $server->daemonPayload(), 'wipe' => $wipe]),
            $maxSeconds,
        );

        $read = 0;
        $failed = false;
        $deadline = time() + $maxSeconds;

        while (time() < $deadline) {
            $row = \App\Models\NodeCall::select(['state', 'progress', 'response_status'])->find($call->id);

            if (! $row) {
                $onLine('error', 'The install record went away while it was running.');

                return false;
            }

            $progress = (string) $row->progress;
            if (strlen($progress) > $read) {
                // Only whole lines. A batch can land mid-line, and half a
                // SteamCMD progress line parsed as a percentage is a progress
                // bar that jumps backwards.
                $fresh = substr($progress, $read);
                $lastBreak = strrpos($fresh, "\n");
                if ($lastBreak !== false) {
                    $read += $lastBreak + 1;
                    foreach (explode("\n", substr($fresh, 0, $lastBreak)) as $line) {
                        if ($line === '') {
                            continue;
                        }
                        [$event, $data] = array_pad(explode("\t", $line, 2), 2, '');
                        // Same rule as the streaming path: an install that
                        // emits an error event failed, whatever status the SSE
                        // response itself carried. Without this a reverse
                        // install that died would be recorded as successful,
                        // because SSE answers 200 before it knows.
                        if ($event === 'error') {
                            $failed = true;
                        }
                        $onLine($event ?: 'message', $data);
                    }
                }
            }

            if ($row->state === 'done') {
                return ! $failed
                    && (int) $row->response_status >= 200
                    && (int) $row->response_status < 300;
            }

            sleep(1);
        }

        $onLine('error', 'The install ran past '.$maxSeconds.' seconds and was abandoned.');

        return false;
    }

    // ------------------------------------------------------------- internals

    private function transport(): Transport
    {
        return $this->transport ??= Transport::for($this->node);
    }

    private function get(string $path, array $query = []): ?array
    {
        $res = $this->transport()->send('GET', $path, $query);

        return $res?->ok() ? (array) $res->json() : null;
    }

    private function post(string $path, array $body = [], ?int $timeout = null): ?array
    {
        $res = $this->transport()->send('POST', $path, body: $body, timeout: $timeout);

        return $res?->ok() ? (array) $res->json() : null;
    }

    /**
     * The credential this panel presents when it calls the node.
     *
     * An enrolled node has its own, stored encrypted at enrollment. The env
     * fallback exists only for the dev stack, where the daemon is handed a
     * fixed token and never enrolls. Falling back on a real install is what made
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
            // `unreachable` matters to the caller: nothing here was measured, so
            // a console that overwrites its state with this would be claiming
            // the server is off when the truth is that nobody asked it.
            return ['state' => 'offline', 'cpu' => 0, 'memory_mib' => 0, 'disk_mib' => 0, 'players' => 0, 'unreachable' => true];
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
