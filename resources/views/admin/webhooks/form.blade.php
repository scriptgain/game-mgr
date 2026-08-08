<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="link"
                   subtitle="A webhook posts panel events to your own endpoint, signed with a shared secret, so billing or provisioning elsewhere can react to them." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $webhook->exists ? route('admin.webhooks.update', $webhook) : route('admin.webhooks.store') }}">
        @csrf
        @if ($webhook->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Endpoint" icon="link">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')"
                                 hint="What you will recognise it by in a list.">
                            <x-input name="name" value="{{ old('name', $webhook->name) }}" required placeholder="Billing Sync" />
                        </x-field>
                        <x-field label="URL" required :error="$errors->first('url')"
                                 hint="Must accept a POST with a JSON body. Anything other than a 2xx counts as a failure.">
                            <x-input type="url" name="url" value="{{ old('url', $webhook->url) }}" required
                                     placeholder="https://billing.example.com/hooks/gamemgr" class="font-mono text-xs" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Send On" icon="bell" subtitle="Only the events switched on here are posted. Everything else is dropped without a request.">
                    @php $selected = (array) old('events', $webhook->events ?? []); @endphp
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
                        <x-toggle name="is_active" :checked="(bool) old('is_active', $webhook->is_active ?? true)"
                                  label="Active" description="Off keeps the webhook but stops anything being posted to it." />
                        @if ($webhook->exists)
                            <x-toggle name="rotate_secret" :checked="false" label="Rotate The Signing Secret"
                                      description="The current secret stops working the moment you save. Whatever is on the other end needs the new one." />
                        @endif
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $webhook->exists ? 'Save Webhook' : 'Create Webhook' }}</x-button>
                        <x-button href="{{ route('admin.webhooks.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
