<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where this game's cover art was stored, relative to the public disk.
 *
 * Null means there is none, which is an ordinary state rather than a failure:
 * many games have no Steam app id, and a dedicated server's app id frequently
 * has no store page of its own. The listing falls back to a generated tile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('artwork_path')->nullable()->after('cover_color');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('artwork_path');
        });
    }
};
