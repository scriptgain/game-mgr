@php
    /*
     * Which step owns which field, so a validation failure reopens the step
     * that actually failed instead of dumping somebody back at the beginning.
     *
     * Prefix matching, not equality: the repeaters produce error keys like
     * ports.2.value and variables.0.env_variable, and an exact comparison would
     * match none of them. That is the whole reason this map exists, so getting
     * it wrong here would be a quiet no-op.
     */
    $stepOf = [
        'name' => 1, 'game_id' => 1, 'author' => 1, 'description' => 1,
        'runtime' => 2, 'docker_images_raw' => 2, 'data_path' => 2,
        'script_container' => 2, 'script_entry' => 2,
        'steam_app_id' => 2, 'steam_branch' => 2, 'steam_anonymous' => 2, 'lgsm_shortname' => 2,
        'startup' => 3, 'stop_command' => 3, 'done_marker' => 3,
        'update_command' => 3, 'script_install' => 3,
        'ports' => 4, 'rcon_supported' => 4, 'rcon_protocol' => 4, 'query_protocol' => 4,
        'variables' => 5,
        'schema' => 6, 'config_files_raw' => 6, 'mcjars_raw' => 6,
    ];

    // $errors is shared by middleware on a real request; guarded so the view
    // also renders from a console or a test that builds it directly.
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag;

    $firstBadStep = 1;
    foreach ($errorBag->keys() as $key) {
        $root = explode('.', $key)[0];
        if (isset($stepOf[$root])) {
            $firstBadStep = $stepOf[$root];
            break;
        }
    }

    $steps = [
        ['label' => 'Identity', 'hint' => 'Name and game'],
        ['label' => 'Runtime', 'hint' => 'How it installs'],
        ['label' => 'Startup', 'hint' => 'How it runs'],
        ['label' => 'Ports', 'hint' => 'How it is reached'],
        ['label' => 'Variables', 'hint' => 'What can be set'],
        ['label' => 'Config', 'hint' => 'Files customers edit'],
        ['label' => 'Review', 'hint' => 'Check and save'],
    ];

    $imageLines = collect($template->docker_images ?? [])->map(fn ($image, $label) => $label.' = '.$image)->implode("\n");

    // The port set, in the shape the browser edits it: one value per row, read
    // as a port number or as an offset depending on the source.
    $portRows = $template->ports->map(fn ($p) => [
        'role' => $p->role,
        'label' => $p->label,
        'protocol' => $p->protocol,
        'source' => $p->source,
        'value' => $p->source === 'fixed' ? (int) $p->port : (int) $p->port_offset,
        'required' => (bool) $p->required,
    ])->values()->all();

    if (old('ports')) {
        $portRows = array_values(old('ports'));
    }

    // A rejected POST brings "required" back as the string "0", which is truthy
    // in JavaScript and would flip every toggle on.
    $portRows = collect($portRows)->map(function (array $row) {
        $row['required'] = filter_var($row['required'] ?? true, FILTER_VALIDATE_BOOL);
        $row['value'] = (int) ($row['value'] ?? 0);

        return $row;
    })->values()->all();

    foreach ($portRows as $index => $row) {
        $portRows[$index]['uid'] = $index;
    }

    // Variables, moved in from the screen they used to live on.
    $variableRows = old('variables') ? array_values(old('variables')) : $template->variables
        ->map(fn ($v) => [
            'name' => $v->name,
            'env_variable' => $v->env_variable,
            'description' => $v->description,
            'default_value' => $v->default_value,
            'rules' => $v->rules,
            'user_viewable' => (bool) $v->user_viewable,
            'user_editable' => (bool) $v->user_editable,
        ])->values()->all();

    $variableRows = collect($variableRows)->map(function (array $row, int $i) {
        $row['user_viewable'] = filter_var($row['user_viewable'] ?? true, FILTER_VALIDATE_BOOL);
        $row['user_editable'] = filter_var($row['user_editable'] ?? true, FILTER_VALIDATE_BOOL);
        $row['uid'] = $i;

        return $row;
    })->values()->all();

    // The config schema is files, each holding settings. Two levels of repeater.
    $schemaRows = old('schema') ? array_values(old('schema')) : collect($template->config_schema ?? [])
        ->map(fn ($file) => [
            'file' => $file['file'] ?? '',
            'format' => $file['format'] ?? 'properties',
            'label' => $file['label'] ?? '',
            'settings' => collect($file['settings'] ?? [])->map(fn ($s) => [
                'key' => $s['key'] ?? '',
                'name' => $s['name'] ?? '',
                'default' => $s['default'] ?? '',
                'rules' => $s['rules'] ?? 'nullable|string',
                'section' => $s['section'] ?? '',
                'user_viewable' => (bool) ($s['user_viewable'] ?? true),
                'user_editable' => (bool) ($s['user_editable'] ?? true),
            ])->values()->all(),
        ])->values()->all();

    $schemaRows = collect($schemaRows)->map(function (array $file, int $i) {
        $file['uid'] = $i;
        $file['settings'] = collect($file['settings'] ?? [])->map(function (array $s, int $j) {
            $s['user_viewable'] = filter_var($s['user_viewable'] ?? true, FILTER_VALIDATE_BOOL);
            $s['user_editable'] = filter_var($s['user_editable'] ?? true, FILTER_VALIDATE_BOOL);
            $s['uid'] = $j;

            return $s;
        })->values()->all();

        return $file;
    })->values()->all();

    $textarea = 'block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-xs text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="cube"
                   :subtitle="$template->exists ? 'Every tab is editable. Changes apply to servers built from this template afterwards, not to ones already running.' : null" />

    @if ($errors->any())
        <div class="mb-6">
            <x-alert type="danger" title="Something Below Needs Fixing">
                The tab it belongs to has been opened for you.
            </x-alert>
        </div>
    @endif

    {{-- novalidate on purpose: six of the seven steps are hidden at any moment,
         and the browser cannot scroll to, or complain about, a control it
         cannot see. The wizard checks the open step itself and the server
         validates everything regardless. --}}
    <form method="POST" novalidate
          action="{{ $template->exists ? route('admin.templates.update', $template) : route('admin.templates.store') }}"
          x-data="{ runtime: @js(old('runtime', $template->runtime ?? 'docker')) }">
        @csrf
        @if ($template->exists)@method('PUT')@endif
        {{-- Tells the controller these sections were on the form at all, so
             removing the last row really removes it instead of looking like a
             save that quietly did nothing. --}}
        <input type="hidden" name="variables_submitted" value="1">
        <input type="hidden" name="schema_submitted" value="1">

        <x-form-wizard :steps="$steps" :editing="$template->exists" :start="$firstBadStep">
            <x-slot:save>
                <x-button type="submit" icon="check" class="w-full justify-center">Save Template</x-button>
            </x-slot:save>

            {{-- ------------------------------------------------------- 1 --}}
            <x-form-wizard.step :n="1" first title="Identity" icon="cube"
                                subtitle="What this template is called and which game it belongs to.">
                <div class="space-y-4">
                    <x-field label="Name" required :error="$errors->first('name')">
                        <x-input name="name" value="{{ old('name', $template->name) }}" required placeholder="Paper" />
                    </x-field>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Game" required :error="$errors->first('game_id')">
                            <x-select name="game_id" required>
                                @foreach ($games as $game)
                                    <option value="{{ $game->id }}" @selected(old('game_id', $template->game_id) == $game->id)>{{ $game->name }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                        <x-field label="Author" :error="$errors->first('author')">
                            <x-input name="author" value="{{ old('author', $template->author) }}" />
                        </x-field>
                    </div>
                    <x-field label="Description" :error="$errors->first('description')">
                        <x-input name="description" value="{{ old('description', $template->description) }}" />
                    </x-field>
                </div>
            </x-form-wizard.step>

            {{-- ------------------------------------------------------- 2 --}}
            <x-form-wizard.step :n="2" title="Runtime" icon="play"
                                subtitle="How this template is installed and supervised. Most panels only offer the first one.">
                <div class="space-y-4">
                    <x-field label="How It Is Installed And Supervised" required :error="$errors->first('runtime')">
                        <x-select name="runtime" x-model="runtime">
                            @foreach (\App\Models\Template::RUNTIMES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <div x-show="runtime === 'docker'" x-cloak class="space-y-4">
                        <x-field label="Docker Images" hint="One per line, as Label = image:tag. The first is the default."
                                 :error="$errors->first('docker_images_raw')">
                            <textarea name="docker_images_raw" rows="3" placeholder="Java 21 = ghcr.io/gamemgr/java:21"
                                      class="{{ $textarea }}">{{ old('docker_images_raw', $imageLines) }}</textarea>
                        </x-field>
                        <x-field label="Data Directory Inside The Container" :error="$errors->first('data_path')"
                                 hint="Where the server keeps its files. The node's storage is mounted over this path, so getting it wrong means the world is written into the container and lost on the next restart. /home/container suits most community images; itzg/minecraft-server uses /data.">
                            <x-input name="data_path" value="{{ old('data_path', $template->data_path ?: '/home/container') }}" class="font-mono text-xs" />
                        </x-field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Install Container" :error="$errors->first('script_container')">
                                <x-input name="script_container" value="{{ old('script_container', $template->script_container) }}" class="font-mono text-xs" />
                            </x-field>
                            <x-field label="Install Entrypoint" :error="$errors->first('script_entry')">
                                <x-input name="script_entry" value="{{ old('script_entry', $template->script_entry ?: 'bash') }}" class="font-mono text-xs" />
                            </x-field>
                        </div>
                    </div>

                    <div x-show="runtime === 'steamcmd'" x-cloak class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Steam App ID" :error="$errors->first('steam_app_id')">
                            <x-input type="number" name="steam_app_id" value="{{ old('steam_app_id', $template->steam_app_id) }}" placeholder="258550" />
                        </x-field>
                        <x-field label="Branch" hint="Leave blank for the public branch." :error="$errors->first('steam_branch')">
                            <x-input name="steam_branch" value="{{ old('steam_branch', $template->steam_branch) }}" />
                        </x-field>
                        <div class="flex items-end pb-2">
                            <x-toggle name="steam_anonymous" :checked="(bool) old('steam_anonymous', $template->steam_anonymous ?? true)" label="Anonymous Login" />
                        </div>
                    </div>

                    <div x-show="runtime === 'linuxgsm'" x-cloak>
                        <x-field label="LinuxGSM Shortname" hint="What LinuxGSM calls the game, for example vhserver or mcserver."
                                 :error="$errors->first('lgsm_shortname')">
                            <x-input name="lgsm_shortname" value="{{ old('lgsm_shortname', $template->lgsm_shortname) }}" class="font-mono" placeholder="vhserver" />
                        </x-field>
                    </div>
                </div>
            </x-form-wizard.step>

            {{-- ------------------------------------------------------- 3 --}}
            <x-form-wizard.step :n="3" title="Startup" icon="bolt"
                                subtitle="What the daemon runs, and how it knows the server is up.">
                <div class="space-y-4">
                    {{-- The hint deliberately spells the delimiters out rather than showing
                         them literally: a doubled brace inside a Blade attribute is parsed
                         as an echo and produces invalid PHP. --}}
                    <x-field label="Startup Command" :error="$errors->first('startup')"
                             hint="Reference a variable by wrapping its name in double curly braces, for example SERVER_MEMORY.">
                        <textarea name="startup" rows="3" class="{{ $textarea }}">{{ old('startup', $template->startup) }}</textarea>
                    </x-field>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Stop Command" :error="$errors->first('stop_command')">
                            <x-input name="stop_command" value="{{ old('stop_command', $template->stopCommand()) }}" class="font-mono text-xs" />
                        </x-field>
                        <x-field label="Ready When Output Contains" hint="How the daemon knows booting finished."
                                 :error="$errors->first('done_marker')">
                            <x-input name="done_marker" value="{{ old('done_marker', $template->doneMarker()) }}" class="font-mono text-xs" />
                        </x-field>
                    </div>
                    <x-field label="Update Command" :error="$errors->first('update_command')">
                        <x-input name="update_command" value="{{ old('update_command', $template->update_command) }}" class="font-mono text-xs" />
                    </x-field>
                    <x-field label="Install Script" :error="$errors->first('script_install')">
                        <textarea name="script_install" rows="10" spellcheck="false" class="{{ $textarea }}">{{ old('script_install', $template->script_install) }}</textarea>
                    </x-field>
                </div>
            </x-form-wizard.step>

            {{-- ------------------------------------------------------- 4 --}}
            {{-- Ports keeps its own Alpine scope rather than the form's, so the
                 repeating rows can own an id counter without the runtime switch
                 above having to know about it. Every control uses x-bind:name
                 rather than :name, because a colon prefix on a Blade component
                 is a PHP expression and would be evaluated server side instead
                 of reaching Alpine. --}}
            <div x-data="{
                    rows: @js($portRows),
                    next: {{ count($portRows) }},
                    add() {
                        this.rows.push({ uid: this.next++, role: '', label: '', protocol: 'both', source: 'fixed', value: 27015, required: true });
                    },
                    remove(index) { this.rows.splice(index, 1); },
                    gamePort() {
                        const game = this.rows.find(r => r.role === 'game');
                        return game ? Number(game.value) : 0;
                    },
                    resolved(row) {
                        return row.source === 'offset' ? this.gamePort() + Number(row.value) : Number(row.value);
                    },
                 }">
                <x-form-wizard.step :n="4" title="Ports And Queries" icon="network"
                                    subtitle="Every listener this game needs, and how the panel counts players. A server reserves all of them together on one address, or it is not created at all.">
                    <x-slot:actions>
                        <x-button type="button" variant="secondary" size="sm" icon="plus" x-on:click="add()">Add A Port</x-button>
                    </x-slot:actions>

                    <div class="space-y-4">
                        @error('ports')<x-alert type="danger">{{ $message }}</x-alert>@enderror
                        @foreach ($errors->get('ports.*') as $messages)
                            <x-alert type="danger">{{ $messages[0] }}</x-alert>
                        @endforeach

                        <p class="text-sm text-slate-500" x-show="rows.length === 0">
                            No ports declared. A server built from this template gets whatever port happens to be free,
                            which is how a Palworld server ends up on Valheim's 2456 and nobody can reach it.
                            Add a row called <span class="font-mono">game</span> to fix that.
                        </p>

                        <template x-for="(row, i) in rows" x-bind:key="row.uid">
                            <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200">
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <x-field label="Key" hint="game, query, rcon, sftp, or a word of your own.">
                                        <x-input x-bind:name="'ports[' + i + '][role]'" x-model="row.role"
                                                 class="font-mono text-xs" placeholder="game" />
                                    </x-field>
                                    <x-field label="Name">
                                        <x-input x-bind:name="'ports[' + i + '][label]'" x-model="row.label" placeholder="Game Port" />
                                    </x-field>
                                    <x-field label="Protocol">
                                        <x-select x-bind:name="'ports[' + i + '][protocol]'" x-model="row.protocol">
                                            @foreach (\App\Models\TemplatePort::PROTOCOLS as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </x-select>
                                    </x-field>
                                    <x-field label="How It Is Worked Out">
                                        <x-select x-bind:name="row.role === 'game' ? '' : 'ports[' + i + '][source]'" x-model="row.source"
                                                  x-bind:disabled="row.role === 'game'">
                                            <option value="fixed">A Fixed Port</option>
                                            <option value="offset">Offset From The Game Port</option>
                                        </x-select>
                                        {{-- A disabled control is not submitted, and the game row's
                                             source select is always disabled because a game port is
                                             always fixed. Without this the field simply went missing
                                             and every save of a template with a game port failed on
                                             "the ports.0.source field is required", which is a
                                             validation error nobody could act on because the control
                                             it names cannot be changed. --}}
                                        <template x-if="row.role === 'game'">
                                            <input type="hidden" x-bind:name="'ports[' + i + '][source]'" x-bind:value="row.source || 'fixed'">
                                        </template>
                                    </x-field>
                                    <x-field label="Number">
                                        <x-input type="number" x-bind:name="'ports[' + i + '][value]'" x-model="row.value" />
                                    </x-field>
                                    <div class="flex flex-wrap items-end gap-4 pb-2">
                                        <label class="flex cursor-pointer select-none items-center gap-2">
                                            <input type="hidden" x-bind:name="'ports[' + i + '][required]'" x-bind:value="row.required ? 1 : 0">
                                            <button type="button" role="switch" x-bind:aria-checked="row.required.toString()"
                                                    x-on:click="row.required = ! row.required"
                                                    x-bind:class="row.required ? 'bg-brand-600' : 'bg-slate-300'"
                                                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
                                                <span x-bind:class="row.required ? 'translate-x-6' : 'translate-x-1'"
                                                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                                            </button>
                                            <span class="text-sm font-medium text-slate-900">Required</span>
                                        </label>
                                        <button type="button" x-on:click="remove(i)" x-show="row.role !== 'game'"
                                                class="rounded-lg px-2 py-1 text-sm font-medium text-rose-600 ring-1 ring-transparent transition hover:bg-rose-50 hover:ring-rose-200">
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <p class="mt-3 text-sm text-slate-500">
                                    <span x-show="row.role === 'game'">
                                        The port players connect to. On an address with nothing else on it this is what the
                                        server gets, every time, with no exceptions.
                                    </span>
                                    <span x-show="row.role !== 'game' && row.source === 'offset'">
                                        Resolves to <span class="font-mono text-slate-900" x-text="resolved(row)"></span>,
                                        and follows the game port if it ever changes.
                                    </span>
                                    <span x-show="row.role !== 'game' && row.source === 'fixed'">
                                        Always <span class="font-mono text-slate-900" x-text="row.value"></span>,
                                        whatever the game port is.
                                    </span>
                                    <span x-show="! row.required">This one is optional: a server is still created if it cannot be had.</span>
                                </p>
                            </div>
                        </template>

                        <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200 space-y-4">
                            <p class="text-sm font-medium text-slate-900">Players And Queries</p>
                            <x-toggle name="rcon_supported" :checked="(bool) old('rcon_supported', $template->rcon_supported)"
                                      label="Supports RCON" description="Lets the panel kick, ban and run commands without the console." />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="RCON Protocol" :error="$errors->first('rcon_protocol')">
                                    <x-select name="rcon_protocol">
                                        <option value="">None</option>
                                        @foreach (['source' => 'Source', 'minecraft' => 'Minecraft', 'battleye' => 'BattlEye'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('rcon_protocol', $template->rcon_protocol) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                                <x-field label="Query Protocol" hint="Used for the player count when RCON is not available."
                                         :error="$errors->first('query_protocol')">
                                    <x-select name="query_protocol">
                                        <option value="">None</option>
                                        @foreach (['a2s' => 'Steam A2S', 'minecraft' => 'Minecraft', 'gamespy' => 'GameSpy'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('query_protocol', $template->query_protocol) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                            </div>
                        </div>
                    </div>
                </x-form-wizard.step>
            </div>

            {{-- ------------------------------------------------------- 5 --}}
            <div x-data="{
                    rows: @js($variableRows),
                    next: {{ count($variableRows) }},
                    add() {
                        this.rows.push({ uid: this.next++, name: '', env_variable: '', description: '', default_value: '', rules: 'nullable|string|max:255', user_viewable: true, user_editable: true });
                    },
                    remove(index) { this.rows.splice(index, 1); },
                 }">
                <x-form-wizard.step :n="5" title="Variables" icon="sliders"
                                    subtitle="What a server built from this template can be given. These used to live on their own page.">
                    <x-slot:actions>
                        <x-button type="button" variant="secondary" size="sm" icon="plus" x-on:click="add()">Add A Variable</x-button>
                    </x-slot:actions>

                    <div class="space-y-4">
                        @error('variables')<x-alert type="danger">{{ $message }}</x-alert>@enderror
                        @foreach ($errors->get('variables.*') as $messages)
                            <x-alert type="danger">{{ $messages[0] }}</x-alert>
                        @endforeach

                        <p class="text-sm text-slate-500" x-show="rows.length === 0">
                            None yet. A variable is anything the startup command needs to be told, such as a version,
                            a world seed or a maximum player count.
                        </p>

                        <template x-for="(row, i) in rows" x-bind:key="row.uid">
                            <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field label="Name">
                                        <x-input x-bind:name="'variables[' + i + '][name]'" x-model="row.name" placeholder="Minecraft Version" />
                                    </x-field>
                                    <x-field label="Environment Variable" hint="Upper case, letters, numbers and underscores.">
                                        <x-input x-bind:name="'variables[' + i + '][env_variable]'" x-model="row.env_variable"
                                                 class="font-mono text-xs" placeholder="MINECRAFT_VERSION" />
                                    </x-field>
                                    <x-field label="Description">
                                        <x-input x-bind:name="'variables[' + i + '][description]'" x-model="row.description" />
                                    </x-field>
                                    <x-field label="Default">
                                        <x-input x-bind:name="'variables[' + i + '][default_value]'" x-model="row.default_value" />
                                    </x-field>
                                    <x-field label="Validation Rules" hint="Laravel rules, for example required|string|max:20.">
                                        <x-input x-bind:name="'variables[' + i + '][rules]'" x-model="row.rules" class="font-mono text-xs" />
                                    </x-field>
                                    <div class="flex flex-wrap items-end gap-4 pb-2">
                                        <label class="flex cursor-pointer select-none items-center gap-2">
                                            <input type="hidden" x-bind:name="'variables[' + i + '][user_viewable]'" x-bind:value="row.user_viewable ? 1 : 0">
                                            <button type="button" role="switch" x-bind:aria-checked="row.user_viewable.toString()"
                                                    x-on:click="row.user_viewable = ! row.user_viewable"
                                                    x-bind:class="row.user_viewable ? 'bg-brand-600' : 'bg-slate-300'"
                                                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors">
                                                <span x-bind:class="row.user_viewable ? 'translate-x-6' : 'translate-x-1'"
                                                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                                            </button>
                                            <span class="text-sm font-medium text-slate-900">Clients See It</span>
                                        </label>
                                        <label class="flex cursor-pointer select-none items-center gap-2">
                                            <input type="hidden" x-bind:name="'variables[' + i + '][user_editable]'" x-bind:value="row.user_editable ? 1 : 0">
                                            <button type="button" role="switch" x-bind:aria-checked="row.user_editable.toString()"
                                                    x-on:click="row.user_editable = ! row.user_editable"
                                                    x-bind:class="row.user_editable ? 'bg-brand-600' : 'bg-slate-300'"
                                                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors">
                                                <span x-bind:class="row.user_editable ? 'translate-x-6' : 'translate-x-1'"
                                                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                                            </button>
                                            <span class="text-sm font-medium text-slate-900">Clients Change It</span>
                                        </label>
                                        <button type="button" x-on:click="remove(i)"
                                                class="rounded-lg px-2 py-1 text-sm font-medium text-rose-600 ring-1 ring-transparent transition hover:bg-rose-50 hover:ring-rose-200">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-slate-500" x-show="! row.user_viewable">
                                    Hidden from clients entirely. Use this for anything holding a licence key or a token.
                                </p>
                            </div>
                        </template>
                    </div>
                </x-form-wizard.step>
            </div>

            {{-- ------------------------------------------------------- 6 --}}
            <div x-data="{
                    files: @js($schemaRows),
                    nextFile: {{ count($schemaRows) }},
                    addFile() {
                        this.files.push({ uid: this.nextFile++, file: '', format: 'properties', label: '', settings: [] });
                    },
                    removeFile(i) { this.files.splice(i, 1); },
                    addSetting(file) {
                        file.settings.push({ uid: Date.now() + file.settings.length, key: '', name: '', default: '', rules: 'nullable|string', section: '', user_viewable: true, user_editable: true });
                    },
                    removeSetting(file, j) { file.settings.splice(j, 1); },
                    jsonError(value) {
                        if (! value || ! value.trim()) return '';
                        try { JSON.parse(value); return ''; } catch (e) { return e.message; }
                    },
                    filesJson: @js(old('config_files_raw', $template->config_files ? json_encode($template->config_files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '')),
                    mcjarsJson: @js(old('mcjars_raw', $template->mcjars ? json_encode($template->mcjars, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '')),
                 }">
                <x-form-wizard.step :n="6" title="Config" icon="settings"
                                    subtitle="The files a customer may edit from the Config tab, without giving them the file manager.">
                    <x-slot:actions>
                        <x-button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addFile()">Add A File</x-button>
                    </x-slot:actions>

                    <div class="space-y-4">
                        @error('schema')<x-alert type="danger">{{ $message }}</x-alert>@enderror
                        @foreach ($errors->get('schema.*') as $messages)
                            <x-alert type="danger">{{ $messages[0] }}</x-alert>
                        @endforeach

                        <p class="text-sm text-slate-500" x-show="files.length === 0">
                            Nothing declared, so servers built from this template have no Config tab and their owners
                            edit raw files instead. Declaring a file here is what turns a properties file into a form.
                        </p>

                        <template x-for="(file, i) in files" x-bind:key="file.uid">
                            <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200">
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <x-field label="File">
                                        <x-input x-bind:name="'schema[' + i + '][file]'" x-model="file.file"
                                                 class="font-mono text-xs" placeholder="server.properties" />
                                    </x-field>
                                    <x-field label="Format">
                                        <x-select x-bind:name="'schema[' + i + '][format]'" x-model="file.format">
                                            @foreach (['properties' => 'Java Properties', 'yaml' => 'YAML', 'json' => 'JSON', 'ini' => 'INI'] as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </x-select>
                                    </x-field>
                                    <x-field label="Shown As">
                                        <x-input x-bind:name="'schema[' + i + '][label]'" x-model="file.label" placeholder="Server Settings" />
                                    </x-field>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <template x-for="(setting, j) in file.settings" x-bind:key="setting.uid">
                                        <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                <x-field label="Key">
                                                    <x-input x-bind:name="'schema[' + i + '][settings][' + j + '][key]'" x-model="setting.key"
                                                             class="font-mono text-xs" placeholder="difficulty" />
                                                </x-field>
                                                <x-field label="Shown As">
                                                    <x-input x-bind:name="'schema[' + i + '][settings][' + j + '][name]'" x-model="setting.name" placeholder="Difficulty" />
                                                </x-field>
                                                <x-field label="Default">
                                                    <x-input x-bind:name="'schema[' + i + '][settings][' + j + '][default]'" x-model="setting.default" />
                                                </x-field>
                                                <x-field label="Section" hint="Groups fields on the tab.">
                                                    <x-input x-bind:name="'schema[' + i + '][settings][' + j + '][section]'" x-model="setting.section" placeholder="World" />
                                                </x-field>
                                                <x-field label="Rules" class="sm:col-span-2">
                                                    <x-input x-bind:name="'schema[' + i + '][settings][' + j + '][rules]'" x-model="setting.rules" class="font-mono text-xs" />
                                                </x-field>
                                                <div class="flex flex-wrap items-end gap-3 pb-2 sm:col-span-2">
                                                    <label class="flex cursor-pointer select-none items-center gap-2">
                                                        <input type="hidden" x-bind:name="'schema[' + i + '][settings][' + j + '][user_viewable]'" x-bind:value="setting.user_viewable ? 1 : 0">
                                                        <button type="button" role="switch" x-on:click="setting.user_viewable = ! setting.user_viewable"
                                                                x-bind:class="setting.user_viewable ? 'bg-brand-600' : 'bg-slate-300'"
                                                                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors">
                                                            <span x-bind:class="setting.user_viewable ? 'translate-x-5' : 'translate-x-1'"
                                                                  class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"></span>
                                                        </button>
                                                        <span class="text-xs font-medium text-slate-700">Visible</span>
                                                    </label>
                                                    <label class="flex cursor-pointer select-none items-center gap-2">
                                                        <input type="hidden" x-bind:name="'schema[' + i + '][settings][' + j + '][user_editable]'" x-bind:value="setting.user_editable ? 1 : 0">
                                                        <button type="button" role="switch" x-on:click="setting.user_editable = ! setting.user_editable"
                                                                x-bind:class="setting.user_editable ? 'bg-brand-600' : 'bg-slate-300'"
                                                                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors">
                                                            <span x-bind:class="setting.user_editable ? 'translate-x-5' : 'translate-x-1'"
                                                                  class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"></span>
                                                        </button>
                                                        <span class="text-xs font-medium text-slate-700">Editable</span>
                                                    </label>
                                                    <button type="button" x-on:click="removeSetting(file, j)"
                                                            class="rounded-lg px-2 py-1 text-xs font-medium text-rose-600 ring-1 ring-transparent transition hover:bg-rose-50 hover:ring-rose-200">
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                            <p class="mt-2 text-xs text-slate-500" x-show="setting.user_viewable && ! setting.user_editable">
                                                Shown to the customer and not changeable by them.
                                            </p>
                                            <p class="mt-2 text-xs text-slate-500" x-show="! setting.user_viewable">
                                                Administrators only. The customer never sees this one.
                                            </p>
                                        </div>
                                    </template>

                                    <div class="flex items-center gap-2">
                                        <x-button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addSetting(file)">Add A Setting</x-button>
                                        <button type="button" x-on:click="removeFile(i)"
                                                class="rounded-lg px-2 py-1 text-sm font-medium text-rose-600 ring-1 ring-transparent transition hover:bg-rose-50 hover:ring-rose-200">
                                            Remove This File
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- These two are machine formats. They arrive from an
                             imported definition and from the MCJars API, nobody writes
                             them by hand, and a structured editor for a shape
                             nobody authors buys nothing. They are checked for
                             being parseable, which is the failure that matters. --}}
                        <div class="grid gap-4 lg:grid-cols-2">
                            <x-field label="Config Files (Pterodactyl Format)" :error="$errors->first('config_files_raw')"
                                     hint="JSON, as an imported definition supplies it. Leave empty unless you have one.">
                                <textarea name="config_files_raw" rows="6" spellcheck="false" x-model="filesJson" class="{{ $textarea }}"></textarea>
                                <p class="mt-1 text-xs text-rose-600" x-show="jsonError(filesJson)" x-cloak
                                   x-text="'Not valid JSON: ' + jsonError(filesJson)"></p>
                            </x-field>
                            <x-field label="MCJars Document" :error="$errors->first('mcjars_raw')"
                                     hint="Carrying this is the only thing that gives a template a Minecraft version picker.">
                                <textarea name="mcjars_raw" rows="6" spellcheck="false" x-model="mcjarsJson" class="{{ $textarea }}"></textarea>
                                <p class="mt-1 text-xs text-rose-600" x-show="jsonError(mcjarsJson)" x-cloak
                                   x-text="'Not valid JSON: ' + jsonError(mcjarsJson)"></p>
                            </x-field>
                        </div>
                    </div>
                </x-form-wizard.step>
            </div>

            {{-- ------------------------------------------------------- 7 --}}
            <x-form-wizard.step :n="7" last title="Review" icon="check-circle"
                                :submit="$template->exists ? 'Save Template' : 'Create Template'"
                                subtitle="What is about to be saved.">
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach ([
                        [1, 'Identity', 'document.querySelector(\'[name=name]\')?.value || \'Not named yet\''],
                        [2, 'Runtime', 'runtime'],
                        [3, 'Startup', 'document.querySelector(\'[name=startup]\')?.value?.slice(0, 60) || \'Nothing set\''],
                        [4, 'Ports', '(document.querySelectorAll(\'[name^="ports"][name$="[role]"]\').length || 0) + \' declared\''],
                        [5, 'Variables', '(document.querySelectorAll(\'[name^="variables"][name$="[env_variable]"]\').length || 0) + \' declared\''],
                        [6, 'Config', '(document.querySelectorAll(\'[name^="schema"][name$="[file]"]\').length || 0) + \' files\''],
                    ] as [$n, $label, $expression])
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="font-medium text-slate-500">{{ $label }}</dt>
                            <dd class="flex min-w-0 items-center gap-3">
                                <span class="truncate text-slate-900" x-text="{{ $expression }}"></span>
                                <button type="button" @click="go({{ $n }})"
                                        class="shrink-0 rounded-lg border border-transparent px-2 py-1 text-xs font-medium text-brand-700 transition hover:border-brand-200 hover:bg-brand-50">
                                    Change
                                </button>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-form-wizard.step>
        </x-form-wizard>
    </form>
</x-layouts.app>
