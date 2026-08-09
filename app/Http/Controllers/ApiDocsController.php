<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\OpenApiController;
use Illuminate\Support\Str;

/**
 * The API reference, rendered from the OpenAPI document rather than written.
 *
 * The old page was a hand-written list of endpoints inside the panel chrome,
 * and a hand-written list is wrong the day after somebody adds a route. This
 * one reads the same document a client generator would, so a new endpoint
 * appears here the moment it exists and a removed one disappears with it.
 *
 * Rendered on the SERVER, not fetched by JavaScript in the browser. Docs should
 * be readable with scripting off, findable by ctrl-F on first paint, and
 * linkable straight to an anchor, none of which survive a page that assembles
 * itself after load.
 *
 * Public, like the docs page before it: somebody evaluating the panel should be
 * able to read what its API can do before they install it, and the document
 * describes shapes rather than data.
 */
class ApiDocsController extends Controller
{
    public function show(OpenApiController $openapi)
    {
        $spec = $openapi->document();

        $scopes = $this->group($spec);

        return view('api-docs', [
            'spec' => $spec,
            'scopes' => $scopes,
            'total' => collect($scopes)->flatten(1)->flatten(1)->count(),
            'baseUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    /**
     * Two scopes, each split into the resources somebody actually thinks in.
     *
     * The grouping key is the path, not the operationId: a reader is looking
     * for "backups" because that is the word in the URL they are holding.
     *
     * @return array<string,array<string,array<int,array<string,mixed>>>>
     */
    private function group(array $spec): array
    {
        $out = ['application' => [], 'client' => []];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! is_array($operation) || ! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $scope = $operation['tags'][0] ?? 'application';
                $resource = $this->resourceOf($path);

                $out[$scope][$resource][] = [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'summary' => $operation['summary'] ?? '',
                    'parameters' => $operation['parameters'] ?? [],
                    'body' => $schema = $operation['requestBody']['content']['application/json']['schema'] ?? null,
                    'example' => $schema ? self::example($schema) : null,
                    'anchor' => Str::slug($method.'-'.$path),
                    'id' => $operation['operationId'] ?? '',
                ];
            }
        }

        foreach ($out as $scope => $resources) {
            ksort($resources);
            // Read order within a resource: list, then fetch one, then the
            // things that change it. Alphabetical would put DELETE first.
            $rank = ['GET' => 0, 'POST' => 1, 'PATCH' => 2, 'PUT' => 3, 'DELETE' => 4];
            foreach ($resources as $name => $operations) {
                usort($operations, fn ($a, $b) => [strlen($a['path']), $rank[$a['method']] ?? 9]
                    <=> [strlen($b['path']), $rank[$b['method']] ?? 9]);
                $resources[$name] = $operations;
            }
            $out[$scope] = $resources;
        }

        return $out;
    }

    /**
     * An icon per resource, from the panel's own set.
     *
     * Named rather than derived: a wrong icon is worse than none, and there is
     * no rule that turns "watchdog-rules" into a shield. Anything unmapped gets
     * a neutral one instead of an empty gap in the rail.
     */
    private const ICONS = [
        'Activity' => 'clock',
        'Backups' => 'archive',
        'Channels' => 'bell',
        'Database Hosts' => 'database',
        'Databases' => 'database',
        'Files' => 'folder',
        'Games' => 'controller',
        'Locations' => 'map',
        'Me' => 'key',
        'Mods' => 'puzzle',
        'Mounts' => 'folder',
        'Network' => 'network',
        'Nodes' => 'cloud',
        'Players' => 'user-group',
        'Resources' => 'cpu',
        'Schedules' => 'clock',
        'Servers' => 'server',
        'Subusers' => 'users',
        'Templates' => 'cube',
        'Users' => 'users',
        'Watchdog Rules' => 'shield',
        'Webhooks' => 'bolt',
        'Worlds' => 'globe',
    ];

    public static function iconFor(string $resource): string
    {
        return self::ICONS[$resource] ?? 'link';
    }

    /**
     * A copy-paste payload, from the required fields only.
     *
     * Required only, because the point of an example is to be the smallest
     * thing that works. A body carrying every optional field is a body somebody
     * has to edit down before their first call.
     *
     * The values are placeholders shaped like the type, never plausible-looking
     * real data: "1" for an id invites somebody to run it and wonder why it
     * failed against their install.
     */
    private static function example(array $schema): string
    {
        $payload = [];

        foreach ($schema['required'] ?? [] as $field) {
            $property = $schema['properties'][$field] ?? [];
            $type = (array) ($property['type'] ?? 'string');

            $payload[$field] = match (true) {
                isset($property['enum']) => $property['enum'][0],
                in_array('integer', $type, true), in_array('number', $type, true) => 0,
                in_array('boolean', $type, true) => true,
                in_array('array', $type, true) => [],
                default => '<'.$field.'>',
            };
        }

        return $payload === [] ? '{}' : json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /** Path segments that are things done TO a server, not things it has. */
    private const VERBS = [
        'power', 'command', 'reinstall', 'suspend', 'unsuspend', 'transfer',
        'build', 'startup', 'rename', 'settings', 'status-page',
    ];

    /**
     * Which heading an endpoint belongs under.
     *
     * A client route is nearly always /servers/{server}/something, and filing
     * all forty of those under "servers" would be one enormous heading and no
     * navigation at all. So the segment after the server is what counts, and
     * only the handful that act on the server itself stay under Servers.
     */
    private function resourceOf(string $path): string
    {
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            fn ($s) => $s !== '' && $s !== 'api' && $s !== 'application' && $s !== 'client' && ! str_starts_with($s, '{'),
        ));

        if ($segments === []) {
            return 'Other';
        }

        $name = $segments[0];

        if (count($segments) > 1 && $name === 'servers') {
            // /servers/{server}/backups is a sub-resource and deserves its own
            // heading. /servers/{server}/reinstall is a VERB, and filing it
            // under "Reinstall" makes a heading with one thing beneath it and
            // hides it from anybody scanning for Servers.
            $name = in_array($segments[1], self::VERBS, true) ? 'servers' : $segments[1];
        }

        return Str::headline(str_replace('-', ' ', $name));
    }
}
