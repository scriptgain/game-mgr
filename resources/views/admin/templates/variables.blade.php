<x-layouts.app :title="$title">
    <x-page-header :title="$template->name.' Variables'" icon="bolt"
                   subtitle="What a client can change on the Startup tab, and what stays yours.">
        <x-slot:actions>
            <x-button href="{{ route('admin.templates.show', $template) }}" variant="secondary" size="sm">Back To Template</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Variables" icon="sliders" flush>
                @if ($template->variables->isEmpty())
                    <x-empty-state icon="bolt" title="No Variables"
                                   description="Add one for anything the startup command needs, like a version or a map name." />
                @else
                    <x-table flush>
                        <thead><tr><th>Name</th><th>Environment</th><th>Default</th><th>Visibility</th><th class="text-right vx-act-1">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($template->variables as $variable)
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900">{{ $variable->name }}</span>
                                        @if ($variable->description)<span class="block text-xs text-slate-400 truncate">{{ $variable->description }}</span>@endif
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">{{ $variable->env_variable }}</td>
                                    <td class="font-mono text-xs text-slate-500 truncate">{{ $variable->default_value }}</td>
                                    <td>
                                        <span class="flex items-center gap-1">
                                            @if ($variable->user_editable)<x-badge color="success">Editable</x-badge>
                                            @elseif ($variable->user_viewable)<x-badge color="neutral">Read Only</x-badge>
                                            @else<x-badge color="warn">Hidden</x-badge>@endif
                                        </span>
                                    </td>
                                    <td class="text-right vx-act-1">
                                        <x-delete-button
                                            name="drop-variable-{{ $variable->id }}"
                                            :action="route('admin.templates.variables.destroy', [$template, $variable])"
                                            title="Remove {{ $variable->name }}?"
                                            message="Servers already built from this template keep the value they were given. New ones lose the setting entirely."
                                            label="Remove Variable" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        <div>
            <form method="POST" action="{{ route('admin.templates.variables.store', $template) }}">
                @csrf
                <x-card title="Add A Variable" icon="plus">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name') }}" required placeholder="Minecraft Version" />
                        </x-field>
                        <x-field label="Environment Variable" required hint="Uppercase, underscores. This is what the startup command references." :error="$errors->first('env_variable')">
                            <x-input name="env_variable" value="{{ old('env_variable') }}" required placeholder="MINECRAFT_VERSION" class="font-mono" />
                        </x-field>
                        <x-field label="Description">
                            <x-input name="description" value="{{ old('description') }}" />
                        </x-field>
                        <x-field label="Default Value">
                            <x-input name="default_value" value="{{ old('default_value') }}" />
                        </x-field>
                        <x-field label="Validation Rules" required hint="Laravel rules, pipe separated.">
                            <x-input name="rules" value="{{ old('rules', 'required|string|max:20') }}" required class="font-mono text-xs" />
                        </x-field>
                        <x-toggle name="user_viewable" :checked="(bool) old('user_viewable', true)" label="Clients Can See It" />
                        <x-toggle name="user_editable" :checked="(bool) old('user_editable', true)" label="Clients Can Change It" />
                    </div>
                    <x-slot:footer>
                        <div class="flex justify-end"><x-button type="submit" size="sm" icon="plus">Add Variable</x-button></div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-layouts.app>
