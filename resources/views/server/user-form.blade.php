<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    @php $editing = $subuser->exists; @endphp

    <form method="POST" action="{{ $editing ? route('server.users.update', [$server, $subuser]) : route('server.users.store', $server) }}">
        @csrf
        @if ($editing)@method('PUT')@endif

        <x-card :title="$editing ? 'Access For '.$subuser->user?->name : 'Invite A User'"
                subtitle="Tick only what they need. Everything not ticked is refused, including through the API.">
            @unless ($editing)
                <div class="mb-5 max-w-md">
                    <x-field label="Email Address" required hint="They need a GameMGR account already." :error="$errors->first('email')">
                        <x-input type="email" name="email" value="{{ old('email') }}" required placeholder="friend@example.com" />
                    </x-field>
                </div>
            @endunless

            @php $current = old('permissions', $subuser->permissions ?? []); @endphp
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach (\App\Models\Subuser::MATRIX as $group => $permissions)
                    <div class="rounded-lg ring-1 ring-inset ring-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">{{ $group }}</p>
                        <div class="space-y-2.5">
                            @foreach ($permissions as $key => $description)
                                <x-check-switch name="permissions[]" :value="$key"
                                                :checked="in_array($key, (array) $current, true)">{{ $description }}</x-check-switch>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('server.users', $server) }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $editing ? 'Save Access' : 'Grant Access' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
