<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="cube" />

    @php
        $imageLines = collect($template->docker_images ?? [])->map(fn ($image, $label) => $label.' = '.$image)->implode("\n");

        // The port set, in the shape the browser edits it: one value per row,
        // read as a port number or as an offset depending on the source.
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

        // A rejected POST brings "required" back as the string "0", which is
        // truthy in JavaScript and would flip every toggle on.
        $portRows = collect($portRows)->map(function (array $row) {
            $row['required'] = filter_var($row['required'] ?? true, FILTER_VALIDATE_BOOL);
            $row['value'] = (int) ($row['value'] ?? 0);

            return $row;
        })->values()->all();

        foreach ($portRows as $index => $row) {
            $portRows[$index]['uid'] = $index;
        }
    @endphp

    <form method="POST" action="{{ $template->exists ? route('admin.templates.update', $template) : route('admin.templates.store') }}"
          x-data="{ runtime: @js(old('runtime', $template->runtime ?? 'docker')) }">
        @csrf
        @if ($template->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Template">
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
                            <x-field label="Author">
                                <x-input name="author" value="{{ old('author', $template->author) }}" />
                            </x-field>
                        </div>
                        <x-field label="Description">
                            <x-input name="description" value="{{ old('description', $template->description) }}" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Runtime" subtitle="How this template is installed and supervised. Most panels only offer the first one.">
                    <div class="space-y-4">
                        <x-field label="How It Is Installed And Supervised" required>
                            <x-select name="runtime" x-model="runtime">
                                @foreach (\App\Models\Template::RUNTIMES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>

                        <div x-show="runtime === 'docker'" class="space-y-4">
                            <x-field label="Docker Images" hint="One per line, as Label = image:tag. The first is the default.">
                                <textarea name="docker_images_raw" rows="3" placeholder="Java 21 = ghcr.io/gamemgr/java:21"
                                          class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-xs text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('docker_images_raw', $imageLines) }}</textarea>
                            </x-field>
                            <x-field label="Data Directory Inside The Container" required
                                     hint="Where the server keeps its files. The node's storage is mounted over this path, so getting it wrong means the world is written into the container and lost on the next restart. /home/container suits most community images; itzg/minecraft-server uses /data.">
                                <x-input name="data_path" value="{{ old('data_path', $template->data_path ?: '/home/container') }}" class="font-mono text-xs" />
                            </x-field>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Install Container">
                                    <x-input name="script_container" value="{{ old('script_container', $template->script_container) }}" class="font-mono text-xs" />
                                </x-field>
                                <x-field label="Install Entrypoint">
                                    <x-input name="script_entry" value="{{ old('script_entry', $template->script_entry ?: 'bash') }}" class="font-mono text-xs" />
                                </x-field>
                            </div>
                        </div>

                        <div x-show="runtime === 'steamcmd'" x-cloak class="grid gap-4 sm:grid-cols-3">
                            <x-field label="Steam App ID" required>
                                <x-input type="number" name="steam_app_id" value="{{ old('steam_app_id', $template->steam_app_id) }}" placeholder="258550" />
                            </x-field>
                            <x-field label="Branch" hint="Leave blank for the public branch.">
                                <x-input name="steam_branch" value="{{ old('steam_branch', $template->steam_branch) }}" />
                            </x-field>
                            <div class="flex items-end pb-2">
                                <x-toggle name="steam_anonymous" :checked="(bool) old('steam_anonymous', $template->steam_anonymous ?? true)" label="Anonymous Login" />
                            </div>
                        </div>

                        <div x-show="runtime === 'linuxgsm'" x-cloak>
                            <x-field label="LinuxGSM Shortname" hint="What LinuxGSM calls the game, for example vhserver or mcserver.">
                                <x-input name="lgsm_shortname" value="{{ old('lgsm_shortname', $template->lgsm_shortname) }}" class="font-mono" placeholder="vhserver" />
                            </x-field>
                        </div>
                    </div>
                </x-card>

                <x-card title="Startup">
                    <div class="space-y-4">
                        {{-- The hint deliberately spells the delimiters out rather than showing
                             them literally: a doubled brace inside a Blade attribute is parsed
                             as an echo and produces invalid PHP. --}}
                        <x-field label="Startup Command" hint="Reference a variable by wrapping its name in double curly braces, for example SERVER_MEMORY.">
                            <textarea name="startup" rows="3"
                                      class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-xs text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('startup', $template->startup) }}</textarea>
                        </x-field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Stop Command">
                                <x-input name="stop_command" value="{{ old('stop_command', $template->stopCommand()) }}" class="font-mono text-xs" />
                            </x-field>
                            <x-field label="Ready When Output Contains" hint="How the daemon knows booting finished.">
                                <x-input name="done_marker" value="{{ old('done_marker', $template->doneMarker()) }}" class="font-mono text-xs" />
                            </x-field>
                        </div>
                        <x-field label="Update Command">
                            <x-input name="update_command" value="{{ old('update_command', $template->update_command) }}" class="font-mono text-xs" />
                        </x-field>
                        <x-field label="Install Script">
                            <textarea name="script_install" rows="10" spellcheck="false"
                                      class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-xs text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('script_install', $template->script_install) }}</textarea>
                        </x-field>
                    </div>
                </x-card>

                {{-- The port set. Its own Alpine scope rather than the form's, so
                     the repeating rows can own an id counter without the runtime
                     switch above having to know about it. Every control uses
                     x-bind:name rather than :name, because a colon prefix on a
                     Blade component is a PHP expression and would be evaluated
                     server side instead of reaching Alpine. --}}
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
                    <x-card title="Ports"
                            subtitle="Every listener this game needs. A server reserves all of them together on one address, or it is not created at all.">
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
                                            <x-select x-bind:name="'ports[' + i + '][source]'" x-model="row.source"
                                                      x-bind:disabled="row.role === 'game'">
                                                <option value="fixed">A Fixed Port</option>
                                                <option value="offset">Offset From The Game Port</option>
                                            </x-select>
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
                        </div>

                        <x-slot:footer>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                                <span>Canonical set:</span>
                                <span class="font-mono text-slate-900" x-show="rows.length"
                                      x-text="rows.map(r => resolved(r) + '/' + r.protocol).join('  ')"></span>
                                <span x-show="! rows.length">Nothing declared.</span>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>
            </div>

            <div class="space-y-6">
                <x-card title="Players And Queries" subtitle="What makes the Players tab and the status page possible at all.">
                    <div class="space-y-4">
                        <x-toggle name="rcon_supported" :checked="(bool) old('rcon_supported', $template->rcon_supported)"
                                  label="Supports RCON" description="Lets the panel kick, ban and run commands without the console." />
                        <x-field label="RCON Protocol">
                            <x-select name="rcon_protocol">
                                <option value="">None</option>
                                @foreach (['source' => 'Source', 'minecraft' => 'Minecraft', 'battleye' => 'BattlEye'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('rcon_protocol', $template->rcon_protocol) === $value)>{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                        <x-field label="Query Protocol" hint="Used for the player count when RCON is not available.">
                            <x-select name="query_protocol">
                                <option value="">None</option>
                                @foreach (['a2s' => 'Steam A2S', 'minecraft' => 'Minecraft', 'gamespy' => 'GameSpy'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('query_protocol', $template->query_protocol) === $value)>{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>
                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2">
                            <x-button href="{{ route('admin.templates.index') }}" variant="secondary" size="sm">Cancel</x-button>
                            <x-button type="submit" size="sm">{{ $template->exists ? 'Save Template' : 'Create Template' }}</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
