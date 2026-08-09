<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\LocationResource;
use App\Models\AuditLog;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Location over the API. The rules match the admin screen's, because an API that
 * validates more loosely than the form is an API that writes rows the panel
 * would have refused.
 */
class LocationApiController extends ApiController
{
    public function index(Request $request)
    {
        $query = Location::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('id');

        return $this->paginate($request, $query, LocationResource::class);
    }

    public function show(Location $location)
    {
        return $this->one($location, LocationResource::class);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $location = Location::create($data);

        AuditLog::record('locations.create', 'Created "'.($location->name ?? '').'" over the API', $location);

        return response()->json($this->one($location, LocationResource::class), 201);
    }

    public function update(Request $request, Location $location)
    {
        $data = $this->validated($request, $location);
        $location->update($data);

        AuditLog::record('locations.update', 'Updated "'.($location->name ?? '').'" over the API', $location);

        return $this->one($location->fresh(), LocationResource::class);
    }

    public function destroy(Location $location)
    {
        $name = $location->name ?? '';
        $location->delete();

        AuditLog::record('locations.delete', 'Deleted "'.$name.'" over the API');

        return $this->done();
    }

    private function validated(Request $request, ?Location $model): array
    {
        $data = $request->validate([
            'short' => ['required', 'string', 'max:32', Rule::unique('locations', 'short')->ignore($model?->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'flag' => ['nullable', 'string', 'max:8'],
        ]);

        return $data;
    }
}
