<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The catalogue auditor.
 *
 * It exists because installing 259 templates to find out which ones work is
 * terabytes and days, and most of what goes wrong does not need an install to
 * find. Every check in it is a regex against somebody else's shell script, so
 * every one of these tests pins a mistake the first version actually made.
 */
class AuditCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function template(array $attrs = [], array $vars = []): Template
    {
        $game = Game::create(['name' => 'Test '.Str::random(5), 'slug' => 'test-'.Str::random(8)]);

        $template = Template::create(array_merge([
            'game_id' => $game->id,
            'name' => 'Test Template',
            'runtime' => 'docker',
            'docker_images' => ['Default' => 'ghcr.io/example/thing:latest'],
            'startup' => './run',
        ], $attrs));

        foreach ($vars as $env => $default) {
            TemplateVariable::create([
                'template_id' => $template->id,
                'name' => $env, 'env_variable' => $env, 'default_value' => $default,
                'user_viewable' => true, 'user_editable' => true, 'rules' => 'nullable|string',
            ]);
        }

        return $template;
    }

    /**
     * Artisan::call, not $this->artisan.
     *
     * The latter returns a PendingCommand that only runs when it is asserted
     * against, so Artisan::output() came back empty and every assertion here
     * failed with a confusing "expected (nothing) to contain ...".
     */
    private function audit(array $flags = []): string
    {
        \Illuminate\Support\Facades\Artisan::call('gamemgr:audit-catalogue', $flags);

        return \Illuminate\Support\Facades\Artisan::output();
    }

    /**
     * The SA-MP shape, and the whole reason this command exists.
     *
     * Its script reads $VERSION, its template declares "Version". The shell is
     * case sensitive, so the variable is empty, the URL loses its version
     * number, curl fetches a 404 page and tar chokes on it. The script exits 0
     * regardless.
     *
     * The first version of this check uppercased BOTH sides before comparing,
     * which made the two look identical and missed the only bug it was written
     * to catch.
     */
    public function test_a_variable_read_in_the_wrong_case_is_broken(): void
    {
        $this->template([
            'script_install' => "#!/bin/bash\ncurl -o s.tar.gz https://example.test/\${VERSION}.tar.gz\n",
        ], ['Version' => '0.3.7']);

        $out = $this->audit();

        $this->assertStringContainsString('BROKEN', $out);
        $this->assertStringContainsString('VERSION', $out);
        $this->assertStringContainsString('1 that cannot work', $out);
    }

    /** Declared with the same spelling is simply fine. */
    public function test_a_variable_read_in_the_right_case_is_clean(): void
    {
        $this->template([
            'script_install' => "#!/bin/bash\ncurl -o s.tar.gz https://example.test/\${VERSION}.tar.gz\n",
        ], ['VERSION' => '0.3.7']);

        $this->assertStringContainsString('0 that cannot work', $this->audit());
    }

    /**
     * A variable read with a default is deliberate, not a fault. Most of the
     * catalogue reads optional Pterodactyl inputs this way, and treating them
     * as problems is what had 156 of 259 templates warning about nothing.
     */
    public function test_a_defaulted_variable_is_not_a_warning(): void
    {
        $this->template([
            'script_install' => "#!/bin/bash\necho \${SRCDS_BETAID:-none} \${SOMETHING_OPTIONAL:-x}\n",
        ]);

        $out = $this->audit();
        $this->assertStringContainsString('0 that cannot work', $out);
        $this->assertStringNotContainsString('SRCDS_BETAID', $out);
    }

    /**
     * Lower case and single character names are shell locals, not inputs. The
     * TF2 template warned about $P, a variable written two lines above its use.
     */
    public function test_shell_locals_are_not_reported(): void
    {
        $this->template([
            'script_install' => "#!/bin/bash\nfor mod in a b; do echo \$mod; done\n",
            'startup' => 'P=${SERVER_PORT:-27015}'."\n".'exec ./run --port "$P"',
        ]);

        $out = $this->audit();
        $this->assertStringNotContainsString('$mod', $out);
        $this->assertStringNotContainsString('undeclared P', $out);
    }

    /** A docker template with no image cannot start, and that is worth saying. */
    public function test_a_docker_template_with_no_image_is_broken(): void
    {
        $this->template(['docker_images' => null]);

        $this->assertStringContainsString('1 that cannot work', $this->audit());
    }

    /**
     * The MTA shape: four downloads, one dead.
     *
     * MTA's script names four URLs. Three answer and one is a version pinned
     * nightly that has rotated away; the script uses a working one and the
     * install succeeds. The first version flagged any single failure, and so
     * reported the only template known to work as broken.
     */
    public function test_one_dead_download_among_several_is_a_warning_not_broken(): void
    {
        Http::fake([
            'good.test/*' => Http::response('x', 206),
            'gone.test/*' => Http::response('', 404),
        ]);

        $this->template([
            'script_install' => "#!/bin/bash\n"
                ."curl -o a https://good.test/a.tar.gz\n"
                ."curl -o b https://good.test/b.tar.gz\n"
                ."curl -o c https://gone.test/c.tar.gz\n",
        ]);

        $out = $this->audit(['--urls' => true]);

        $this->assertStringContainsString('0 that cannot work', $out);
        $this->assertStringContainsString('1 of 3 downloads dead', $out);
    }

    /** Every download gone is genuinely broken, and Quilt and PowerNukkitX are. */
    public function test_every_download_dead_is_broken(): void
    {
        Http::fake(['gone.test/*' => Http::response('', 404)]);

        $this->template([
            'script_install' => "#!/bin/bash\ncurl -o a https://gone.test/a.tar.gz\n",
        ]);

        $this->assertStringContainsString('1 that cannot work', $this->audit(['--urls' => true]));
    }

    /**
     * Authentication required is not the same as gone.
     *
     * A script that supplies a key gets through where this check cannot, and
     * reporting the template dead helps nobody.
     */
    public function test_an_endpoint_needing_auth_is_not_reported_dead(): void
    {
        Http::fake(['locked.test/*' => Http::response('', 401)]);

        $this->template([
            'script_install' => "#!/bin/bash\ncurl -o a https://locked.test/a.tar.gz\n",
        ]);

        $this->assertStringContainsString('0 that cannot work', $this->audit(['--urls' => true]));
    }

    /**
     * An API root is not a download. The script appends a path built at runtime,
     * so the bare host answers 404 or 401 and means nothing about the template.
     * api.curseforge.com and api.modrinth.com both did.
     */
    public function test_api_hosts_and_bare_hosts_are_skipped(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->template([
            'script_install' => "#!/bin/bash\necho https://api.example.test/ https://bare.test/\n",
        ]);

        $this->assertStringContainsString('0 that cannot work', $this->audit(['--urls' => true]));
    }
}
