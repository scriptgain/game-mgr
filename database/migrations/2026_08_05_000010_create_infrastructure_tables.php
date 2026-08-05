<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One panel, nodes anywhere. A node is any Linux box running the GameMGR
// daemon: a VPS, a dedicated server, a Proxmox VM or a spare machine at home.
// Locations group them so "EU" or "home lab" is a first-class filter.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('short')->unique();   // e.g. "eu-fra"
            $table->string('name');              // e.g. "Frankfurt"
            $table->string('description')->nullable();
            $table->string('flag', 8)->nullable(); // emoji flag, purely cosmetic
            $table->timestamps();
        });

        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // Transport. direct  = the panel dials the daemon (needs an open port).
            //            reverse = the daemon holds an outbound websocket to the
            //                      panel and work is pushed down it, which is what
            //                      makes a NAT'd home box usable at all.
            $table->string('connection_mode')->default('direct');
            $table->string('scheme')->default('https');
            $table->string('fqdn')->nullable();
            $table->unsignedInteger('daemon_port')->default(8942);
            $table->unsignedInteger('sftp_port')->default(2022);
            $table->boolean('behind_proxy')->default(false);

            // Credentials. daemon_token is stored hashed; enrol_token is a
            // short-lived single-use secret that only buys the daemon its
            // long-lived credential.
            $table->string('daemon_token_id', 32)->nullable()->index();
            $table->string('daemon_token', 64)->nullable();
            $table->string('enrol_token', 64)->nullable()->index();
            $table->timestamp('enrol_token_expires_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();

            // Declared capacity, set by the admin. Over-allocation lets a node
            // sell more than it has, which is normal for game hosting because
            // servers rarely peak together. -1 means unlimited.
            $table->unsignedBigInteger('memory')->default(0);   // MiB
            $table->integer('memory_overallocate')->default(0); // percent
            $table->unsignedBigInteger('disk')->default(0);     // MiB
            $table->integer('disk_overallocate')->default(0);
            $table->unsignedInteger('cpu')->default(0);         // percent, 100 = 1 core
            $table->integer('cpu_overallocate')->default(0);
            $table->unsignedInteger('upload_size')->default(256); // MiB, file manager cap

            // What the node can actually run. The server create form filters
            // templates against this, so you cannot place a SteamCMD template
            // on a Docker-only box.
            $table->json('runtimes')->nullable();

            $table->boolean('public')->default(true);          // available for auto placement
            $table->boolean('maintenance_mode')->default(false);
            $table->string('daemon_base')->default('/var/lib/gamemgr/volumes');

            // Self-reported inventory, filled in by the daemon at enrolment and
            // refreshed on every heartbeat. Never trusted for limits, only shown.
            $table->string('reported_os')->nullable();
            $table->string('reported_kernel')->nullable();
            $table->string('reported_arch')->nullable();
            $table->string('reported_docker')->nullable();
            $table->string('reported_agent_version')->nullable();
            $table->unsignedInteger('reported_cpu_cores')->nullable();
            $table->unsignedBigInteger('reported_memory')->nullable();
            $table->unsignedBigInteger('reported_disk')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
            $table->index(['location_id', 'public']);
        });

        // An allocation is an IP and port pair a server can bind to. Ports are
        // the scarce resource on a game node, so they are modelled explicitly
        // rather than being invented at start time.
        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->string('ip_alias')->nullable(); // what the player connects to
            $table->unsignedInteger('port');
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['node_id', 'ip', 'port']);
        });

        Schema::create('node_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sampled_at');
            $table->float('cpu')->default(0);            // percent
            $table->unsignedBigInteger('memory')->default(0); // MiB used
            $table->unsignedBigInteger('disk')->default(0);   // MiB used
            $table->float('load')->default(0);
            $table->unsignedInteger('server_count')->default(0);
            $table->unsignedInteger('running_count')->default(0);
            $table->index(['node_id', 'sampled_at']);
        });

        // Host path passed into a server's container or chroot. Allowlisted by
        // node and by template so a client cannot mount whatever they like.
        Schema::create('mounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('source');   // path on the node
            $table->string('target');   // path inside the server
            $table->boolean('read_only')->default(true);
            $table->boolean('user_mountable')->default(false);
            $table->timestamps();
        });

        Schema::create('mount_node', function (Blueprint $table) {
            $table->foreignId('mount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->primary(['mount_id', 'node_id']);
        });

        Schema::create('mount_server', function (Blueprint $table) {
            $table->foreignId('mount_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('server_id');
            $table->primary(['mount_id', 'server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mount_server');
        Schema::dropIfExists('mount_node');
        Schema::dropIfExists('mounts');
        Schema::dropIfExists('node_metrics');
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('nodes');
        Schema::dropIfExists('locations');
    }
};
