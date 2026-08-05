<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Servers and everything hanging off one: variables, subusers, databases,
// backups, schedules and metric history.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Short id is what appears in URLs and in the SFTP username. Full
            // uuid stays the daemon-side identity.
            $table->string('uuid_short', 8)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('allocation_id')->nullable()->index();

            // Copied off the template at create time so changing the template
            // later cannot silently re-point a live server.
            $table->string('runtime')->default('docker');
            $table->string('image')->nullable();
            $table->text('startup')->nullable();

            // Limits. 0 means unlimited, matching operator expectations.
            $table->unsignedBigInteger('memory')->default(1024); // MiB
            $table->unsignedBigInteger('swap')->default(0);
            $table->unsignedBigInteger('disk')->default(5120);
            $table->unsignedInteger('io')->default(500);
            $table->unsignedInteger('cpu')->default(100);        // percent
            $table->string('threads')->nullable();               // pinned cores, e.g. "0-3"
            $table->boolean('oom_disabled')->default(true);

            // Feature caps exposed to the client area.
            $table->unsignedInteger('database_limit')->default(2);
            $table->unsignedInteger('allocation_limit')->default(2);
            $table->unsignedInteger('backup_limit')->default(5);

            // null once installed and healthy. Anything else disables the
            // client controls and explains itself in the UI.
            $table->string('status')->nullable(); // installing|install_failed|suspended|restoring|transferring
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_crashed_at')->nullable();

            // Cached from the last daemon poll so an index page never has to
            // fan out to every node just to colour a status dot.
            $table->string('power_state')->default('offline'); // offline|starting|running|stopping
            $table->float('cached_cpu')->default(0);
            $table->unsignedBigInteger('cached_memory')->default(0);
            $table->unsignedBigInteger('cached_disk')->default(0);
            $table->unsignedInteger('cached_players')->default(0);
            $table->unsignedInteger('cached_max_players')->default(0);
            $table->timestamp('cached_at')->nullable();

            // Watchdog behaviour, per server.
            $table->boolean('auto_restart')->default(true);
            $table->boolean('auto_update')->default(false);
            $table->timestamps();

            $table->index(['node_id', 'status']);
            $table->index(['owner_id']);
        });

        Schema::create('server_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_variable_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['server_id', 'template_variable_id'], 'server_variable_unique');
        });

        // Permission strings mirror Pterodactyl's namespaces so an operator's
        // existing mental model transfers, plus GameMGR's player.*, mod.* and
        // world.* additions.
        Schema::create('subusers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['server_id', 'user_id']);
        });

        Schema::create('database_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedInteger('port')->default(3306);
            $table->string('username');
            $table->text('password');                 // encrypted cast
            $table->string('linked_ip')->nullable();  // what servers connect to
            $table->foreignId('node_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_databases')->default(0);
            $table->timestamps();
        });

        Schema::create('server_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('database_host_id')->constrained()->cascadeOnDelete();
            $table->string('database');
            $table->string('username');
            $table->text('password');                 // encrypted cast
            $table->string('remote')->default('%');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamps();
            $table->unique(['database_host_id', 'database']);
        });

        // Retention is a policy, not a flat count cap. A backup is kept if any
        // rule still wants it, which is the same shape BackupMGR uses.
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('keep_last')->default(3);
            $table->unsignedInteger('keep_daily')->default(7);
            $table->unsignedInteger('keep_weekly')->default(4);
            $table->unsignedInteger('keep_monthly')->default(6);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retention_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('ignored_files')->nullable();
            $table->string('disk')->default('local');   // local|s3|storagemgr
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->boolean('is_successful')->default(false);
            $table->boolean('is_locked')->default(false); // locked backups ignore retention
            $table->string('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['server_id', 'completed_at']);
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cron_minute')->default('*');
            $table->string('cron_hour')->default('*');
            $table->string('cron_day_of_month')->default('*');
            $table->string('cron_month')->default('*');
            $table->string('cron_day_of_week')->default('*');
            $table->boolean('is_active')->default(true);
            $table->boolean('only_when_online')->default(false);
            $table->boolean('is_processing')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        // Tasks chain: each runs after the previous one plus its offset, so a
        // "warn, wait 5m, warn again, restart" sequence is one schedule.
        Schema::create('schedule_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('action');            // power|command|backup|update|webhook
            $table->text('payload')->nullable();
            $table->unsignedInteger('time_offset')->default(0); // seconds after the previous task
            $table->boolean('continue_on_failure')->default(false);
            $table->boolean('is_queued')->default(false);
            $table->timestamps();
        });

        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sampled_at');
            $table->float('cpu')->default(0);
            $table->unsignedBigInteger('memory')->default(0);  // MiB
            $table->unsignedBigInteger('disk')->default(0);    // MiB
            $table->unsignedBigInteger('net_rx')->default(0);  // bytes/s
            $table->unsignedBigInteger('net_tx')->default(0);
            $table->unsignedInteger('players')->default(0);
            $table->float('tick_rate')->nullable();            // TPS or server FPS
            $table->index(['server_id', 'sampled_at']);
        });

        // A saved server configuration, cloneable in one click. Pterodactyl
        // makes you rebuild every limit and variable by hand each time.
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->json('limits');
            $table->json('feature_limits');
            $table->json('environment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprints');
        Schema::dropIfExists('server_metrics');
        Schema::dropIfExists('schedule_tasks');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('server_databases');
        Schema::dropIfExists('database_hosts');
        Schema::dropIfExists('subusers');
        Schema::dropIfExists('server_variables');
        Schema::dropIfExists('servers');
    }
};
