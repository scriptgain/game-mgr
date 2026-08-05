<x-layouts.app :title="$title">
    <x-page-header title="Blueprints" icon="copy"
                   subtitle="A saved server shape. Creating the hundredth Minecraft Starter should be one click, not fifteen fields retyped.">
        <x-slot:actions><x-button href="{{ route('admin.blueprints.create') }}" icon="plus" size="sm">New Blueprint</x-button></x-slot:actions>
    </x-page-header>

    <x-card flush>
        @if ($blueprints->isEmpty())
            <x-empty-state icon="copy" title="No Blueprints"
                           description="Save the shapes you create over and over, then apply one at server creation.">
                <x-slot:action><x-button href="{{ route('admin.blueprints.create') }}" icon="plus">New Blueprint</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'blueprints')" label="blueprint">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Blueprint</th><th>Template</th><th>Resources</th><th>Created By</th><th class="text-right vx-act-2">Actions</th></tr></thead>
                <tbody>
                @foreach ($blueprints as $blueprint)
                <tr>
                <td class="w-10"><x-select-toggle :value="$blueprint->id" :label="$blueprint->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $blueprint->name }}</span>
                @if ($blueprint->description)<span class="block text-xs text-slate-400 truncate">{{ $blueprint->description }}</span>@endif
                </td>
                <td class="text-slate-500">{{ $blueprint->template?->game?->name }} : {{ $blueprint->template?->name }}</td>
                <td class="text-slate-500 tabular">{{ $blueprint->summary() }}</td>
                <td class="text-slate-500">{{ $blueprint->creator?->name }}</td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.blueprints.edit', $blueprint) }}" icon="edit" title="Edit Blueprint" />
                <x-delete-button
                name="delete-blueprint-{{ $blueprint->id }}"
                :action="route('admin.blueprints.destroy', $blueprint)"
                title="Delete {{ $blueprint->name }}?"
                message="Servers already created from it are unaffected."
                label="Delete Blueprint" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Servers already created from them are unaffected." confirm-title="Delete These Blueprints?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
