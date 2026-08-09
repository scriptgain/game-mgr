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
        return response()->json($this->document(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The document itself, so the human-readable reference at /api-docs renders
     * from exactly the same source a client generator consumes. Two
     * descriptions of one API that can disagree is worse than one.
     */
    public function document(): array
    {
        return [
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
        ];
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
        $subject = str_replace(['-', '_'], ' ', (string) end($segments));
        $noun = Str::of($subject)->singular()->toString();

        // The last segment of an action route is a VERB, not a thing: power,
        // activate, upload, reinstall. "Create or act on a upload" was both
        // ungrammatical and wrong about what the call does, so those are
        // described as the action they are.
        // No owner suffix on these: the sentence already names what it acts
        // on, and "Upload a file on one server" reads like a bad translation.
        if (in_array($method, ['POST', 'PUT'], true) && isset(self::ACTIONS[$subject])) {
            return self::ACTIONS[$subject];
        }

        // Acronyms read as letters, so they take the article their SOUND wants
        // and they are not lowercased into "a sso".
        if (isset(self::ACRONYMS[$noun])) {
            $noun = self::ACRONYMS[$noun];
            $subject = $noun;
        }

        // "Delete a allocation" is the kind of thing that makes generated
        // documentation look generated. So is "Create an user": the article
        // follows the SOUND, and a leading u is only a vowel sound when it is
        // not the "yoo" of user, unit or uuid.
        $a = self::takesAn($noun) ? 'an ' : 'a ';

        return match ($method) {
            'GET' => str_ends_with($uri, '}')
                ? 'Fetch one '.$noun
                : 'List '.$subject.$this->ownerSuffix($uri),
            'POST' => 'Create '.$a.$noun.$this->ownerSuffix($uri),
            'PATCH', 'PUT' => 'Update '.$a.$noun,
            'DELETE' => 'Delete '.$a.$noun,
            default => Str::ucfirst($subject),
        };
    }

    /**
     * Action endpoints, spelled out.
     *
     * A generated summary is only worth having if it says something a reader
     * could not work out faster from the URL. These are the ones where the verb
     * carries the meaning.
     */
    private const ACTIONS = [
        'power' => 'Start, stop, restart or kill the server',
        'command' => 'Send a console command',
        'reinstall' => 'Reinstall the server',
        'suspend' => 'Suspend the server',
        'unsuspend' => 'Lift a suspension',
        'transfer' => 'Move the server to another node',
        'upload' => 'Upload a file',
        'activate' => 'Make this the active one',
        'restore' => 'Restore from this backup',
        'lock' => 'Lock this backup against deletion',
        'primary' => 'Make this the primary allocation',
        'run' => 'Run it now, off schedule',
        'toggle' => 'Turn it on or off',
        'refresh' => 'Re-check against the source',
        'kick' => 'Kick this player',
        'ban' => 'Ban this player',
        'unban' => 'Lift a ban',
        'whitelist' => 'Add or remove from the whitelist',
        'op' => 'Grant or revoke operator',
        'rename' => 'Rename it',
        'archive' => 'Compress files into one archive',
        'extract' => 'Unpack an archive',
        'mkdir' => 'Create a directory',
        'write' => 'Write file contents',
        'test' => 'Send a test message',
        'sync' => 'Reconcile with the provider',
    ];

    /** Path words that are initialisms, with the casing a reader expects. */
    private const ACRONYMS = ['sso' => 'SSO', 'api' => 'API', 'rcon' => 'RCON', 'sftp' => 'SFTP', 'dns' => 'DNS'];

    /**
     * Does this word want "an"?
     *
     * By sound, not by spelling. A leading vowel usually means yes, except the
     * "yoo" words (user, unit, uuid) which take "a", and an initialism read as
     * letters where F, H, L, M, N, R, S and X all begin with a vowel sound.
     */
    private static function takesAn(string $noun): bool
    {
        if ($noun === strtoupper($noun) && ctype_alpha($noun)) {
            return in_array($noun[0], ['A', 'E', 'F', 'H', 'I', 'L', 'M', 'N', 'O', 'R', 'S', 'X'], true);
        }

        if (preg_match('/^(us|uni|uu|eu|one)/i', $noun) === 1) {
            return false;
        }

        return in_array(strtolower($noun[0] ?? ''), ['a', 'e', 'i', 'o', 'u'], true);
    }

    /** " on one server" and the like, so a nested route says whose it is. */
    private function ownerSuffix(string $uri): string
    {
        foreach (['{server}' => ' on one server', '{node}' => ' on one node', '{user}' => ' for one user'] as $token => $suffix) {
            if (str_contains($uri, $token)) {
                return $suffix;
            }
        }

        return '';
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
