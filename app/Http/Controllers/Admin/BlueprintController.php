<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Template;
use Illuminate\Http\Request;

/**
 * A blueprint is a saved server shape: template plus limits plus feature caps.
 * Creating the hundredth Minecraft Starter should be one click, not fifteen
 * fields retyped identically.
 */
class BlueprintController extends Controller
{
    public function index()
    {
        return view('admin.blueprints.index', [
            'title' => 'Blueprints',
            'blueprints' => Blueprint::with('template.game', 'creator')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.blueprints.form', [
            'title' => 'New Blueprint',
            'blueprint' => new Blueprint(['limits' => ['memory' => 2048, 'disk' => 10240, 'cpu' => 200, 'swap' => 0, 'io' => 500], 'feature_limits' => ['databases' => 1, 'allocations' => 2, 'backups' => 5]]),
            'templates' => Template::with('game')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Blueprint::create($this->validated($request) + ['created_by' => auth()->id()]);

        return redirect()->route('admin.blueprints.index')->with('status', 'Blueprint created.');
    }

    public function edit(Blueprint $blueprint)
    {
        return view('admin.blueprints.form', [
            'title' => 'Edit '.$blueprint->name,
            'blueprint' => $blueprint,
            'templates' => Template::with('game')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Blueprint $blueprint)
    {
        $blueprint->update($this->validated($request));

        return redirect()->route('admin.blueprints.index')->with('status', 'Blueprint updated.');
    }

    public function destroy(Blueprint $blueprint)
    {
        $blueprint->delete();

        return back()->with('status', 'Blueprint deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'template_id' => ['required', 'exists:templates,id'],
            'memory' => ['required', 'integer', 'min:0'],
            'disk' => ['required', 'integer', 'min:0'],
            'cpu' => ['required', 'integer', 'min:0'],
            'swap' => ['required', 'integer', 'min:-1'],
            'io' => ['required', 'integer', 'between:10,1000'],
            'databases' => ['required', 'integer', 'between:0,50'],
            'allocations' => ['required', 'integer', 'between:0,50'],
            'backups' => ['required', 'integer', 'between:0,200'],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'template_id' => $data['template_id'],
            'limits' => [
                'memory' => $data['memory'], 'disk' => $data['disk'], 'cpu' => $data['cpu'],
                'swap' => $data['swap'], 'io' => $data['io'],
            ],
            'feature_limits' => [
                'databases' => $data['databases'], 'allocations' => $data['allocations'], 'backups' => $data['backups'],
            ],
        ];
    }
}
