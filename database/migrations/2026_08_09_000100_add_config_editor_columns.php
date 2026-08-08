<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-game config editor.
 *
 * templates.config_schema is not the same thing as templates.config_files,
 * which already exists and stays put. config_files is Pterodactyl's boot time
 * substitution: it tells the daemon to stamp the allocation's IP and port into
 * a file before the game starts, and nobody ever sees it. config_schema is the
 * opposite direction: it declares which settings inside those files a customer
 * is allowed to look at and change, and what each one is.
 *
 * It is JSON on the template rather than two more tables because it is one
 * document that belongs to one template: it is exported, imported, versioned
 * and diffed as a unit, exactly like startup and config_files beside it, and
 * splitting it into rows would buy a foreign key nothing ever joins on at the
 * cost of a second admin CRUD screen for editing it.
 *
 * servers.config_dirty_at records that somebody saved config after the server
 * last started. Compared against last_started_at it answers "is what is on
 * screen actually what the game is running", which is the whole reason the
 * restart banner can be honest instead of permanent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('config_schema')->nullable()->after('config_logs');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('config_dirty_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('config_schema');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('config_dirty_at');
        });
    }
};
