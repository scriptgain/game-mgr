<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="users"
                   subtitle="An account that can sign in to the panel. Clients see only their own servers; admins see everything." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Account">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Name" required :error="$errors->first('name')">
                                <x-input name="name" value="{{ old('name', $user->name) }}" required placeholder="Jane Doe" />
                            </x-field>
                            <x-field label="Email" required :error="$errors->first('email')"
                                     hint="This is the sign in name as well as where mail goes.">
                                <x-input type="email" name="email" value="{{ old('email', $user->email) }}" required placeholder="jane@example.com" />
                            </x-field>
                        </div>
                        <x-field label="Timezone" required :error="$errors->first('timezone')"
                                 hint="Every timestamp shown to this account is rendered in it.">
                            <x-input name="timezone" value="{{ old('timezone', $user->timezone ?: config('app.timezone')) }}" required class="sm:max-w-xs" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Password"
                        :subtitle="$user->exists
                            ? 'Leave both boxes empty to keep the current password.'
                            : 'At least eight characters. The account can change it later from My Account.'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Password" :required="! $user->exists" :error="$errors->first('password')">
                            <x-input type="password" name="password" autocomplete="new-password" />
                        </x-field>
                        <x-field label="Confirm Password" :required="! $user->exists">
                            <x-input type="password" name="password_confirmation" autocomplete="new-password" />
                        </x-field>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Access">
                    <div class="space-y-5">
                        <x-field label="Role" required :error="$errors->first('role')"
                                 :hint="$user->isRootAdmin() ? 'Locked on the root admin.' : 'Admins can reach every server and every setting.'">
                            <x-select name="role" :disabled="$user->isRootAdmin()">
                                <option value="client" @selected(old('role', $user->role) === 'client')>Client</option>
                                <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                            </x-select>
                        </x-field>

                        @if ($user->isRootAdmin())
                            <x-alert type="info">
                                This is the root admin. It cannot be demoted, suspended or deleted, which is what stops an
                                install from locking itself out.
                            </x-alert>
                        @else
                            <x-toggle name="suspended" :checked="(bool) old('suspended', $user->suspended)"
                                      label="Suspended" description="They keep their servers but cannot sign in." />
                        @endif
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $user->exists ? 'Save User' : 'Create User' }}</x-button>
                        <x-button href="{{ route('admin.users.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
