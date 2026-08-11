<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Template;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The vendored library seeds once and then leaves everything alone.
 *
 * The seeder runs on every deploy. With nine hand-written templates a mistake
 * here was an annoyance; with two hundred and fifty it is two hundred and fifty
 * duplicates, or two hundred and fifty reverted customisations, on every update.
 */
class CatalogueLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_real_library(): void
    {
        $this->seed(CatalogueSeeder::class);

        // The exact numbers move as the catalogue is refreshed. What matters is
        // that it is a library rather than a handful.
        $this->assertGreaterThan(100, Game::count(), 'the catalogue should hold a lot of games');
        $this->assertGreaterThan(200, Template::count());
    }

    /**
     * Running twice must change nothing.
     *
     * This is the property the whole design rests on: the seeder runs on every
     * deploy, so anything not idempotent here compounds forever.
     */
    public function test_seeding_twice_imports_nothing_the_second_time(): void
    {
        $this->seed(CatalogueSeeder::class);
        $games = Game::count();
        $templates = Template::count();

        $this->seed(CatalogueSeeder::class);

        $this->assertSame($games, Game::count());
        $this->assertSame($templates, Template::count());
    }

    /**
     * An admin's edit to a shipped template survives an update.
     *
     * The seeder used to write with updateOrCreate unconditionally, so every
     * panel update silently reverted whatever an operator had changed. It
     * reverted the TF2 template's licensed Steam login twice in one evening on
     * gamemgr001, and nothing said the update had done it.
     */
    public function test_a_customised_template_is_left_alone(): void
    {
        $this->seed(CatalogueSeeder::class);

        $template = Template::whereNull('imported_from')->where('name', 'TF2 Dedicated')->firstOrFail();
        $template->forceFill([
            'steam_anonymous' => false,
            'requires_steam_account' => true,
            'customised_at' => now(),
        ])->save();

        $this->seed(CatalogueSeeder::class);

        $fresh = $template->fresh();
        $this->assertFalse((bool) $fresh->steam_anonymous, 'the seeder reverted an admin edit');
        $this->assertTrue((bool) $fresh->requires_steam_account);
    }

    /** An untouched shipped template stays ours to keep current. */
    public function test_an_untouched_template_is_still_updated(): void
    {
        $this->seed(CatalogueSeeder::class);

        $template = Template::whereNull('imported_from')->where('name', 'TF2 Dedicated')->firstOrFail();
        $template->forceFill(['steam_anonymous' => false])->save();

        $this->seed(CatalogueSeeder::class);

        $this->assertTrue((bool) $template->fresh()->steam_anonymous, 'the seeder should own an uncustomised template');
    }

    /** Community definitions carrying Steam credentials arrive bound, not in plain text. */
    public function test_imported_templates_never_hold_steam_credentials(): void
    {
        $this->seed(CatalogueSeeder::class);

        $leaked = \App\Models\TemplateVariable::whereIn('env_variable', ['STEAM_USER', 'STEAM_PASS', 'STEAM_AUTH'])->count();

        $this->assertSame(0, $leaked, 'a Steam password would sit in server_variables in plain text');
        $this->assertGreaterThan(0, Template::where('requires_steam_account', true)->count());
    }
}
