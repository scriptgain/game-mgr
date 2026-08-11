<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Start a server once its files have finished downloading.
 *
 * Without this, a fifteen gigabyte install finishes into silence and the
 * server sits offline until somebody notices and presses Start. For an
 * unattended install, which is most of them, that is the difference between
 * "ready" and "ready in the morning".
 *
 * Default true, including for rows that already exist. That is the behaviour
 * people expect from every other panel, and a server that starts when it is
 * ready is easier to explain than one that does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('start_on_install')->default(true)->after('auto_update');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('start_on_install');
        });
    }
};
