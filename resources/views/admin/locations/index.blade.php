<x-layouts.app :title="$title">
    <x-page-header title="Locations" icon="globe" subtitle="Group nodes so capacity and placement mean something.">
        <x-slot:actions>
            <x-button href="{{ route('admin.locations.create') }}" icon="plus" size="sm">New Location</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        @if ($locations->isEmpty())
            <x-empty-state icon="globe" title="No Locations Yet"
                           description="Every node belongs to a location. Create one before adding your first node.">
                <x-slot:action><x-button href="{{ route('admin.locations.create') }}" icon="plus">New Location</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'locations')" label="location">
            <x-slot:table>
                <x-table flush>
                <thead>
                <tr>
                <th class="w-10"><x-select-toggle all /></th><th>Location</th><th>Short Code</th><th>Description</th><th>Nodes</th><th class="text-right vx-act-2">Actions</th></tr>
                </thead>
                <tbody>
                @foreach ($locations as $location)
                <tr>
                <td class="w-10"><x-select-toggle :value="$location->id" :label="$location->name" /></td>
                <td class="font-medium text-slate-900">{{ $location->flag }} {{ $location->name }}</td>
                <td class="font-mono text-xs text-slate-500">{{ $location->short }}</td>
                <td class="text-slate-500">{{ $location->description }}</td>
                <td class="tabular">{{ $location->nodes_count }}</td>
                <td class="text-right">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.locations.edit', $location) }}" icon="edit" title="Edit Location" />
                <x-delete-button
                name="delete-location-{{ $location->id }}"
                :action="route('admin.locations.destroy', $location)"
                title="Delete {{ $location->name }}?"
                message="Only possible while it has no nodes."
                label="Delete Location" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Any location that still has nodes is skipped." confirm-title="Delete These Locations?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
