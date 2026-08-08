<x-layouts.app :title="$title">
    <x-page-header title="Webhooks" icon="link" subtitle="Wire GameMGR into billing or provisioning. Payloads are signed.">
        <x-slot:actions><x-button href="{{ route('admin.webhooks.create') }}" icon="plus" size="sm">New Webhook</x-button></x-slot:actions>
    </x-page-header>

    @if (session('webhook_secret'))
        <div class="mb-6">
            <x-card title="Signing Secret" icon="key" subtitle="Shown once. Store it now.">
                <x-copy-field :value="session('webhook_secret')" masked />
            </x-card>
        </div>
    @endif

    <x-card flush>
        @if ($webhooks->isEmpty())
            <x-empty-state icon="link" title="No Webhooks"
                           description="Have GameMGR tell your billing system when a server is created, suspended or deleted.">
                <x-slot:action><x-button href="{{ route('admin.webhooks.create') }}" icon="plus">New Webhook</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'webhooks')" label="webhook">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Name</th><th>URL</th><th>Events</th><th>Health</th><th class="text-right vx-act-2">Actions</th></tr></thead>
                <tbody>
                @foreach ($webhooks as $webhook)
                <tr>
                <td class="w-10"><x-select-toggle :value="$webhook->id" :label="$webhook->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $webhook->name }}</span>
                @unless ($webhook->is_active)<x-badge color="neutral" class="ml-1.5">Off</x-badge>@endunless
                </td>
                <td class="font-mono text-xs text-slate-500">{{ $webhook->url }}</td>
                <td class="tabular text-slate-500">{{ count($webhook->events ?? []) }}</td>
                <td>
                @if ($webhook->isHealthy())
                <x-badge color="success" dot>Healthy</x-badge>
                @else
                <x-badge color="danger" dot>{{ $webhook->failure_count }} failures</x-badge>
                @endif
                </td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.webhooks.edit', $webhook) }}" icon="edit" title="Edit Webhook" />
                <x-delete-button
                name="delete-webhook-{{ $webhook->id }}"
                :action="route('admin.webhooks.destroy', $webhook)"
                title="Delete {{ $webhook->name }}?"
                message="Whatever is on the other end stops hearing about anything."
                label="Delete Webhook" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="enable" icon="check">Enable</x-mass-action>
            <x-mass-action action="disable" icon="x-circle">Disable</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Whatever is on the other end stops hearing about anything." confirm-title="Delete These Webhooks?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
