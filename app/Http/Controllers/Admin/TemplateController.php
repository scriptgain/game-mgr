<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $ports = $this->validatedPorts($request);
        $template = Template::create($this->validated($request));
        $this->syncPorts($template, $ports);

        return redirect()->route('admin.templates.show', $template)->with('status', 'Template created.');
    }

    public function show(Template $template)
    {
        $template->load('game', 'variables', 'ports');

        return view('admin.templates.show', [
            'title' => $template->name,
            'template' => $template,
            'servers' => $template->servers()->with('owner', 'node')->orderBy('name')->get(),
            // Derived here rather than in the view: the show page leads on a
            // summary strip and these are the three facts that need assembling
            // from more than one column.
            'rconSummary' => $template->rcon_supported
                ? $this->protocolLabel($template->rcon_protocol).$this->offsetLabel($template->rcon_port_offset)
                : 'Not Supported',
            'querySummary' => $template->query_protocol
                ? $this->protocolLabel($template->query_protocol).$this->offsetLabel($template->query_port_offset)
                : 'Not Supported',
            'configFilesJson' => $template->config_files
                ? json_encode($template->config_files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : null,
        ]);
    }

    /** Protocol keys are stored lowercase; these are how people write them. */
    private function protocolLabel(?string $protocol): string
    {
        return [
            'source' => 'Source',
            'minecraft' => 'Minecraft',
            'battleye' => 'BattlEye',
            'a2s' => 'A2S',
            'gamespy' => 'GameSpy',
        ][$protocol] ?? ($protocol ? ucfirst($protocol) : 'Supported');
    }

    /** A port offset of zero means "the game port itself", which is worth saying. */
    private function offsetLabel(?int $offset): string
    {
        return $offset ? ', Port +'.$offset : ', Game Port';
    }

    public function edit(Template $template)
    {
        return view('admin.templates.form', [
            'title' => 'Edit '.$template->name,
            'template' => $template->load('ports'),
            'games' => Game::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Template $template)
    {
        $ports = $this->validatedPorts($request);
        $template->update($this->validated($request, $template));
        $this->syncPorts($template, $ports);

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

    // ---------------------------------------------------------------- ports

    /**
     * The port set as the form posts it: one row per listener.
     *
     * Declaring ports is the point of this screen. The rival panel's egg format
     * cannot say what a game listens on at all, which is why a server there
     * gets whatever number was free and everybody downstream, the firewall
     * included, has to be told after the fact.
     */
    private function validatedPorts(Request $request): array
    {
        $data = $request->validate([
            'ports' => ['nullable', 'array', 'max:20'],
            'ports.*.role' => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/'],
            'ports.*.label' => ['required', 'string', 'max:60'],
            'ports.*.protocol' => ['required', 'in:tcp,udp,both'],
            'ports.*.source' => ['required', 'in:fixed,offset'],
            'ports.*.value' => ['required', 'integer', 'between:-65535,65535'],
            'ports.*.required' => ['nullable', 'boolean'],
        ], [], [
            'ports.*.role' => 'port key',
            'ports.*.label' => 'port name',
            'ports.*.value' => 'port number',
        ]);

        $rows = array_values($data['ports'] ?? []);
        if (! $rows) {
            return [];
        }

        $seen = [];
        $game = null;
        $out = [];

        foreach ($rows as $index => $row) {
            $role = mb_strtolower(trim((string) $row['role']));
            if (isset($seen[$role])) {
                throw ValidationException::withMessages([
                    'ports.'.$index.'.role' => 'Two ports cannot both be called "'.$role.'".',
                ]);
            }
            $seen[$role] = true;

            $fixed = $row['source'] === 'fixed';
            $value = (int) $row['value'];

            if ($fixed && ($value < 1 || $value > 65535)) {
                throw ValidationException::withMessages([
                    'ports.'.$index.'.value' => 'A fixed port has to be between 1 and 65535.',
                ]);
            }

            // The game port is what everything else is measured from, so it
            // cannot be measured from itself.
            if ($role === 'game') {
                if (! $fixed) {
                    throw ValidationException::withMessages([
                        'ports.'.$index.'.source' => 'The game port is the number everything else is offset from, so it has to be a fixed port.',
                    ]);
                }
                $game = $value;
            }

            $out[] = [
                'role' => $role,
                'label' => trim((string) $row['label']),
                'protocol' => $row['protocol'],
                'source' => $fixed ? 'fixed' : 'offset',
                'port' => $fixed ? $value : null,
                'port_offset' => $fixed ? null : $value,
                'required' => (bool) ($row['required'] ?? false),
            ];
        }

        if ($game === null) {
            throw ValidationException::withMessages([
                'ports.0.role' => 'A port set needs a row called "game". That is the port players connect to, and every offset is measured from it.',
            ]);
        }

        return $out;
    }

    /**
     * Replace the template's port set with what was posted.
     *
     * Rows are matched on role rather than id so a set can be reordered in the
     * browser without every row being deleted and recreated, which would take
     * their ids with them.
     */
    private function syncPorts(Template $template, array $rows): void
    {
        if (! $rows) {
            // An empty submission on a template that has a set is a deliberate
            // clear, but the form always posts the rows it rendered, so this is
            // only reached by an API caller that sent none. Leaving the set
            // alone is the safer reading of "said nothing".
            return;
        }

        $sort = 0;
        $roles = [];
        foreach ($rows as $row) {
            $template->ports()->updateOrCreate(
                ['role' => $row['role']],
                $row + ['sort' => $sort++],
            );
            $roles[] = $row['role'];
        }

        $template->ports()->whereNotIn('role', $roles)->delete();
        $template->load('ports');
        $template->syncPortColumns();
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

        // Both this and done_marker are nullable rules, so a request that omits
        // them has no key at all rather than an empty one. Reading them directly
        // 500d every save that did not come from the browser form.
        $data['config_stop'] = ['value' => ($data['stop_command'] ?? null) ?: 'stop'];
        unset($data['stop_command']);

        // Preserve everything else already in config_startup; only the done
        // marker is editable from this form.
        $startup = $template?->config_startup ?? [];
        $startup['done'] = ($data['done_marker'] ?? null) ?: null;
        $data['config_startup'] = array_filter($startup, fn ($v) => $v !== null);
        unset($data['done_marker']);

        foreach (['steam_anonymous', 'rcon_supported'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $data;
    }
}
