<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\NodeCall;
use Illuminate\Http\Request;

/**
 * The node's half of reverse mode.
 *
 * A node behind NAT cannot be dialled, so it comes here and asks whether the
 * panel wants anything. Three endpoints, all authenticated by the daemon's own
 * long-lived token through the agent.auth middleware, and every one of them
 * scoped to the calling node: a daemon can only ever see, answer or report on
 * work addressed to itself.
 *
 * There is deliberately no way for a node to enumerate calls, retry one it
 * already answered, or reach another node's work. A node's credential is the
 * thing most likely to be sitting on somebody's home NUC, so it buys the least
 * possible authority.
 */
class NodeCallController extends Controller
{
    /**
     * Take the next call, waiting for one if the queue is empty.
     *
     * The wait is what makes reverse mode feel immediate rather than polled: a
     * daemon that is already parked here picks a call up within a fraction of a
     * second of it being written, so a Start button behaves the way it does on
     * a directly reachable node.
     *
     * It costs one PHP-FPM worker per reverse node for the duration. That is
     * the whole price of the feature and it is written down in config/node.php
     * and in the docs rather than left to be discovered under load.
     */
    public function next(Request $request)
    {
        $node = $request->attributes->get('agent_node');

        $hold = min(
            (int) $request->integer('wait', (int) config('node.reverse.poll_hold', 25)),
            (int) config('node.reverse.poll_hold', 25),
        );
        $deadline = microtime(true) + max($hold, 0);
        $interval = (int) config('node.reverse.poll_interval_ms', 100) * 1000;

        do {
            if ($call = NodeCall::claimFor($node)) {
                // A node that is here IS alive, so this doubles as the
                // heartbeat. Without it a busy reverse node could be flipped to
                // offline by nodes:poll between its 30-second heartbeats.
                $this->seen($node);

                return response()->json(['call' => $this->payload($call)]);
            }

            usleep($interval);
        } while (microtime(true) < $deadline);

        $this->seen($node);

        return response()->json(null, 204);
    }

    /**
     * Lines produced while a call is still running.
     *
     * Only an install uses this. Everything else answers once, and a call that
     * has already finished refuses further output rather than appending to a
     * log nobody will read again.
     */
    public function progress(Request $request, string $uuid)
    {
        $call = $this->find($request, $uuid);

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.event' => ['nullable', 'string', 'max:32'],
            'lines.*.data' => ['nullable', 'string'],
        ]);

        if ($call->state === 'done') {
            return response()->json(['message' => 'That call has already been answered.'], 409);
        }

        $lines = '';
        foreach ($data['lines'] as $line) {
            // Tab-separated, because the reader splits on the first tab and a
            // SteamCMD progress line is full of everything else.
            $lines .= ($line['event'] ?? 'message')."\t".str_replace(["\t", "\n"], ' ', (string) ($line['data'] ?? ''))."\n";
        }

        $call->appendProgress($lines);

        return response()->json(['ok' => true]);
    }

    /** The answer. */
    public function result(Request $request, string $uuid)
    {
        $call = $this->find($request, $uuid);

        $data = $request->validate([
            'status' => ['required', 'integer', 'min:100', 'max:599'],
            'body' => ['nullable', 'string'],
            'encoding' => ['nullable', 'in:base64'],
        ]);

        if ($call->state === 'done') {
            return response()->json(['message' => 'That call has already been answered.'], 409);
        }

        $body = (string) ($data['body'] ?? '');
        if (($data['encoding'] ?? null) === 'base64') {
            $body = (string) base64_decode($body, true);
        }

        $call->forceFill([
            'state' => 'done',
            'response_status' => $data['status'],
            'response_body' => $body,
            'completed_at' => now(),
        ])->save();

        $this->seen($request->attributes->get('agent_node'));

        return response()->json(['ok' => true]);
    }

    /**
     * The call as the daemon needs it: enough to rebuild the request and
     * nothing about the panel's own bookkeeping.
     */
    private function payload(NodeCall $call): array
    {
        $query = $call->query ?? [];
        $encoding = $query['__encoding'] ?? null;
        unset($query['__encoding']);

        return [
            'uuid' => $call->uuid,
            'method' => $call->method,
            'path' => $call->path,
            'query' => (object) $query,
            'body' => $call->body,
            'encoding' => $encoding,
            // Seconds left, so a daemon does not spend an hour on work the
            // panel stopped waiting for fifty-nine minutes ago.
            'expires_in' => max(0, (int) now()->diffInSeconds($call->deadline_at, false)),
        ];
    }

    /** A call belonging to the calling node, or a 404 that says nothing more. */
    private function find(Request $request, string $uuid): NodeCall
    {
        $node = $request->attributes->get('agent_node');

        // Scoped to the node, so "wrong node" and "no such call" are the same
        // answer. One node must not be able to probe for another's work.
        return NodeCall::where('node_id', $node->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function seen(Node $node): void
    {
        $node->forceFill(['last_seen_at' => now()])->save();
    }
}
