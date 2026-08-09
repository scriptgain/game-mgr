<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;

/**
 * The Pterodactyl response envelope, in one place.
 *
 * Every object is wrapped as {"object": "server", "attributes": {...}} and every
 * list as {"object": "list", "data": [...], "meta": {"pagination": {...}}}.
 *
 * This is not a taste in JSON shapes. The docs page and three code comments
 * already promise "two scopes, matching Pterodactyl so existing tooling ports
 * across", and the practical value is that a WHMCS module written for
 * Pterodactyl is a starting point rather than a blank file. An envelope spelled
 * out at forty call sites is an envelope that is spelled differently at two of
 * them, so it is spelled here.
 */
abstract class ApiResource extends JsonResource
{
    /** What this thing calls itself in the envelope, e.g. "server". */
    abstract public function objectName(): string;

    /** The fields, without the envelope. */
    abstract public function fields(): array;

    /**
     * Relationships, included only when the caller asked for them with
     * ?include=allocations,node. Loading them unconditionally turns a list of
     * two hundred servers into a list of two hundred servers and their nodes,
     * owners and templates, which is the query nobody wanted.
     */
    public function relations(): array
    {
        return [];
    }

    public function toArray($request): array
    {
        $payload = [
            'object' => $this->objectName(),
            'attributes' => $this->fields(),
        ];

        $relations = array_filter($this->relations(), fn ($v) => $v !== null);
        if ($relations !== []) {
            $payload['attributes']['relationships'] = $relations;
        }

        return $payload;
    }

    /** Did the caller ask for this relationship by name? */
    protected function wants(string $relation): bool
    {
        $include = request()->query('include', '');

        return in_array($relation, array_filter(explode(',', (string) $include)), true);
    }

    /**
     * Wrap anything as a list, with pagination meta when there is any.
     *
     * Static and shared so a controller returning a collection does not have to
     * remember the shape.
     */
    public static function list(iterable $items, string $resource): array
    {
        $data = $resource::collection($items);

        $out = [
            'object' => 'list',
            'data' => $data->resolve(),
        ];

        if ($items instanceof AbstractPaginator) {
            $out['meta'] = [
                'pagination' => [
                    'total' => $items->total(),
                    'count' => $items->count(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'total_pages' => $items->lastPage(),
                ],
            ];
        }

        return $out;
    }
}
