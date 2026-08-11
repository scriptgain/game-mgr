<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an admin last edited a shipped template by hand.
 *
 * The catalogue seeder runs on every update and writes with updateOrCreate,
 * so it silently reverted any change an admin had made to a template it ships.
 * That happened twice in one evening to the TF2 template on gamemgr001: an
 * operator sets a template to use a licensed Steam login, updates the panel a
 * week later, and the setting is quietly gone. Nothing says the update did it,
 * which makes it close to undiagnosable from the outside.
 *
 * Tolerable at nine shipped templates. Not at two hundred and fifty.
 *
 * Null means untouched, so the seeder owns it and may keep it current. Set
 * means a person has an opinion about this template and the seeder leaves it
 * alone from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->timestamp('customised_at')->nullable()->after('imported_at');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('customised_at');
        });
    }
};
