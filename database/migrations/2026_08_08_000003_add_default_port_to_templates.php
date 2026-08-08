<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A template knows how to install and run a game but never recorded the port
 * that game is normally reached on.
 *
 * rcon_port_offset and query_port_offset have always been offsets from a number
 * nothing stored, so the panel could derive an RCON port without ever knowing
 * the game port it was derived from. That gap is why a fresh install has no
 * allocations: there is nothing to generate them from, and an operator has to
 * know off the top of their head that Palworld wants 8211 and Bedrock wants
 * 19132.
 *
 * The canonical port belongs on the template rather than the game, because Java
 * and Bedrock Minecraft are one Game with two very different ports.
 */
return new class extends Migration
{
    /**
     * Canonical ports for the shipped catalogue. Deliberately data in the
     * migration rather than a seeder re-run: an existing install has these
     * templates already and must not have its rows replaced to gain a column.
     */
    private array $ports = [
        'Paper' => [25565, 'tcp'],
        'Forge' => [25565, 'tcp'],
        'Bedrock' => [19132, 'udp'],
        'Rust Vanilla' => [28015, 'udp'],
        'Rust Oxide' => [28015, 'udp'],
        'Valheim Dedicated' => [2456, 'udp'],
        'ARK ASA' => [7777, 'udp'],
        'CS2 Dedicated' => [27015, 'udp'],
        'Palworld Dedicated' => [8211, 'udp'],
    ];

    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedInteger('default_port')->nullable()->after('runtime');
            // both is the honest default for an unknown template: the Docker
            // driver already publishes each port on TCP and UDP together, and
            // narrowing that without knowing the game would break it.
            $table->string('default_protocol', 8)->default('both')->after('default_port');
        });

        foreach ($this->ports as $name => [$port, $protocol]) {
            DB::table('templates')->where('name', $name)->update([
                'default_port' => $port,
                'default_protocol' => $protocol,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['default_port', 'default_protocol']);
        });
    }
};
