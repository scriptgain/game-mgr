<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Steam account the panel can install paid games with.
 *
 * Every template until now downloaded anonymously, which covers most dedicated
 * servers and none of the ones people actually ask for: ARK: Survival Evolved,
 * Squad, Insurgency and Deadlock all require an account that owns the game.
 * The driver could already take STEAM_USER and STEAM_PASS as ordinary template
 * variables, but that put a real password in `server_variables` in plain text,
 * once per server, and still could not answer a Steam Guard prompt.
 *
 * So the credentials live here instead, encrypted, entered once, and bound to
 * as many servers as need them. The shared secret never leaves the panel: the
 * daemon is sent a generated five character code that expires in thirty
 * seconds, not the seed that produces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steam_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('username');

            // Both encrypted at rest by the model's casts, the same as
            // database_hosts.password and nodes.daemon_secret.
            $table->text('password');

            // The mobile authenticator's shared_secret, Base64. Nullable
            // because an account with Steam Guard disabled, or one whose sentry
            // is already established on every node it is used from, does not
            // need one and should not be forced to hand one over.
            $table->text('shared_secret')->nullable();

            // Node ids where a login has succeeded, so steamcmd has written its
            // sentry file and later installs need no code at all. Advisory:
            // it is what the UI reads to say which nodes are ready, and it is
            // never trusted in place of actually attempting the login.
            $table->json('authorized_nodes')->nullable();

            $table->timestamps();
        });

        Schema::table('servers', function (Blueprint $table) {
            // nullOnDelete rather than cascade: deleting a credential must
            // never delete somebody's game server. The install simply stops
            // working, loudly, which is the correct failure.
            $table->foreignId('steam_account_id')->nullable()->after('template_id')
                ->constrained('steam_accounts')->nullOnDelete();
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->boolean('requires_steam_account')->default(false)->after('steam_anonymous');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('requires_steam_account');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('steam_account_id');
        });

        Schema::dropIfExists('steam_accounts');
    }
};
