<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to record an install actually happening.
 *
 * Creating a server set status to "installing" and nothing else ever ran: the
 * panel had no install() at all, so the daemon was never asked, no files were
 * ever downloaded, and the row sat at "installing" forever. With the dispatch
 * in place there is now real progress to keep, and an operator watching an
 * 8 GB SteamCMD download deserves to see where it is rather than a spinner.
 *
 * The log lives on the server row rather than in a table of lines. It is read
 * as a whole or not at all, it is truncated to the tail, and a row per line
 * would be tens of thousands of rows per install for something nobody queries
 * by line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // 0-100 while downloading, null when the runtime cannot report it.
            // LinuxGSM and Docker pulls do not give a percentage the way
            // SteamCMD does, so this is deliberately nullable rather than 0.
            $table->unsignedTinyInteger('install_progress')->nullable()->after('installed_at');
            // Human phase: "Downloading", "Validating", "Pulling image".
            $table->string('install_phase')->nullable()->after('install_progress');
            $table->longText('install_log')->nullable()->after('install_phase');
            $table->timestamp('install_started_at')->nullable()->after('install_log');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['install_progress', 'install_phase', 'install_log', 'install_started_at']);
        });
    }
};
