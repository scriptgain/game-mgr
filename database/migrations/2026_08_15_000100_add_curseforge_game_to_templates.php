<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which game CurseForge should be searched for.
 *
 * The client assumed Minecraft, because Minecraft was the only thing that used
 * it. CurseForge carries other games, and the ARK template has declared it as a
 * mod source since the catalogue was written, so an ARK owner searching for a
 * structures mod was shown Minecraft mods with no hint that anything was wrong.
 *
 * A column rather than a lookup keyed on the game's name: the catalogue is the
 * place templates are described, and matching "ARK: Survival Ascended" as a
 * string in a service is the kind of thing that breaks the day somebody renames
 * a game.
 *
 * Null means this template does not use CurseForge, which is most of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedInteger('curseforge_game_id')->nullable()->after('steam_app_id');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('curseforge_game_id');
        });
    }
};
