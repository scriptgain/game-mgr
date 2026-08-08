<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The panel has to be able to CALL a node, not just recognise one calling it.
 *
 * Enrollment stored only sha256 of the daemon credential, which is right for
 * authenticating the daemon inbound but leaves the panel with nothing it can
 * present outbound. NodeClient papered over that by sending a dev token, so on
 * a real install every authenticated call to a node was rejected and the panel
 * reported the daemon as not responding while it was perfectly healthy.
 *
 * Two columns rather than one, deliberately. The hash stays because inbound
 * auth is an indexed exact-match lookup and Laravel's encryption is not
 * deterministic, so an encrypted column cannot be queried by value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->text('daemon_secret')->nullable()->after('daemon_token');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('daemon_secret');
        });
    }
};
