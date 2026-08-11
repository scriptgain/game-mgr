<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an install is sitting at a Steam Guard prompt, waiting for a person.
 *
 * A column rather than a cache entry because the person answering usually is
 * not the person watching the stream: the install runs in a queue worker, the
 * event that opened the prompt is not replayed, and somebody who reloads the
 * page has to be able to see that it is still waiting. Losing that to a cache
 * flush would leave an install blocked with nothing on screen explaining why.
 *
 * Null means not waiting. The timestamp is used to age the prompt out in the
 * UI, because the node gives up after ten minutes and a box that stays on
 * screen after that would take a code nothing is listening for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('guard_prompt_at')->nullable()->after('install_phase');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('guard_prompt_at');
        });
    }
};
