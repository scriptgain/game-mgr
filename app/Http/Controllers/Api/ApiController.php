<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Shared behaviour for the application scope.
 *
 * Every resource here answers the same three questions the same way: how many
 * per page, in what order, and in what envelope. Spelled once so twenty
 * controllers cannot answer them twenty slightly different ways.
 */
abstract class ApiController extends Controller
{
    /** The most any caller can ask for in one page, however large per_page is. */
    protected const MAX_PER_PAGE = 200;

    /**
     * A relation is not a Builder even though it forwards to one, so both are
     * accepted: half these endpoints page over $node->allocations() and the
     * other half over Model::query().
     */
    protected function paginate(Request $request, Builder|Relation $query, string $resource): array
    {
        $rows = $query->paginate(
            min(max((int) $request->query('per_page', 50), 1), self::MAX_PER_PAGE)
        )->withQueryString();

        return ApiResource::list($rows, $resource);
    }

    /** One record, in the envelope. */
    protected function one($model, string $resource): array
    {
        return (new $resource($model))->toArray(request());
    }

    /**
     * A 204, for actions whose answer is "it happened".
     *
     * Returning the changed record instead would be a lie for anything the node
     * does asynchronously: the row says installing and the caller would read
     * that as done.
     */
    protected function done()
    {
        return response()->json(null, 204);
    }
}
