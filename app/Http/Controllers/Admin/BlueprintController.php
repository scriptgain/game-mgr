<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Template;
use Illuminate\Database\Eloquent\Collection;
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
        $blueprint = new Blueprint(['limits' => ['memory' => 2048, 'disk' => 10240, 'cpu' => 200, 'swap' => 0, 'io' => 500], 'feature_limits' => ['databases' => 1, 'allocations' => 2, 'backups' => 5]]);

        return view('admin.blueprints.form', $this->formData($blueprint, 'New Blueprint'));
    }

    public function store(Request $request)
    {
        Blueprint::create($this->validated($request) + ['created_by' => auth()->id()]);

        return redirect()->route('admin.blueprints.index')->with('status', 'Blueprint created.');
    }

    public function edit(Blueprint $blueprint)
    {
        return view('admin.blueprints.form', $this->formData($blueprint, 'Edit '.$blueprint->name));
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

    /**
     * Everything the shared create/edit view needs. The limits and feature caps
     * are unpacked here rather than in Blade so the view stays markup.
     */
    private function formData(Blueprint $blueprint, string $title): array
    {
        $templates = Template::with('game')->orderBy('name')->get();
        $limits = $blueprint->limits ?? [];
        $features = $blueprint->feature_limits ?? [];

        return [
            'title' => $title,
            'blueprint' => $blueprint,
            'templates' => $templates,
            'limits' => $limits,
            'features' => $features,
            'activeTab' => $this->tabWithFirstError(),
            'designer' => $this->designerData($blueprint, $templates, $limits, $features),
        ];
    }

    /**
     * Which pane the form should open on.
     *
     * A rejected POST that reopens on the wrong tab shows the operator a form
     * with nothing visibly wrong and a button that appears to do nothing, so
     * the pane holding the first rejected field wins. Error keys come back in
     * rule order, which is the order the fields are asked for.
     */
    private function tabWithFirstError(): string
    {
        $panes = [
            'memory' => 'resources', 'disk' => 'resources', 'cpu' => 'resources',
            'swap' => 'resources', 'io' => 'resources',
            'databases' => 'caps', 'allocations' => 'caps', 'backups' => 'caps',
        ];

        $errors = session('errors');

        foreach ($errors ? $errors->getBag('default')->keys() : [] as $field) {
            if (isset($panes[$field])) {
                return $panes[$field];
            }
        }

        return 'resources';
    }

    /**
     * The JSON island behind the live preview.
     *
     * The form draws the very card an operator will later pick from, so it
     * needs the same facts the picking screen has: the templates, and every
     * other blueprint to rank this draft against. `values` mirrors what the
     * inputs already render, so the preview and the form can never disagree
     * about what is about to be posted.
     */
    private function designerData(Blueprint $blueprint, Collection $templates, array $limits, array $features): array
    {
        return [
            'templates' => $templates->map(fn (Template $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'game' => $template->game?->name,
                'runtime' => $template->runtime,
                'runtime_label' => $template->runtimeLabel(),
            ])->values()->all(),

            'siblings' => Blueprint::query()
                ->where('id', '!=', $blueprint->getKey() ?? 0)
                ->orderBy('name')->get()
                ->map(fn (Blueprint $other) => [
                    'id' => $other->id,
                    'name' => $other->name,
                    'template_id' => $other->template_id,
                    'memory' => (int) ($other->limits['memory'] ?? 0),
                    'disk' => (int) ($other->limits['disk'] ?? 0),
                    'cpu' => (int) ($other->limits['cpu'] ?? 0),
                ])->values()->all(),

            'values' => [
                'name' => (string) old('name', $blueprint->name),
                'description' => (string) old('description', $blueprint->description),
                // Nothing is selected on a fresh create, so the browser lands on
                // the first option: seed the preview with the same one.
                'template_id' => (string) (old('template_id', $blueprint->template_id) ?: $templates->first()?->id),
                'memory' => (int) old('memory', $limits['memory'] ?? 2048),
                'disk' => (int) old('disk', $limits['disk'] ?? 10240),
                'cpu' => (int) old('cpu', $limits['cpu'] ?? 200),
                'swap' => (int) old('swap', $limits['swap'] ?? 0),
                'io' => (int) old('io', $limits['io'] ?? 500),
                'databases' => (int) old('databases', $features['databases'] ?? 1),
                'allocations' => (int) old('allocations', $features['allocations'] ?? 2),
                'backups' => (int) old('backups', $features['backups'] ?? 5),
            ],
        ];
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

        // Cast on the way in: a request field is a string, and JSON that stores
        // "8192" where the seeders store 8192 makes every strict comparison
        // downstream depend on where the row came from.
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'template_id' => $data['template_id'],
            'limits' => [
                'memory' => (int) $data['memory'], 'disk' => (int) $data['disk'], 'cpu' => (int) $data['cpu'],
                'swap' => (int) $data['swap'], 'io' => (int) $data['io'],
            ],
            'feature_limits' => [
                'databases' => (int) $data['databases'], 'allocations' => (int) $data['allocations'], 'backups' => (int) $data['backups'],
            ],
        ];
    }
}
