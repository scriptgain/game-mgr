<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a person asked for this server to be off.
 *
 * Without it the watchdog cannot tell a crash from somebody pressing Stop: both
 * leave power_state offline with status null. An "unexpectedly offline" rule
 * would therefore restart every server its owner deliberately shut down, and
 * the owner would find they could not turn their own server off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('stopped_intentionally')->default(false)->after('power_state');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('stopped_intentionally');
        });
    }
};
