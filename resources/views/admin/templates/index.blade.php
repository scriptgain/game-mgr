<x-layouts.app :title="$title">
    <x-page-header title="Templates" icon="cube"
                   subtitle="How a server gets installed and started. Each one declares which runtime it needs.">
        <x-slot:actions>
            <x-button href="{{ route('admin.templates.import') }}" variant="secondary" icon="download" size="sm">Import Template</x-button>
            <x-button href="{{ route('admin.templates.create') }}" icon="plus" size="sm">New Template</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        <div class="px-5 py-3 border-b border-slate-100">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <x-select name="game" class="w-56">
                    <option value="">Every Game</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected($filters['game'] == $game->id)>{{ $game->name }}</option>
                    @endforeach
                </x-select>
                <x-select name="runtime" class="w-48">
                    <option value="">Every Runtime</option>
                    @foreach (\App\Models\Template::RUNTIMES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['runtime'] === $value)>{{ $label }}</option>
                    @endforeach
                </x-select>
                <x-button type="submit" variant="secondary" size="sm">Filter</x-button>
            </form>
        </div>

        @if ($templates->isEmpty())
            <x-empty-state icon="cube" title="No Templates"
                           description="Import an existing template definition to get a whole catalogue at once, or write one by hand.">
                <x-slot:action><x-button href="{{ route('admin.templates.import') }}" icon="download">Import Template</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'templates')" label="template">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Template</th><th>Game</th><th>Runtime</th><th>Servers</th><th>Source</th><th class="text-right vx-act-2">Actions</th></tr></thead>
                <tbody>
                @foreach ($templates as $template)
                <tr>
                <td class="w-10"><x-select-toggle :value="$template->id" :label="$template->name" /></td>
                <td>
                <a href="{{ route('admin.templates.show', $template) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $template->name }}</a>
                @if ($template->author)<span class="block text-xs text-slate-400">by {{ $template->author }}</span>@endif
                </td>
                <td class="text-slate-500">{{ $template->game?->name }}</td>
                <td><x-runtime-badge :runtime="$template->runtime" /></td>
                <td class="tabular">{{ $template->servers_count }}</td>
                <td>
                @if ($template->wasImported())
                <x-badge color="neutral"><x-icon name="download" class="w-3.5 h-3.5" /> Imported</x-badge>
                @else
                <span class="text-xs text-slate-400">Built in</span>
                @endif
                </td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.templates.show', $template) }}" icon="eye" title="Open Template" />
                <x-icon-button href="{{ route('admin.templates.edit', $template) }}" icon="edit" title="Edit Template" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Any template still used by a server is skipped." confirm-title="Delete These Templates?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
