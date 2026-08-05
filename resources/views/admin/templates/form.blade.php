<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="cube" />

    @php
        $imageLines = collect($template->docker_images ?? [])->map(fn ($image, $label) => $label.' = '.$image)->implode("\n");
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
