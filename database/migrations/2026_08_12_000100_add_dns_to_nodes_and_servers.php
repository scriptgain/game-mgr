<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connection names, phase 1.
 *
 * One wildcard record per node covers every server on it, so the only DNS
 * identity a node needs is its label. The two columns on servers are
 * denormalised on purpose: rendering an address must be a column read, never a
 * DNS lookup and never a string rebuilt out of three relations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            // lax1, phx1. The middle label of every name on this node.
            $table->string('dns_label', 63)->nullable()->unique()->after('fqdn');
            // Whether the wildcard record is really there: disabled, unlabelled,
            // no_ip, active, drift or failed. Nullable means never checked.
            $table->string('wildcard_status', 24)->nullable()->after('dns_label');
            $table->string('wildcard_error', 500)->nullable()->after('wildcard_status');
            $table->timestamp('wildcard_checked_at')->nullable()->after('wildcard_error');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->string('dns_label', 63)->nullable()->after('name');
            $table->string('connect_name', 255)->nullable()->after('dns_label');
            // Uniqueness is per node, because that is the scope the wildcard
            // answers in: alpha.lax1 and alpha.fra1 are different names.
            $table->index(['node_id', 'dns_label']);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['node_id', 'dns_label']);
            $table->dropColumn(['dns_label', 'connect_name']);
        });

        Schema::table('nodes', function (Blueprint $table) {
            $table->dropUnique(['dns_label']);
            $table->dropColumn(['dns_label', 'wildcard_status', 'wildcard_error', 'wildcard_checked_at']);
        });
    }
};
