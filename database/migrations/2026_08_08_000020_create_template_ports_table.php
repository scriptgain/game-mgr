<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A game does not use "a port". It uses a set of them, and the set is the
 * thing that has to be reserved.
 *
 * Palworld listens on 8211/udp, answers Steam queries on 27015/udp and takes
 * RCON on 25575/tcp. Minecraft Java listens on 25565/tcp and answers its own
 * query protocol on 25565/UDP, which is the same number and a different
 * protocol. Neither of those can be described by one port column plus two
 * integer offsets, which is all a template had: the offsets were arithmetic
 * from a number that was not stored until today, no row could say "this one is
 * TCP while the game port is UDP", and there was no way to declare a fourth
 * port at all. Rust's companion app sits on 28082 and nothing could say so.
 *
 * So the port set becomes rows. Each row is one listener:
 *
 *   role         game | query | rcon | sftp, or any word an operator invents
 *   protocol     tcp | udp | both
 *   source       fixed  -> use `port` verbatim
 *                offset -> use the game port plus `port_offset`
 *   required     an allocation that cannot be satisfied fails the whole create
 *
 * `source` matters because the two behave differently when the canonical port
 * is already taken: the allocator shifts the entire set by the same amount, so
 * the layout a game expects is preserved rather than scattered.
 *
 * The old columns are kept and are now a mirror of the game, query and rcon
 * rows, written by Template::syncPortColumns(). Nothing that reads them had to
 * change, and nothing that reads them is the source of truth any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            // Not called "key": KEY is reserved in MySQL and every raw query
            // touching it would need quoting. "role" also matches the column
            // this ends up written to on allocations.
            $table->string('role', 32);
            $table->string('label', 60);
            $table->string('protocol', 8)->default('both');  // tcp|udp|both
            $table->string('source', 8)->default('fixed');   // fixed|offset
            $table->unsignedInteger('port')->nullable();     // source = fixed
            $table->integer('port_offset')->nullable();      // source = offset
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['template_id', 'role']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('template_ports');
    }

    /**
     * Turn what every existing template already says into rows.
     *
     * Data migration rather than a seeder re-run, for the same reason the
     * default_port migration was: an install that has been running for a week
     * has its own templates and its own edits to the shipped ones, and neither
     * may be replaced to gain a table.
     */
    private function backfill(): void
    {
        $templates = DB::table('templates')->whereNotNull('default_port')->get();
        $now = now();

        foreach ($templates as $t) {
            $rows = [];

            $rows[] = [
                'template_id' => $t->id,
                'role' => 'game',
                'label' => 'Game Port',
                'protocol' => $t->default_protocol ?: 'both',
                'source' => 'fixed',
                'port' => (int) $t->default_port,
                'port_offset' => null,
                'required' => true,
                'sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Every query protocol GameMGR speaks is UDP. A2S is UDP, the
            // Minecraft query protocol is UDP even though the game port is TCP,
            // and GameSpy is UDP. That last one is the whole reason this table
            // exists: a Minecraft template could not previously say it needs
            // 25565 open on both.
            if ($t->query_protocol) {
                $rows[] = [
                    'template_id' => $t->id,
                    'role' => 'query',
                    'label' => 'Query Port',
                    'protocol' => 'udp',
                    'source' => 'offset',
                    'port' => null,
                    'port_offset' => (int) $t->query_port_offset,
                    'required' => true,
                    'sort' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Source RCON and Minecraft RCON are both TCP. BattlEye's RCON is
            // the odd one out and runs over UDP.
            if ($t->rcon_supported) {
                $rows[] = [
                    'template_id' => $t->id,
                    'role' => 'rcon',
                    'label' => 'RCON Port',
                    'protocol' => $t->rcon_protocol === 'battleye' ? 'udp' : 'tcp',
                    'source' => 'offset',
                    'port' => null,
                    'port_offset' => (int) $t->rcon_port_offset,
                    'required' => true,
                    'sort' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('template_ports')->insert($rows);
        }
    }
};
