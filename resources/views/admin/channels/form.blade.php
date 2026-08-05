<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="bell" />

    <form method="POST" action="{{ $channel->exists ? route('admin.channels.update', $channel) : route('admin.channels.store') }}" class="max-w-3xl">
        @csrf
        @if ($channel->exists)@method('PUT')@endif

        <x-card title="Channel">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $channel->name) }}" required placeholder="Ops Discord" />
                </x-field>
                <x-field label="Type" required>
                    <x-select name="type">
                        @foreach (\App\Models\NotificationChannel::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $channel->type) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Target" :required="! $channel->exists"
                         :hint="$channel->exists ? 'Leave blank to keep the current one.' : 'A webhook URL, or an email address for the email type.'"
                         :error="$errors->first('target')">
                    <x-input name="target" value="" placeholder="https://discord.com/api/webhooks/..." autocomplete="off" />
                    @if ($channel->exists)
                        <p class="text-xs text-slate-500 font-mono">Currently {{ $channel->maskedTarget() }}</p>
                    @endif
                </x-field>

                <div>
                    <p class="text-sm font-medium text-slate-700 mb-2">Send On</p>
                    @php $selected = (array) old('events', $channel->events ?? []); @endphp
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

                <x-toggle name="is_active" :checked="(bool) old('is_active', $channel->is_active ?? true)" label="Active" />
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.channels.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $channel->exists ? 'Save Channel' : 'Create Channel' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
