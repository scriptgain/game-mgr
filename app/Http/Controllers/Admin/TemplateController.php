<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Http\Request;

/**
 * Templates: how a game gets installed, started and stopped.
 *
 * The rival panel's equivalent can only ever describe a Docker container. A
 * GameMGR template also says whether it wants SteamCMD or LinuxGSM, which is
 * what lets one panel cover all three worlds.
 */
class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = Template::with('game')->withCount('servers');

        if ($runtime = $request->query('runtime')) {
            $query->where('runtime', $runtime);
        }
        if ($gameId = $request->query('game')) {
            $query->where('game_id', $gameId);
        }

        return view('admin.templates.index', [
            'title' => 'Templates',
            'templates' => $query->orderBy('game_id')->orderBy('name')->get(),
            'games' => Game::orderBy('name')->get(),
            'filters' => ['runtime' => $runtime, 'game' => $gameId],
        ]);
    }

    public function create()
    {
        return view('admin.templates.form', [
            'title' => 'New Template',
            'template' => new Template(['runtime' => 'docker', 'script_entry' => 'bash']),
            'games' => Game::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $template = Template::create($this->validated($request));

        return redirect()->route('admin.templates.show', $template)->with('status', 'Template created.');
    }

    public function show(Template $template)
    {
        return view('admin.templates.show', [
            'title' => $template->name,
            'template' => $template->load('game', 'variables'),
            'servers' => $template->servers()->with('owner', 'node')->orderBy('name')->get(),
        ]);
    }

    public function edit(Template $template)
    {
        return view('admin.templates.form', [
            'title' => 'Edit '.$template->name,
            'template' => $template,
            'games' => Game::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Template $template)
    {
        $template->update($this->validated($request, $template));

        return redirect()->route('admin.templates.show', $template)->with('status', 'Template updated.');
    }

    public function destroy(Template $template)
    {
        if ($template->servers()->exists()) {
            return back()->with('error', 'Servers are still built from this template. Delete them first.');
        }

        $template->delete();

        return redirect()->route('admin.templates.index')->with('status', 'Template deleted.');
    }

    // ------------------------------------------------------------ variables

    public function variables(Template $template)
    {
        return view('admin.templates.variables', [
            'title' => $template->name.' Variables',
            'template' => $template->load('variables'),
        ]);
    }

    public function storeVariable(Request $request, Template $template)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'env_variable' => ['required', 'string', 'max:80', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'default_value' => ['nullable', 'string', 'max:500'],
            'rules' => ['required', 'string', 'max:255'],
            'user_viewable' => ['nullable', 'boolean'],
            'user_editable' => ['nullable', 'boolean'],
        ]);

        $data['user_viewable'] = (bool) ($data['user_viewable'] ?? false);
        $data['user_editable'] = (bool) ($data['user_editable'] ?? false);
        $data['sort'] = (int) $template->variables()->max('sort') + 1;

        $template->variables()->create($data);

        return back()->with('status', 'Variable added.');
    }

    public function destroyVariable(Template $template, TemplateVariable $variable)
    {
        abort_unless($variable->template_id === $template->id, 404);

        $variable->delete();

        return back()->with('status', 'Variable removed.');
    }

    // ------------------------------------------------------------ internals

    private function validated(Request $request, ?Template $template = null): array
    {
        $data = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'name' => ['required', 'string', 'max:120'],
            'author' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'runtime' => ['required', 'in:docker,steamcmd,linuxgsm'],
            'startup' => ['nullable', 'string', 'max:2000'],
            'update_command' => ['nullable', 'string', 'max:255'],
            'docker_images_raw' => ['nullable', 'string', 'max:2000'],
            'script_container' => ['nullable', 'string', 'max:255'],
            'script_entry' => ['nullable', 'string', 'max:64'],
            'data_path' => ['nullable', 'string', 'max:255'],
            'script_install' => ['nullable', 'string'],
            'steam_app_id' => ['nullable', 'integer', 'min:1'],
            'steam_anonymous' => ['nullable', 'boolean'],
            'steam_branch' => ['nullable', 'string', 'max:64'],
            'lgsm_shortname' => ['nullable', 'string', 'max:64'],
            'stop_command' => ['nullable', 'string', 'max:120'],
            'done_marker' => ['nullable', 'string', 'max:200'],
            'rcon_supported' => ['nullable', 'boolean'],
            'rcon_protocol' => ['nullable', 'in:source,minecraft,battleye'],
            'query_protocol' => ['nullable', 'in:a2s,minecraft,gamespy'],
        ]);

        // Images are edited as "Label = image:tag" lines, which is far easier to
        // read and diff than the JSON blob Pterodactyl makes you type.
        $images = [];
        foreach (preg_split('/\R/', (string) ($data['docker_images_raw'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, '=')) {
                [$label, $image] = array_map('trim', explode('=', $line, 2));
            } else {
                $label = 'Default';
                $image = $line;
            }
            if ($image !== '') {
                $images[$label] = $image;
            }
        }
        unset($data['docker_images_raw']);
        $data['docker_images'] = $images ?: null;

        $data['config_stop'] = ['value' => $data['stop_command'] ?: 'stop'];
        unset($data['stop_command']);

        // Preserve everything else already in config_startup; only the done
        // marker is editable from this form.
        $startup = $template?->config_startup ?? [];
        $startup['done'] = $data['done_marker'] ?: null;
        $data['config_startup'] = array_filter($startup, fn ($v) => $v !== null);
        unset($data['done_marker']);

        foreach (['steam_anonymous', 'rcon_supported'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $data;
    }
}
