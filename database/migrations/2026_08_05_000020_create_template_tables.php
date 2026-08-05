<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Games and Templates. This is Pterodactyl's Nest and Egg, renamed to what
// people actually call them: a Game ("Minecraft") holds Templates ("Paper",
// "Forge", "Vanilla"). The Pterodactyl column shapes are preserved verbatim
// where they matter so any community egg JSON imports without translation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('icon')->nullable();        // x-icon name
            $table->string('cover_color', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('author')->nullable();
            $table->text('description')->nullable();

            // The differentiator. Pterodactyl only knows how to run a Docker
            // container; GameMGR templates declare which runtime installs and
            // supervises them.
            $table->string('runtime')->default('docker'); // docker|steamcmd|linuxgsm

            // Docker runtime.
            $table->json('docker_images')->nullable();    // {"label": "image:tag"}
            $table->string('script_container')->default('ghcr.io/gamemgr/installers:debian');
            $table->string('script_entry')->default('bash');
            $table->longText('script_install')->nullable();

            // SteamCMD runtime.
            $table->unsignedInteger('steam_app_id')->nullable();
            $table->boolean('steam_anonymous')->default(true);
            $table->string('steam_branch')->nullable();
            $table->string('steam_beta_password')->nullable();

            // LinuxGSM runtime. The shortname is what lgsm calls the game, for
            // example "mcserver", "rustserver", "vhserver".
            $table->string('lgsm_shortname')->nullable();

            // Shared.
            $table->text('startup')->nullable();
            $table->string('update_command')->nullable();
            $table->json('config_files')->nullable();     // Pterodactyl-compatible
            $table->json('config_startup')->nullable();   // {"done": "...", "strip_ansi": bool}
            $table->json('config_stop')->nullable();
            $table->json('config_logs')->nullable();
            $table->json('features')->nullable();         // eula, java_version, pid_limit, ...
            $table->json('file_denylist')->nullable();
            $table->boolean('force_outgoing_ip')->default(false);

            // Player and query support, which is what makes the Players tab and
            // the status page possible at all.
            $table->boolean('rcon_supported')->default(false);
            $table->string('rcon_protocol')->nullable();  // source|minecraft|battleye
            $table->string('query_protocol')->nullable(); // a2s|minecraft|gamespy
            $table->integer('rcon_port_offset')->default(0);
            $table->integer('query_port_offset')->default(0);

            // Mod ecosystem the Mods tab should search for this template.
            $table->json('mod_sources')->nullable();      // ["modrinth","curseforge",...]

            $table->string('imported_from')->nullable();  // filename or URL of the egg
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'runtime']);
        });

        Schema::create('template_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('env_variable');
            $table->text('default_value')->nullable();
            $table->boolean('user_viewable')->default(true);
            $table->boolean('user_editable')->default(true);
            $table->string('rules')->default('nullable|string');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['template_id', 'env_variable']);
        });

        Schema::create('mount_template', function (Blueprint $table) {
            $table->foreignId('mount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->primary(['mount_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mount_template');
        Schema::dropIfExists('template_variables');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('games');
    }
};
