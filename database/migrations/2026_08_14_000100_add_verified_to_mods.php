<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Was this file checked against a hash its author published, or not?
 *
 * Modrinth, Hangar and CurseForge all publish a checksum for every file, and
 * the installer refuses a download that does not match one. Spiget publishes
 * none at all, so an install from SpigotMC is exactly as trustworthy as the
 * connection that carried it.
 *
 * Showing both the same way would be telling somebody a check happened when it
 * did not, so the answer is recorded per file rather than inferred per source:
 * a source could start publishing hashes, or stop, and the rows already
 * installed should still say what was true when they were fetched.
 *
 * Defaults to true because every row that exists when this runs came from
 * Modrinth, which has always been verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->boolean('verified')->default(true)->after('bytes');
        });
    }

    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->dropColumn('verified');
        });
    }
};
