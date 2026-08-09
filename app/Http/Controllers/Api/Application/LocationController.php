<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\Request;

/**
 * Locations, read only for now. Provisioning reads these to decide where to put a
 * server; nothing in a billing flow creates them, and an API that can create a
 * node is an API that can be used to point a customer's server at a machine
 * somebody else controls.
 */
class LocationController extends Controller
{
    public function index(Request $request)
    {
        $rows = Location::query()
            ->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return ApiResource::list($rows, LocationResource::class);
    }

    public function show(Location $location)
    {
        return (new LocationResource($location))->toArray(request());
    }
}
