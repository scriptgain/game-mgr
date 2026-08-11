<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="key"
                   subtitle="A Steam account that owns the games it installs. Entered once, bound to as many servers as need it, never shown to a client." />

    <form method="POST" action="{{ $account->exists ? route('admin.steam-accounts.update', $account) : route('admin.steam-accounts.store') }}">
        @csrf
        @if ($account->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Account" icon="key">
                    <div class="space-y-4">
                        <x-field label="Label" required :error="$errors->first('label')"
                                 hint="What you will recognize it by when binding a server. Not the Steam login.">
                            <x-input name="label" value="{{ old('label', $account->label) }}" required placeholder="Deadlock License" />
                        </x-field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Steam Login" required :error="$errors->first('username')">
                                <x-input name="username" value="{{ old('username', $account->username) }}" required autocomplete="off" />
                            </x-field>
                            <x-field label="Password" :required="! $account->exists"
                                     :hint="$account->exists ? 'Leave blank to keep the current one.' : null"
                                     :error="$errors->first('password')">
                                <x-input type="password" name="password" autocomplete="new-password" />
                            </x-field>
                        </div>
                    </div>
                </x-card>

                <x-card title="Steam Guard" icon="shield"
                        subtitle="Without this, the first install on each node has to be authorized by hand on that box.">
                    <div class="space-y-4">
                        <x-field label="Shared Secret"
                                 :hint="$account->exists && filled($account->shared_secret) ? 'Stored. Leave blank to keep it.' : 'Optional. The Base64 shared_secret from a mobile authenticator export.'"
                                 :error="$errors->first('shared_secret')">
                            {{-- No example value here. A placeholder shaped like a real
                                 shared secret reads as one, and this field is the one
                                 place in the panel where a plausible looking wrong value
                                 costs an account lockout rather than a validation error. --}}
                            <x-input type="password" name="shared_secret" autocomplete="new-password" />
                        </x-field>
                        <x-alert type="info">
                            This is the <span class="font-mono text-xs">shared_secret</span> value, which is Base64 and about 28
                            characters long. It is not the Base32 code an ordinary authenticator app shows you, and it is not the
                            five character code itself. The panel generates a fresh code for every install; the secret never
                            leaves this server.
                        </x-alert>
                        @if ($account->exists && filled($account->authorized_nodes))
                            <x-alert type="success">
                                Steam has already accepted a login on
                                {{ count($account->authorized_nodes) }} {{ Str::plural('node', count($account->authorized_nodes)) }},
                                so installs there need no code at all. Changing the login or the password clears that.
                            </x-alert>
                        @endif
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Before You Save" icon="warning">
                    <div class="space-y-3 text-sm text-slate-600">
                        <p>Steam permits one active session per account. Two installs running at once on the same account will
                           knock each other out, so install one server at a time.</p>
                        <p>Give this its own Steam account holding only the games you host. A personal account being used by a
                           panel is a support problem waiting to happen.</p>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $account->exists ? 'Save Account' : 'Create Account' }}</x-button>
                        <x-button href="{{ route('admin.steam-accounts.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
