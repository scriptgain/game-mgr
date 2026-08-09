<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work parked for a node that cannot be dialled.
 *
 * A direct node is reached by opening a connection to it. A reverse node is
 * behind NAT and there is nothing to open, so the call waits here until the
 * daemon's own long poll picks it up, and the answer comes back the same way.
 *
 * A table rather than a cache entry because this is the only route to a node
 * that has no other route: losing a queued power action to a cache flush or a
 * restarted Redis would lose the only copy. It is also short-lived data, so a
 * scheduled prune keeps it from growing without bound.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();

            // The request, as the daemon will rebuild it against its own mux.
            $table->string('method', 8);
            $table->string('path');
            $table->json('query')->nullable();
            $table->longText('body')->nullable();

            // pending -> claimed -> done. A claim that never comes back is
            // expired by its deadline rather than by a separate state: the
            // panel has stopped waiting either way.
            $table->string('state', 12)->default('pending');
            $table->timestamp('deadline_at');
            $table->timestamp('claimed_at')->nullable();

            // Lines produced before the call finished. Only an SSE endpoint
            // fills this, and only one matters: an install runs for hours and
            // the progress bar is the whole reason anybody keeps the tab open.
            // A single appended column rather than a child table because it is
            // written by one writer, read by one reader, and thrown away with
            // its call.
            $table->longText('progress')->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // The claim query, which runs on every poll of every reverse node,
            // and is the only hot read on this table.
            $table->index(['node_id', 'state', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_calls');
    }
};
