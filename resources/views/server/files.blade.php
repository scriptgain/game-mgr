<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <div x-data="fileBrowser()">
        <x-card flush>
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-slate-100">
                <nav class="flex items-center gap-1 text-sm min-w-0 flex-wrap" aria-label="Breadcrumb">
                    @foreach ($crumbs as $i => $crumb)
                        @if ($i > 0)<x-icon name="chevron-right" class="w-4 h-4 text-slate-300 shrink-0" />@endif
                        @if ($loop->last)
                            <span class="font-medium text-slate-900 truncate">{{ $crumb['name'] }}</span>
                        @else
                            <a href="{{ route('server.files', [$server, 'path' => $crumb['path']]) }}"
                               class="text-slate-500 hover:text-brand-700 transition truncate">{{ $crumb['name'] }}</a>
                        @endif
                    @endforeach
                </nav>

                <div class="flex items-center gap-2">
                    @can('check', [$server, 'file.delete'])
                        <form method="POST" action="{{ route('server.files.destroy', $server) }}" x-show="selected.length" x-cloak>
                            @csrf @method('DELETE')
                            <template x-for="path in selected" :key="path">
                                <input type="hidden" name="paths[]" :value="path">
                            </template>
                            <x-button type="submit" variant="danger" size="sm" icon="trash">
                                Delete <span x-text="selected.length"></span>
                            </x-button>
                        </form>
                    @endcan
                    @can('check', [$server, 'file.create'])
                        <x-button type="button" size="sm" variant="secondary" icon="plus"
                                  x-on:click="$dispatch('open-modal', 'new-folder')">New Folder</x-button>
                    @endcan
                </div>
            </div>

            @if (empty($entries))
                <x-empty-state icon="folder" title="This Folder Is Empty"
                               description="Nothing here yet. Create a folder or upload files from your game client's export." />
            @else
                <x-table flush>
                    <thead>
                        <tr>
                            <th class="w-10">
                                {{-- Select the whole page. Bound to the browser's own selection
                                     state, so shift-click ranges and this control agree. --}}
                                <label class="vx-switch">
                                    <input type="checkbox" :checked="allSelected()" @change="toggleAll($event.target.checked)">
                                    <span class="vx-switch-track"><span class="vx-switch-knob"></span></span>
                                    <span class="sr-only">Select Everything On This Page</span>
                                </label>
                            </th>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Mode</th>
                            <th>Modified</th>
                            <th class="text-right vx-act-1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $i => $entry)
                            @php
                                $full = rtrim($path, '/').'/'.$entry['name'];
                                $isDir = $entry['directory'] ?? false;
                                $editable = ! $isDir && str_starts_with($entry['mime_type'] ?? '', 'text/')
                                            || in_array($entry['mime_type'] ?? '', ['application/json'], true);
                            @endphp
                            <tr data-file-path="{{ $full }}">
                                <td class="w-10">
                                    {{-- A switch, never a bare checkbox. This one is hand rolled
                                         rather than x-select-toggle because the file manager owns
                                         its own selection state, including shift-click ranges,
                                         so it needs the click handler and the bound :checked. --}}
                                    <label class="vx-switch">
                                        <input type="checkbox" :checked="isSelected(@js($full))"
                                               @click="toggle(@js($full), {{ $i }}, $event.shiftKey)">
                                        <span class="vx-switch-track"><span class="vx-switch-knob"></span></span>
                                        <span class="sr-only">Select {{ $entry['name'] }}</span>
                                    </label>
                                </td>
                                <td>
                                    @if ($isDir)
                                        <a href="{{ route('server.files', [$server, 'path' => $full]) }}"
                                           class="inline-flex items-center gap-2 font-medium text-brand-700 hover:text-brand-800">
                                            <x-icon name="folder" class="w-4 h-4 shrink-0" /> {{ $entry['name'] }}
                                        </a>
                                    @elseif ($editable)
                                        <a href="{{ route('server.files.edit', [$server, 'path' => $full]) }}"
                                           class="inline-flex items-center gap-2 text-slate-800 hover:text-brand-700">
                                            <x-icon name="file" class="w-4 h-4 shrink-0 text-slate-400" /> {{ $entry['name'] }}
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-2 text-slate-700">
                                            <x-icon name="file" class="w-4 h-4 shrink-0 text-slate-300" /> {{ $entry['name'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="tabular text-slate-500">
                                    {{ $isDir ? '' : \App\Support\Format::bytes($entry['size'] ?? 0) }}
                                </td>
                                <td class="font-mono text-xs text-slate-500">{{ $entry['mode'] ?? '' }}</td>
                                <td class="text-slate-500 text-xs">
                                    {{ isset($entry['modified_at']) ? \Illuminate\Support\Carbon::parse($entry['modified_at'])->diffForHumans() : '' }}
                                </td>
                                <td class="text-right vx-act-1">
                                    @if (! $isDir && $editable)
                                        <x-icon-button href="{{ route('server.files.edit', [$server, 'path' => $full]) }}" icon="edit" title="Edit File" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-card>

        @can('check', [$server, 'file.create'])
            <x-modal name="new-folder" title="New Folder" icon="folder" maxWidth="max-w-md">
                <form method="POST" action="{{ route('server.files.mkdir', $server) }}" id="new-folder-form">
                    @csrf
                    <input type="hidden" name="path" value="{{ $path }}">
                    <x-field label="Folder Name" for="folder-name" hint="Created inside {{ $path }}">
                        <x-input id="folder-name" name="name" required placeholder="plugins" />
                    </x-field>
                </form>
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'new-folder')">Cancel</x-button>
                    <x-button size="sm" type="submit" form="new-folder-form">Create Folder</x-button>
                </x-slot:footer>
            </x-modal>
        @endcan
    </div>
</x-layouts.app>
