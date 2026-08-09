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
        // Everything is validated before anything is written, so a bad variable
        // cannot leave a half-built template behind.
        $ports = $this->validatedPorts($request);
        $variables = $this->validatedVariables($request);

        $template = Template::create($this->validated($request) + $this->validatedDocuments($request));
        $this->syncPorts($template, $ports);
        $this->syncVariables($template, $variables, $request->boolean('variables_submitted'));

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
        $variables = $this->validatedVariables($request);

        $template->update($this->validated($request, $template) + $this->validatedDocuments($request));
        $this->syncPorts($template, $ports);
        $this->syncVariables($template, $variables, $request->boolean('variables_submitted'));

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
    // ------------------------------------------------------------ variables

    /**
     * Variables as the form now posts them.
     *
     * They used to be their own screen with their own add and delete actions,
     * which meant authoring a template was two screens and a round trip per
     * variable. That screen still exists and still works; this is the same data
     * arriving as part of the form it belongs to.
     */
    private function validatedVariables(Request $request): array
    {
        // The form always sends this marker, so an empty set really means the
        // last variable was deleted rather than that nothing was submitted.
        // Without it, removing every row in the browser would silently leave
        // them all in place, which looks exactly like a save that did not work.
        if (! $request->boolean('variables_submitted')) {
            return [];
        }

        $data = $request->validate([
            'variables' => ['nullable', 'array', 'max:60'],
            'variables.*.name' => ['required', 'string', 'max:120'],
            'variables.*.env_variable' => ['required', 'string', 'max:80', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'variables.*.description' => ['nullable', 'string', 'max:500'],
            'variables.*.default_value' => ['nullable', 'string', 'max:500'],
            'variables.*.rules' => ['required', 'string', 'max:255'],
            'variables.*.user_viewable' => ['nullable', 'boolean'],
            'variables.*.user_editable' => ['nullable', 'boolean'],
        ], [
            'variables.*.env_variable.regex' => 'An environment variable must be upper case and start with a letter, for example MINECRAFT_VERSION.',
            'variables.*.name.required' => 'Every variable needs a name.',
            'variables.*.rules.required' => 'Every variable needs validation rules. Use nullable|string if it does not matter.',
        ]);

        $rows = array_values($data['variables'] ?? []);

        // Two variables writing the same environment variable is not a
        // preference, it is one of them silently winning at install time.
        $seen = [];
        foreach ($rows as $row) {
            $env = $row['env_variable'];
            if (isset($seen[$env])) {
                throw ValidationException::withMessages([
                    'variables' => 'Two variables both write '.$env.'. Each one has to be different, or only one of them reaches the server.',
                ]);
            }
            $seen[$env] = true;
        }

        return $rows;
    }

    private function syncVariables(Template $template, array $rows, bool $submitted = true): void
    {
        if (! $submitted) {
            return;
        }

        $sort = 0;
        $keep = [];
        foreach ($rows as $row) {
            $variable = $template->variables()->updateOrCreate(
                ['env_variable' => $row['env_variable']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'default_value' => $row['default_value'] ?? null,
                    'rules' => $row['rules'],
                    'user_viewable' => (bool) ($row['user_viewable'] ?? false),
                    'user_editable' => (bool) ($row['user_editable'] ?? false),
                    'sort' => $sort++,
                ],
            );
            $keep[] = $variable->id;
        }

        // A row removed in the browser is a row deleted here. Servers already
        // built keep the value they were given: their variables are their own
        // rows, not references to these.
        $template->variables()->whereNotIn('id', $keep)->delete();
    }

    // --------------------------------------------------------------- config

    /**
     * The config schema, as files each holding settings.
     *
     * This is what gives a customer a Config tab instead of a text editor, and
     * until now it could only be set by importing somebody else's egg.
     */
    private function validatedSchema(Request $request): ?array
    {
        if (! $request->boolean('schema_submitted')) {
            return null;
        }

        $data = $request->validate([
            'schema' => ['nullable', 'array', 'max:20'],
            'schema.*.file' => ['required', 'string', 'max:255'],
            'schema.*.format' => ['required', 'in:properties,yaml,json,ini'],
            'schema.*.label' => ['nullable', 'string', 'max:120'],
            'schema.*.settings' => ['nullable', 'array', 'max:200'],
            'schema.*.settings.*.key' => ['required', 'string', 'max:160'],
            'schema.*.settings.*.name' => ['nullable', 'string', 'max:160'],
            'schema.*.settings.*.default' => ['nullable', 'string', 'max:500'],
            'schema.*.settings.*.rules' => ['nullable', 'string', 'max:255'],
            'schema.*.settings.*.section' => ['nullable', 'string', 'max:120'],
            'schema.*.settings.*.user_viewable' => ['nullable', 'boolean'],
            'schema.*.settings.*.user_editable' => ['nullable', 'boolean'],
        ], [
            'schema.*.file.required' => 'Every config file needs a path, for example server.properties.',
            'schema.*.settings.*.key.required' => 'Every setting needs the key it writes in the file.',
        ]);

        $out = [];
        foreach (array_values($data['schema'] ?? []) as $file) {
            $settings = [];
            foreach (array_values($file['settings'] ?? []) as $setting) {
                $settings[] = array_filter([
                    'key' => $setting['key'],
                    'name' => $setting['name'] ?? null,
                    'default' => $setting['default'] ?? null,
                    'rules' => $setting['rules'] ?: 'nullable|string',
                    'section' => $setting['section'] ?? null,
                ], fn ($v) => $v !== null && $v !== '') + [
                    // Kept outside array_filter: false is a meaningful answer
                    // here and filtering would turn "hidden" into "visible".
                    'user_viewable' => (bool) ($setting['user_viewable'] ?? false),
                    'user_editable' => (bool) ($setting['user_editable'] ?? false),
                ];
            }

            $out[] = array_filter([
                'file' => ltrim($file['file'], '/'),
                'format' => $file['format'],
                'label' => $file['label'] ?? null,
            ], fn ($v) => $v !== null && $v !== '') + ['settings' => $settings];
        }

        return $out;
    }

    /**
     * The three documents this form can now author, as columns to write.
     *
     * Each returns null when the form did not carry it at all, and a null is
     * dropped rather than written: an API caller that posts only a name must
     * not blank a template's whole config schema as a side effect.
     */
    private function validatedDocuments(Request $request): array
    {
        return array_filter([
            'config_schema' => $this->validatedSchema($request),
            'config_files' => $this->validatedJson($request, 'config_files_raw', 'The config files document'),
            'mcjars' => $this->validatedJson($request, 'mcjars_raw', 'The MCJars document'),
        ], fn ($v) => $v !== null);
    }

    /**
     * A JSON document typed into a textarea.
     *
     * Machine formats, both of them: config_files arrives from an imported egg
     * and mcjars from the MCJars API. Nobody writes them by hand, so the only
     * thing worth checking is that what was pasted parses.
     */
    private function validatedJson(Request $request, string $field, string $label): ?array
    {
        if (! $request->has($field)) {
            return null;
        }

        $raw = trim((string) $request->input($field));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                $field => $label.' is not valid JSON: '.json_last_error_msg(),
            ]);
        }
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => $label.' has to be a JSON object or array.',
            ]);
        }

        return $decoded;
    }
}
