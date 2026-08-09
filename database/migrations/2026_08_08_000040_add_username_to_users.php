<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * SFTP needs a stable, unique name for an account.
 *
 * The client area has always shown an SFTP username built by slugging the
 * display name: Str::slug($user->name).'.'.$server->uuid_short. That is fine to
 * print and useless to log in with, because two people called Alex Smith slug to
 * the same thing and the daemon would have no way to tell which account just
 * presented a password.
 *
 * So accounts get a real username. Derived from the email local part, which is
 * the closest thing to a name people already know they have, and made unique
 * with a numeric suffix when it collides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable first. The column has to exist before it can be filled,
            // and it cannot be unique until it is.
            $table->string('username', 64)->nullable()->after('email');
        });

        $this->backfill();

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Give every existing account a username without tripping over collisions.
     *
     * This deliberately keeps its own copy of the rule rather than calling
     * User::deriveUsername(). A migration has to produce the same result years
     * from now, on a database it has already run against, and a migration that
     * calls into application code breaks the day that code is refactored or
     * deleted. The duplication is the point.
     */
    private function backfill(): void
    {
        $taken = [];

        DB::table('users')->orderBy('id')->select('id', 'email', 'name')->chunkById(200, function ($users) use (&$taken) {
            foreach ($users as $user) {
                $base = Str::of((string) $user->email)->before('@')->lower()
                    ->replaceMatches('/[^a-z0-9._-]+/', '')
                    ->trim('._-')
                    ->limit(48, '')
                    ->value();

                if ($base === '') {
                    $base = Str::slug((string) $user->name) ?: 'user';
                }

                $username = $base;
                $suffix = 1;
                while (isset($taken[$username]) || DB::table('users')->where('username', $username)->exists()) {
                    $suffix++;
                    $username = $base.$suffix;
                }
                $taken[$username] = true;

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            }
        });
    }
};
