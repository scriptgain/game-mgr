<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where inside the container a template keeps its files.
 *
 * /home/container is the convention most community images follow, and what the
 * imported egg format assumes, so it is the default. It is not universal:
 * itzg/minecraft-server uses /data. Mounting the wrong path is a quiet failure,
 * not a loud one, because the server starts perfectly and writes its whole
 * world into the container's throwaway layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('data_path')->default('/home/container')->after('script_entry');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('data_path');
        });
    }
};
