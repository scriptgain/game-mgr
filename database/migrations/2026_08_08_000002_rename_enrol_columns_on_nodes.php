<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * GameMGR is sold into the US market, so the British "enrol" spelling is wrong
 * everywhere it appears. This is the database half of that rename.
 *
 * Rename, never drop and recreate. A live install can have a node enrolled
 * right now, and dropping enrol_token_expires_at would throw away the state the
 * panel uses to decide whether an outstanding token is still good. The create
 * migration now writes the new names directly, so on a fresh database these
 * columns already exist under the new spelling and the guards make this a
 * no-op.
 *
 * enrolled_at is not touched: "enrolled" is spelled the same in both dialects.
 */
return new class extends Migration
{
    /** Old name => new name. */
    private const COLUMNS = [
        'enrol_token' => 'enroll_token',
        'enrol_token_expires_at' => 'enroll_token_expires_at',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $old => $new) {
            if (Schema::hasColumn('nodes', $old) && ! Schema::hasColumn('nodes', $new)) {
                Schema::table('nodes', function ($table) use ($old, $new) {
                    $table->renameColumn($old, $new);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::COLUMNS, true) as $old => $new) {
            if (Schema::hasColumn('nodes', $new) && ! Schema::hasColumn('nodes', $old)) {
                Schema::table('nodes', function ($table) use ($old, $new) {
                    $table->renameColumn($new, $old);
                });
            }
        }
    }
};
