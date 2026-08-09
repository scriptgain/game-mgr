<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API reference.
 *
 * What is worth pinning is not that the page renders. It is that the page and
 * the machine-readable document cannot disagree, because they are the same
 * source: the moment somebody hand-maintains a second list, one of them starts
 * lying and nobody finds out for months.
 */
class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);
    }

    /** Public, like the guide: readable before anybody installs anything. */
    public function test_it_is_readable_without_signing_in(): void
    {
        $this->get('/api-docs')->assertOk()->assertSee('API Reference');
        $this->get('/api/openapi.json')->assertOk();
    }

    public function test_every_endpoint_in_the_document_appears_on_the_page(): void
    {
        $spec = $this->getJson('/api/openapi.json')->json();
        $html = $this->get('/api-docs')->assertOk()->getContent();

        $missing = [];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! is_array($operation) || ! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }
                if (! str_contains($html, e($path))) {
                    $missing[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertSame([], $missing, 'the reference is missing endpoints the API document declares');
        $this->assertGreaterThan(50, count($spec['paths']), 'the document itself looks empty');
    }

    /**
     * Generated prose still has to read like prose. These were all real:
     * "Delete a allocation", "Create or act on a upload", "Worlds for one
     * record". A reader who trips over the grammar stops trusting the content.
     */
    public function test_the_generated_summaries_are_english(): void
    {
        $spec = $this->getJson('/api/openapi.json')->json();

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! is_array($operation) || ! isset($operation['summary'])) {
                    continue;
                }

                $summary = $operation['summary'];

                foreach ([' a a', ' a e', ' a i', ' a o'] as $slip) {
                    $this->assertStringNotContainsString($slip, ' '.strtolower($summary),
                        "\"$summary\" needs 'an', not 'a' ($path)");
                }

                // The other direction, which is the easy one to get wrong once
                // the first is fixed: "an user" and "an uuid" both read as
                // typos because the article follows the sound, not the letter.
                foreach ([' an us', ' an uni', ' an uu'] as $slip) {
                    $this->assertStringNotContainsString($slip, ' '.strtolower($summary),
                        "\"$summary\" needs 'a', not 'an' ($path)");
                }

                $this->assertStringNotContainsString('for one record', $summary,
                    "\"$summary\" is filler rather than a description ($path)");
                $this->assertStringNotContainsString('act on', $summary,
                    "\"$summary\" does not say what the endpoint does ($path)");
            }
        }
    }

    /**
     * Endpoints that legitimately take no JSON body.
     *
     * A named list rather than a count, because the check below is only worth
     * having if adding a write endpoint without documenting its body FAILS.
     * A silent allowance would make the guarantee decorative.
     */
    private const NO_BODY = [
        'POST /api/application/servers/{server}/suspend',
        'POST /api/application/servers/{server}/unsuspend',
        'POST /api/application/users/{user}/sso',
        'POST /api/client/servers/{server}/backups/{backup}/lock',
        'POST /api/client/servers/{server}/backups/{backup}/restore',
        'POST /api/client/servers/{server}/files/upload',
        'POST /api/client/servers/{server}/mods/{mod}/toggle',
        'POST /api/client/servers/{server}/network',
        'POST /api/client/servers/{server}/network/{allocation}/primary',
        'POST /api/client/servers/{server}/worlds/upload',
        'POST /api/client/servers/{server}/worlds/{world}/activate',
    ];

    /**
     * The gap this whole exercise was about: a write endpoint that does not say
     * what it accepts sends somebody to read the panel's own forms.
     */
    public function test_every_write_endpoint_documents_its_body_or_is_named_as_having_none(): void
    {
        $spec = $this->getJson('/api/openapi.json')->json();
        $undocumented = [];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! in_array($method, ['post', 'put', 'patch'], true)) {
                    continue;
                }

                $key = strtoupper($method).' '.$path;

                if (! isset($operation['requestBody']) && ! in_array($key, self::NO_BODY, true)) {
                    $undocumented[] = $key;
                }
            }
        }

        $this->assertSame([], $undocumented,
            "these write endpoints describe no body. Give the controller a rules() method, or add them to NO_BODY if they really take none.");
    }

    /** The schema has to be usable, not merely present. */
    public function test_a_documented_body_carries_types_and_required_fields(): void
    {
        $spec = $this->getJson('/api/openapi.json')->json();
        $schema = $spec['paths']['/api/application/servers']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame('object', $schema['type']);
        $this->assertSame(['name', 'owner_id', 'template_id', 'memory', 'disk', 'cpu'], $schema['required']);
        $this->assertSame('integer', $schema['properties']['memory']['type']);
        $this->assertSame(0, $schema['properties']['memory']['minimum']);
        $this->assertSame(120, $schema['properties']['name']['maxLength']);
        // A relation is prose, not a guessed enum of live ids.
        $this->assertStringContainsString('existing node', $schema['properties']['node_id']['description']);
    }

    /** An action route is a verb and belongs with the thing it acts on. */
    public function test_server_actions_are_described_by_what_they_do(): void
    {
        $spec = $this->getJson('/api/openapi.json')->json();

        $this->assertSame(
            'Start, stop, restart or kill the server',
            $spec['paths']['/api/client/servers/{server}/power']['post']['summary'],
        );
    }
}
