<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * templates.mcjars: how a template says "I am a Minecraft Java server, and
 * here is where the type, the version and the build live".
 *
 * Why a column of its own rather than the two cheaper options.
 *
 * config_schema was the obvious place to hide a flag, and it is the wrong one.
 * It already means something specific and unrelated: the list of config FILES
 * a customer may edit. Template::configFiles() walks it expecting every entry
 * to have a `file` key, and the admin editor renders one tab per entry, so a
 * "minecraft": true sitting beside server.properties would have to be skipped
 * by both and would still show up in every export as a file that is not a file.
 *
 * A convention was the other option, and it is worse. "The game is called
 * Minecraft" breaks the moment somebody imports a Pterodactyl egg into a game
 * they named "MC", and it fires on Bedrock, which MCJars does not cover at all
 * because it indexes Java jars. "The image is itzg/*" breaks on a fork.
 *
 * And the deciding argument: the thing that has to be stored is not a boolean.
 * The environment variable that pins a build is different for every server
 * flavour, with no derivable pattern between them:
 *
 *     PAPER   -> PAPER_BUILD          FORGE    -> FORGE_VERSION
 *     PURPUR  -> PURPUR_BUILD         NEOFORGE -> NEOFORGE_VERSION
 *     FOLIA   -> FOLIABUILD           FABRIC   -> FABRIC_LOADER_VERSION
 *     SPIGOT  -> nothing at all       QUILT    -> QUILT_LOADER_VERSION
 *
 * So the column holds a small document, shaped like docker_images and
 * config_schema beside it, exported and imported with the template as one
 * unit. `mcjars IS NOT NULL` is the whole test for "does this get a version
 * picker", and nothing else in the panel has to learn what Minecraft is.
 *
 * The seeded Paper and Forge templates are then given their document here as
 * well as in the seeder, because an existing install never re-runs the seeder.
 * Their TYPE variable is widened from the single value it was locked to, since
 * a picker that offers one choice is not a picker, and the extra build
 * variables the newly offered types need are added alongside.
 */
return new class extends Migration
{
    /**
     * What each seeded Minecraft template becomes, keyed by template name
     * within the "minecraft" game.
     *
     * Paper's list is the drop in Paper family plus the two plain servers that
     * share its config files. Forge's list is the four mod loaders. They are
     * kept apart on purpose: a template is a promise about what a server is,
     * and one that offered Paper and Forge from the same dropdown would have a
     * Config tab describing files half of its own choices never write.
     */
    private const CATALOGUE = [
        'Paper' => [
            'builds' => [
                'PAPER' => 'PAPER_BUILD',
                'PURPUR' => 'PURPUR_BUILD',
                'FOLIA' => 'FOLIABUILD',
                'PUFFERFISH' => 'PUFFERFISH_BUILD',
                'SPIGOT' => null,
                'VANILLA' => null,
            ],
            'variables' => [
                ['name' => 'Purpur Build', 'env_variable' => 'PURPUR_BUILD', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Purpur. Blank means the newest build of the chosen version.', 'sort' => 41],
                ['name' => 'Folia Build', 'env_variable' => 'FOLIABUILD', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Folia. No underscore: that is the name the image uses.', 'sort' => 42],
                ['name' => 'Pufferfish Build', 'env_variable' => 'PUFFERFISH_BUILD', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Pufferfish. Blank means the newest build.', 'sort' => 43],
            ],
        ],
        'Forge' => [
            'builds' => [
                'FORGE' => 'FORGE_VERSION',
                'NEOFORGE' => 'NEOFORGE_VERSION',
                'FABRIC' => 'FABRIC_LOADER_VERSION',
                'QUILT' => 'QUILT_LOADER_VERSION',
            ],
            'variables' => [
                ['name' => 'NeoForge Version', 'env_variable' => 'NEOFORGE_VERSION', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is NeoForge. Blank means the newest build for the chosen Minecraft version.', 'sort' => 41],
                ['name' => 'Fabric Loader Version', 'env_variable' => 'FABRIC_LOADER_VERSION', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Fabric. Blank means the newest loader.', 'sort' => 42],
                ['name' => 'Quilt Loader Version', 'env_variable' => 'QUILT_LOADER_VERSION', 'default_value' => '', 'rules' => 'nullable|string|max:40', 'description' => 'Only read when the server type is Quilt. Blank means the newest loader.', 'sort' => 43],
            ],
        ],
    ];

    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('mcjars')->nullable()->after('config_schema');
        });

        $game = DB::table('games')->where('slug', 'minecraft')->value('id');

        if (! $game) {
            return;
        }

        foreach (self::CATALOGUE as $name => $spec) {
            $template = DB::table('templates')->where('game_id', $game)->where('name', $name)->first();

            if (! $template) {
                continue;
            }

            DB::table('templates')->where('id', $template->id)->update([
                'mcjars' => json_encode([
                    'type_variable' => 'TYPE',
                    'version_variable' => 'VERSION',
                    'builds' => $spec['builds'],
                ]),
            ]);

            // The type stops being locked to one value. It stays hidden from
            // clients on the Forge side of the house in the sense that it is
            // still theirs to change; what changes is that "in:FORGE" no
            // longer rejects every other thing the same image can run.
            DB::table('template_variables')
                ->where('template_id', $template->id)
                ->where('env_variable', 'TYPE')
                ->update([
                    'rules' => 'required|in:'.implode(',', array_keys($spec['builds'])),
                    'user_editable' => true,
                    'description' => 'Which server software the image downloads. Picked from the live MCJars catalogue.',
                ]);

            // MCJars version ids are longer than the twenty characters the
            // original rule allowed: "26.2-rc-2" fits, "26.3-snapshot-7" is
            // fifteen, and a pre-release can run past twenty.
            DB::table('template_variables')
                ->where('template_id', $template->id)
                ->where('env_variable', 'VERSION')
                ->update(['rules' => 'required|string|max:40']);

            // A build variable that is `required` is a trap once the type can
            // change: pick Fabric and the Forge version box, which nothing on
            // screen refers to any more, still has to be filled in. Every one of
            // them becomes optional, and blank means "newest", which is what
            // the image does with an unset variable anyway.
            foreach (array_filter($spec['builds']) as $env) {
                DB::table('template_variables')
                    ->where('template_id', $template->id)
                    ->where('env_variable', $env)
                    ->where('rules', 'like', 'required%')
                    ->update(['rules' => 'nullable|string|max:40']);
            }

            foreach ($spec['variables'] as $variable) {
                $exists = DB::table('template_variables')
                    ->where('template_id', $template->id)
                    ->where('env_variable', $variable['env_variable'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('template_variables')->insert($variable + [
                    'template_id' => $template->id,
                    'user_viewable' => true,
                    'user_editable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('mcjars');
        });
    }
};
