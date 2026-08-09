<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns, for two jobs.
 *
 * games.category is what the catalogue is organised by once it stops being six
 * games and starts being sixty, including the ones that are not games at all:
 * a voice server has no world, no players in the usual sense and no game ports,
 * and filing it under "survival" would be nonsense.
 *
 * The import marker editions need already exists: templates.imported_at has
 * been set by EggImporter since the first import. Editions gate the two
 * differently, so which games a catalogue offers is one question and whether
 * this install may import arbitrary eggs is another, but only the category is
 * new here.
 *
 * Note there is deliberately no min_edition column on either table. Which
 * editions include which games lives in config/editions.php, so an edition can
 * be repriced by editing a config file rather than by shipping a migration.
 */
return new class extends Migration
{
    /** The games that ship, and what they are. */
    private const CATEGORIES = [
        'minecraft' => 'sandbox',
        'palworld' => 'survival',
        'valheim' => 'survival',
        'rust' => 'survival',
        'ark-survival-ascended' => 'survival',
        'counter-strike-2' => 'shooter',
    ];

    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('category', 32)->nullable()->after('slug')->index();
        });

        foreach (self::CATEGORIES as $slug => $category) {
            DB::table('games')->where('slug', $slug)->update(['category' => $category]);
        }

        // Anything else already in the catalogue is uncategorised rather than
        // guessed at. A wrong category is worse than a blank one: it puts a game
        // somewhere nobody browsing for it will look.
        DB::table('games')->whereNull('category')->update(['category' => 'other']);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
