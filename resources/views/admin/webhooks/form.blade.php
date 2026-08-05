<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="link" />

    <form method="POST" action="{{ $webhook->exists ? route('admin.webhooks.update', $webhook) : route('admin.webhooks.store') }}" class="max-w-3xl">
        @csrf
        @if ($webhook->exists)@method('PUT')@endif

        <x-card title="Webhook">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $webhook->name) }}" required placeholder="Billing Sync" />
                </x-field>
                <x-field label="URL" required :error="$errors->first('url')">
                    <x-input type="url" name="url" value="{{ old('url', $webhook->url) }}" required placeholder="https://billing.example.com/hooks/gamemgr" />
                </x-field>

                <div>
                    <p class="text-sm font-medium text-slate-700 mb-2">Send On</p>
                    @php $selected = (array) old('events', $webhook->events ?? []); @endphp
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (\App\Models\NotificationChannel::EVENTS as $value => $label)
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="events[]" value="{{ $value }}"
                                       @checked(in_array($value, $selected, true))
                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-sm text-slate-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-toggle name="is_active" :checked="(bool) old('is_active', $webhook->is_active ?? true)" label="Active" />
                @if ($webhook->exists)
                    <x-toggle name="rotate_secret" :checked="false" label="Rotate The Signing Secret"
                              description="The current secret stops working the moment you save. Whatever is on the other end needs the new one." />
                @endif
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.webhooks.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $webhook->exists ? 'Save Webhook' : 'Create Webhook' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
