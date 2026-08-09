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

                foreach ([' a a', ' a e', ' a i', ' a o', ' a u'] as $slip) {
                    $this->assertStringNotContainsString($slip, ' '.strtolower($summary),
                        "\"$summary\" needs 'an', not 'a' ($path)");
                }

                $this->assertStringNotContainsString('for one record', $summary,
                    "\"$summary\" is filler rather than a description ($path)");
                $this->assertStringNotContainsString('act on', $summary,
                    "\"$summary\" does not say what the endpoint does ($path)");
            }
        }
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
