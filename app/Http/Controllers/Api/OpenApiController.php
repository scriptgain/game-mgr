<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/**
 * The API described in a form a machine can read.
 *
 * Generated from the route table rather than written by hand, for the reason
 * the docs page was already wrong about its own endpoints within a week: a
 * hand-maintained list of a hundred endpoints is a list that drifts. This one
 * cannot, because it is derived from the same routes it documents.
 *
 * Unauthenticated on purpose. A description of which endpoints exist is not a
 * secret, every one of them still demands a token, and a specification you need
 * a credential to read is one nobody generates a client from.
 */
class OpenApiController extends Controller
{
    public function show()
    {
        return response()->json([
            'openapi' => '3.1.0',
            'info' => [
                'title' => config('app.name', 'GameMGR').' API',
                'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '1.0.0',
                'description' => implode("\n\n", [
                    'Two scopes. Application drives provisioning and administration: accounts, servers, nodes, allocations and the catalogue. Client is scoped to the servers a token owner can already reach.',
                    'Every response carries an envelope: one object as `object` and `attributes`, a list as `object: list` with `data` and `meta.pagination`. Ask for related records with `?include=node,allocations`.',
                    'A token is created under Account, API Credentials, and sent as `Authorization: Bearer`. A client token cannot reach an application endpoint.',
                ]),
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/')]],
            'components' => [
                'securitySchemes' => [
                    'bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'An API token from Account, API Credentials.'],
                ],
                'schemas' => $this->schemas(),
            ],
            'security' => [['bearer' => []]],
            'tags' => [
                ['name' => 'application', 'description' => 'Administration and provisioning. Needs an application token.'],
                ['name' => 'client', 'description' => "A customer's own servers. Needs a client token."],
            ],
            'paths' => $this->paths(),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function paths(): array
    {
        $paths = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (! Str::startsWith($uri, ['api/application', 'api/client'])) {
                continue;
            }

            $scope = Str::startsWith($uri, 'api/application') ? 'application' : 'client';
            // OpenAPI wants {id}; Laravel already writes them that way, so the
            // path is used as-is apart from the leading slash.
            $path = '/'.$uri;

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $paths[$path][strtolower($method)] = array_filter([
                    'tags' => [$scope],
                    'summary' => $this->summarise($method, $uri),
                    'operationId' => $route->getName() ?: strtolower($method).'-'.Str::slug($uri),
                    'parameters' => $this->parameters($route, $method),
                    'responses' => $this->responses($method),
                ]);
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * A readable sentence per endpoint, derived from the verb and the path.
     *
     * Derived rather than stored, so a new route is described the moment it
     * exists instead of the moment somebody remembers to describe it.
     */
    private function summarise(string $method, string $uri): string
    {
        $segments = array_values(array_filter(explode('/', $uri), fn ($s) => $s !== '' && ! str_starts_with($s, '{')));
        $subject = str_replace('-', ' ', (string) end($segments));
        $noun = Str::of($subject)->replace('_', ' ')->singular()->toString();

        return match ($method) {
            'GET' => str_contains($uri, '}') && ! str_ends_with($uri, '}')
                ? Str::ucfirst($subject).' for one record'
                : (str_ends_with($uri, '}') ? 'Fetch one '.$noun : 'List '.$subject),
            'POST' => 'Create or act on a '.$noun,
            'PATCH', 'PUT' => 'Update a '.$noun,
            'DELETE' => 'Delete a '.$noun,
            default => Str::ucfirst($subject),
        };
    }

    private function parameters($route, string $method): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        if ($method === 'GET' && ! str_ends_with($route->uri(), '}')) {
            $parameters[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']];
            $parameters[] = [
                'name' => 'per_page', 'in' => 'query',
                'description' => 'Up to 200.',
                'schema' => ['type' => 'integer', 'maximum' => 200],
            ];
            $parameters[] = [
                'name' => 'include', 'in' => 'query',
                'description' => 'Related records to embed, comma separated.',
                'schema' => ['type' => 'string'],
            ];
        }

        return $parameters;
    }

    private function responses(string $method): array
    {
        $ok = match ($method) {
            'POST' => ['201' => ['description' => 'Created'], '204' => ['description' => 'Done']],
            'DELETE' => ['204' => ['description' => 'Deleted']],
            default => ['200' => ['description' => 'OK', 'content' => ['application/json' => [
                'schema' => ['$ref' => '#/components/schemas/Envelope'],
            ]]]],
        };

        // The refusals are documented because they are the ones a caller has to
        // handle: a wrong scope, a permission they lack, and a conflict such as
        // deleting a port a server is still on.
        return $ok + [
            '401' => ['description' => 'No token, or one that is unknown or expired.'],
            '403' => ['description' => 'The token is the wrong scope, or the account lacks the permission.'],
            '409' => ['description' => 'Refused because of the current state, for example a suspended or running server.'],
            '422' => ['description' => 'The request did not validate.'],
        ];
    }

    private function schemas(): array
    {
        return [
            'Envelope' => [
                'type' => 'object',
                'properties' => [
                    'object' => ['type' => 'string', 'example' => 'server'],
                    'attributes' => ['type' => 'object'],
                ],
            ],
            'List' => [
                'type' => 'object',
                'properties' => [
                    'object' => ['type' => 'string', 'example' => 'list'],
                    'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Envelope']],
                    'meta' => [
                        'type' => 'object',
                        'properties' => ['pagination' => [
                            'type' => 'object',
                            'properties' => [
                                'total' => ['type' => 'integer'],
                                'count' => ['type' => 'integer'],
                                'per_page' => ['type' => 'integer'],
                                'current_page' => ['type' => 'integer'],
                                'total_pages' => ['type' => 'integer'],
                            ],
                        ]],
                    ],
                ],
            ],
            'Error' => [
                'type' => 'object',
                'properties' => ['message' => ['type' => 'string']],
            ],
        ];
    }
}
