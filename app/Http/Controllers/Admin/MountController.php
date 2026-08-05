<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Template;
use Illuminate\Http\Request;

/**
 * Mounts expose a host path inside a server. Allowlisted by node and template,
 * because "mount any path you like" on a multi-tenant box is a root exploit
 * with extra steps.
 */
class MountController extends Controller
{
    public function index()
    {
        return view('admin.mounts.index', [
            'title' => 'Mounts',
            'mounts' => Mount::withCount(['nodes', 'templates'])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.mounts.form', [
            'title' => 'New Mount',
            'mount' => new Mount(['read_only' => true]),
            'nodes' => Node::orderBy('name')->get(),
            'templates' => Template::with('game')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $mount = Mount::create($data['attributes']);
        $mount->nodes()->sync($data['nodes']);
        $mount->templates()->sync($data['templates']);

        return redirect()->route('admin.mounts.index')->with('status', 'Mount created.');
    }

    public function edit(Mount $mount)
    {
        return view('admin.mounts.form', [
            'title' => 'Edit '.$mount->name,
            'mount' => $mount->load('nodes', 'templates'),
            'nodes' => Node::orderBy('name')->get(),
            'templates' => Template::with('game')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Mount $mount)
    {
        $data = $this->validated($request);
        $mount->update($data['attributes']);
        $mount->nodes()->sync($data['nodes']);
        $mount->templates()->sync($data['templates']);

        return redirect()->route('admin.mounts.index')->with('status', 'Mount updated.');
    }

    public function destroy(Mount $mount)
    {
        $mount->delete();

        return back()->with('status', 'Mount deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:255'],
            'read_only' => ['nullable', 'boolean'],
            'user_mountable' => ['nullable', 'boolean'],
            'nodes' => ['nullable', 'array'],
            'nodes.*' => ['exists:nodes,id'],
            'templates' => ['nullable', 'array'],
            'templates.*' => ['exists:templates,id'],
        ]);

        return [
            'attributes' => [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'source' => $data['source'],
                'target' => $data['target'],
                'read_only' => (bool) ($data['read_only'] ?? false),
                'user_mountable' => (bool) ($data['user_mountable'] ?? false),
            ],
            'nodes' => $data['nodes'] ?? [],
            'templates' => $data['templates'] ?? [],
        ];
    }
}
