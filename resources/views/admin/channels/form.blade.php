<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="bell"
                   subtitle="A channel is somewhere alerts are delivered: a Discord or Slack webhook, a generic endpoint, or an email address." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $channel->exists ? route('admin.channels.update', $channel) : route('admin.channels.store') }}">
        @csrf
        @if ($channel->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Channel" icon="link">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Name" required :error="$errors->first('name')">
                                <x-input name="name" value="{{ old('name', $channel->name) }}" required placeholder="Ops Discord" />
                            </x-field>
                            <x-field label="Type" required :error="$errors->first('type')">
                                <x-select name="type">
                                    @foreach (\App\Models\NotificationChannel::TYPES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', $channel->type) === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-select>
                            </x-field>
                        </div>
                        <x-field label="Target" :required="! $channel->exists"
                                 :hint="$channel->exists ? 'Leave blank to keep the current one.' : 'A webhook URL, or an email address for the email type.'"
                                 :error="$errors->first('target')">
                            <x-input name="target" value="" placeholder="https://discord.com/api/webhooks/..." autocomplete="off" class="font-mono text-xs" />
                            @if ($channel->exists)
                                <p class="text-xs text-slate-500 font-mono">Currently {{ $channel->maskedTarget() }}</p>
                            @endif
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Send On" icon="bell" subtitle="Everything switched on here is delivered to this channel. Switch the noisy ones off rather than muting the whole channel.">
                    @php $selected = (array) old('events', $channel->events ?? []); @endphp
                    <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach (\App\Models\NotificationChannel::EVENTS as $value => $label)
                            <x-check-switch name="events[]" :value="$value"
                                            :checked="in_array($value, $selected, true)">{{ $label }}</x-check-switch>
                        @endforeach
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Delivery" icon="upload">
                    <div class="space-y-5">
                        <x-toggle name="is_active" :checked="(bool) old('is_active', $channel->is_active ?? true)"
                                  label="Active" description="Off keeps the channel but stops anything being sent to it." />
                        @if ($channel->exists)
                            <p class="text-sm text-slate-500">
                                Last used {{ $channel->last_used_at?->diffForHumans() ?? 'never' }}. Save first, then send a
                                test from the channel list: a channel nobody has tested is decoration.
                            </p>
                        @endif
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $channel->exists ? 'Save Channel' : 'Create Channel' }}</x-button>
                        <x-button href="{{ route('admin.channels.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
