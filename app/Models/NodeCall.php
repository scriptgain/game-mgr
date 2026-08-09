<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One call parked for a node that cannot be dialled.
 *
 * See the migration for why this is a table. The claim below is the part worth
 * reading twice: two pollers from the same node must never take the same call,
 * and the daemon runs several.
 */
class NodeCall extends Model
{
    protected $fillable = [
        'node_id', 'uuid', 'method', 'path', 'query', 'body',
        'state', 'deadline_at', 'claimed_at',
        'progress', 'response_status', 'response_body', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'deadline_at' => 'datetime',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Take the oldest pending call for a node, or null.
     *
     * The guard is the affected-row count of the UPDATE, not a read followed by
     * a write. Two pollers that read the same row both see it pending; only one
     * of them changes a row from pending to claimed, and the other gets zero
     * back and moves on. Doing this with a SELECT then an UPDATE would hand the
     * same power action to two goroutines, and "stop" twice is survivable while
     * "install" twice is not.
     */
    public static function claimFor(Node $node): ?self
    {
        $candidate = static::where('node_id', $node->id)
            ->where('state', 'pending')
            ->where('deadline_at', '>', now())
            ->orderBy('id')
            ->first();

        if (! $candidate) {
            return null;
        }

        $taken = static::where('id', $candidate->id)
            ->where('state', 'pending')
            ->update(['state' => 'claimed', 'claimed_at' => now(), 'updated_at' => now()]);

        return $taken === 1 ? $candidate->fresh() : null;
    }

    /** Park a call and hand back the row the caller will wait on. */
    public static function park(Node $node, string $method, string $path, array $query, ?string $body, int $timeout): self
    {
        return static::create([
            'node_id' => $node->id,
            'uuid' => (string) Str::uuid(),
            'method' => strtoupper($method),
            'path' => $path,
            'query' => static::stringify($query),
            'body' => $body,
            'state' => 'pending',
            'deadline_at' => now()->addSeconds($timeout),
        ]);
    }

    /**
     * Query values, as a query string would carry them.
     *
     * `serverQuery()` hands over memory, cpu and port as integers, which JSON
     * faithfully preserved as numbers and the daemon then refused to decode
     * into its string map: every call after a Start failed with an unmarshal
     * error and the node looked unreachable. A query parameter is text by
     * definition, so it is stored as text.
     *
     * @param  array<string,mixed>  $query
     * @return array<string,string>
     */
    private static function stringify(array $query): array
    {
        return array_map(
            fn ($value) => match (true) {
                is_bool($value) => $value ? '1' : '0',
                is_array($value) => json_encode($value),
                default => (string) $value,
            },
            $query,
        );
    }

    /**
     * Append streamed lines, atomically.
     *
     * CONCAT in SQL rather than read-modify-write in PHP: the daemon posts
     * batches while the panel is reading them, and a read-modify-write drops
     * whichever batch lost the race. Losing a progress line is not fatal, but
     * it is silent, and a progress bar that skips is exactly the kind of bug
     * nobody can reproduce.
     */
    public function appendProgress(string $lines): void
    {
        // Two dialects, on purpose. SQLite (the test suite) only grew CONCAT()
        // in 3.44, and MySQL reads `||` as logical OR unless PIPES_AS_CONCAT is
        // set, which would quietly turn the whole install log into "0". Neither
        // expression is portable, so pick by driver rather than hope.
        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(progress, '') || ?"
            : "CONCAT(COALESCE(progress, ''), ?)";

        DB::update(
            'update node_calls set progress = '.$expression.', updated_at = ? where id = ?',
            [$lines, now(), $this->id],
        );
    }

    /** Drop what is finished and expire what was never answered. */
    public static function prune(): int
    {
        $keepFor = (int) config('node.reverse.prune_after', 3600);

        return DB::transaction(function () use ($keepFor) {
            $gone = static::where('completed_at', '<', now()->subSeconds($keepFor))->delete();

            // An abandoned claim: the daemon took it and died, or the panel
            // gave up waiting. Either way nobody is coming back for it, and
            // leaving it "claimed" makes the table read like work in progress.
            $gone += static::whereIn('state', ['pending', 'claimed'])
                ->where('deadline_at', '<', now()->subSeconds($keepFor))
                ->delete();

            return $gone;
        });
    }
}
