<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\NodeResource;
use App\Models\Node;
use Illuminate\Http\Request;

/**
 * Nodes, read only for now. Provisioning reads these to decide where to put a
 * server; nothing in a billing flow creates them, and an API that can create a
 * node is an API that can be used to point a customer's server at a machine
 * somebody else controls.
 */
class NodeController extends Controller
{
    public function index(Request $request)
    {
        $rows = Node::query()->with('location')
            ->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return ApiResource::list($rows, NodeResource::class);
    }

    public function show(Node $node)
    {
        return (new NodeResource($node))->toArray(request());
    }
}
