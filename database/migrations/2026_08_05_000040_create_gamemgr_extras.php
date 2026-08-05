<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The parts Pterodactyl has no answer for: players, mods, worlds, a watchdog,
// alerting, public status pages and outbound webhooks.
return new class extends Migration
{
    public function up(): void
    {
        // Players seen on a server, keyed by whatever identity the game uses
        // (Minecraft UUID, SteamID64, EOS id).
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('identifier');           // uuid or steamid
            $table->string('name');
            $table->string('ip', 45)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('playtime_seconds')->default(0);
            $table->boolean('is_online')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->boolean('is_op')->default(false);
            $table->boolean('is_whitelisted')->default(false);
            $table->string('ban_reason')->nullable();
            $table->timestamps();
            $table->unique(['server_id', 'identifier']);
            $table->index(['server_id', 'is_online']);
        });

        Schema::create('player_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event');                // join|leave|kick|ban|unban|chat|death
            $table->text('detail')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['server_id', 'occurred_at']);
        });

        // Installed mods and plugins, with enough provenance to offer updates.
        Schema::create('mods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('source');               // modrinth|curseforge|spigot|workshop|manual
            $table->string('remote_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('author')->nullable();
            $table->text('summary')->nullable();
            $table->string('version')->nullable();
            $table->string('latest_version')->nullable();
            $table->string('path')->nullable();     // where it landed on disk
            $table->unsignedBigInteger('bytes')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index(['server_id', 'source']);
        });

        // Worlds and saves. Swapping the active world is a first-class action
        // rather than something you do by hand in the file manager.
        Schema::create('worlds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('seed')->nullable();
            $table->string('level_type')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();
            $table->index(['server_id', 'is_active']);
        });

        // Watchdog. server_id null means the rule applies fleet-wide.
        Schema::create('watchdog_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger');              // crash|offline|log_pattern|memory|players_zero|tick_rate
            $table->string('pattern')->nullable();  // regex for log_pattern
            $table->unsignedInteger('threshold')->default(0);
            $table->unsignedInteger('grace_seconds')->default(60);
            $table->string('action')->default('alert'); // alert|restart|stop|reinstall
            $table->json('channels')->nullable();   // notification_channel ids
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');                 // discord|slack|webhook|email
            $table->text('target');                 // webhook url or address
            $table->json('events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('watchdog_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity')->default('warning'); // info|warning|critical
            $table->string('title');
            $table->text('detail')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['severity', 'acknowledged_at']);
        });

        // Opt-in public page per server: up or down, player count, next restart.
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('show_players')->default(true);
            $table->boolean('show_address')->default(true);
            $table->boolean('show_uptime')->default(true);
            $table->boolean('show_version')->default(true);
            $table->timestamps();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('secret', 64)->nullable();
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('status_pages');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('watchdog_rules');
        Schema::dropIfExists('worlds');
        Schema::dropIfExists('mods');
        Schema::dropIfExists('player_events');
        Schema::dropIfExists('players');
    }
};
