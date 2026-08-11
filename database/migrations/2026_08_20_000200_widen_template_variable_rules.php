<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A validation rule can be much longer than 255 characters.
 *
 * Found importing the community catalogue: Black Mesa's map list and V Rising's
 * preset list are both `in:` rules naming eighty-odd values, at roughly 700 and
 * 500 characters. The column was varchar(255), MySQL rejected the row, and the
 * whole template went down with it.
 *
 * Nine hand-written templates never came close, so nothing found this until two
 * hundred and fifty community ones arrived.
 *
 * Written through Schema rather than a raw ALTER: the tests run on SQLite,
 * which does not understand MySQL's MODIFY syntax, and a migration that only
 * works on one engine fails at the least convenient moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // Nullable, because MySQL will not take a DEFAULT on a TEXT column
            // and the old varchar had one. The default moves to the model, so
            // every caller that omitted `rules` and relied on the database to
            // fill it in keeps working. Dropping it outright broke sixteen
            // tests that create a variable without one.
            $table->text('rules')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->string('rules', 255)->default('nullable|string')->nullable(false)->change();
        });
    }
};
