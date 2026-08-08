<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An allocation was an IP and a port and nothing else, so nothing downstream
 * could tell 25575 apart from 25565: not the panel, not the person reading the
 * Network tab, and not the daemon that has to decide which of them to open on
 * the firewall and over which protocol.
 *
 * protocol is tcp, udp or both. `both` stays the default because that is what
 * every allocation created before this migration effectively was, and because
 * the Docker driver publishes a port on both unless told otherwise.
 *
 * role is a comma separated list, not a single value, because several roles
 * genuinely land on one port: CS2 takes its game traffic, its A2S queries and
 * its RCON all on 27015, and that is one allocation carrying "game,query,rcon"
 * over both protocols rather than three rows fighting over the unique index on
 * (node_id, ip, port).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->string('protocol', 8)->default('both')->after('port');
            $table->string('role', 64)->nullable()->after('protocol');
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropColumn(['protocol', 'role']);
        });
    }
};
