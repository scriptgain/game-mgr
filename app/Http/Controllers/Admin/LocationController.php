<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

/**
 * Locations group nodes. "EU", "Phoenix", "home lab". They exist so auto
 * placement, capacity views and the server list can all filter on something
 * an operator actually thinks in.
 */
class LocationController extends Controller
{
    public function index()
    {
        return view('admin.locations.index', [
            'title' => 'Locations',
            'locations' => Location::withCount('nodes')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.locations.form', ['title' => 'New Location', 'location' => new Location]);
    }

    public function store(Request $request)
    {
        Location::create($this->validated($request));

        return redirect()->route('admin.locations.index')->with('status', 'Location created.');
    }

    public function edit(Location $location)
    {
        return view('admin.locations.form', ['title' => 'Edit Location', 'location' => $location]);
    }

    public function update(Request $request, Location $location)
    {
        $location->update($this->validated($request, $location));

        return redirect()->route('admin.locations.index')->with('status', 'Location updated.');
    }

    public function destroy(Location $location)
    {
        // Deleting a location would cascade to its nodes and then to every
        // server on them. Refuse rather than quietly destroy a region.
        if ($location->nodes()->exists()) {
            return back()->with('error', 'That location still has nodes. Move or delete them first.');
        }

        $location->delete();

        return back()->with('status', 'Location deleted.');
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'short' => ['required', 'string', 'max:32', 'unique:locations,short'.($location ? ','.$location->id : '')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'flag' => ['nullable', 'string', 'max:8'],
        ]);
    }
}
