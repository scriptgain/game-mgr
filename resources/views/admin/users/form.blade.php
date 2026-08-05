<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="users" />

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="max-w-2xl">
        @csrf
        @if ($user->exists)@method('PUT')@endif
        <x-card title="Account">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $user->name) }}" required />
                </x-field>
                <x-field label="Email" required :error="$errors->first('email')">
                    <x-input type="email" name="email" value="{{ old('email', $user->email) }}" required />
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Role" required>
                        <x-select name="role" :disabled="$user->isRootAdmin()">
                            <option value="client" @selected(old('role', $user->role) === 'client')>Client</option>
                            <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                        </x-select>
                    </x-field>
                    <x-field label="Timezone" required>
                        <x-input name="timezone" value="{{ old('timezone', $user->timezone ?: config('app.timezone')) }}" required />
                    </x-field>
                </div>
                @if ($user->isRootAdmin())
                    <x-alert type="info">
                        This is the root admin. It cannot be demoted, suspended or deleted, which is what stops an
                        install from locking itself out.
                    </x-alert>
                @else
                    <x-toggle name="suspended" :checked="(bool) old('suspended', $user->suspended)"
                              label="Suspended" description="They keep their servers but cannot sign in." />
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Password" :required="! $user->exists"
                             :hint="$user->exists ? 'Leave blank to keep the current one.' : 'At least eight characters.'"
                             :error="$errors->first('password')">
                        <x-input type="password" name="password" autocomplete="new-password" />
                    </x-field>
                    <x-field label="Confirm Password" :required="! $user->exists">
                        <x-input type="password" name="password_confirmation" autocomplete="new-password" />
                    </x-field>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.users.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $user->exists ? 'Save User' : 'Create User' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
