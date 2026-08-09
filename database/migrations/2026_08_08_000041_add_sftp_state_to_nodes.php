<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the panel knows about a node's file access.
 *
 * Both columns are reported by the daemon rather than configured here, because
 * both are facts about the node rather than wishes about it. A panel that let
 * an admin tick "SFTP enabled" would happily show a customer a host and a
 * username for a port with nothing behind it.
 *
 * The fingerprint is the node's SSH host key. Showing it is the difference
 * between a customer verifying the warning their client gives them on first
 * connection and being trained to click through it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('sftp_enabled')->default(false)->after('sftp_port');
            $table->string('sftp_fingerprint')->nullable()->after('sftp_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['sftp_enabled', 'sftp_fingerprint']);
        });
    }
};
