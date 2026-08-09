<x-layouts.app :title="$title">
    <x-page-header title="My Account" icon="user-group" subtitle="Your details and how you get in." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <form method="POST" action="{{ route('account.update') }}">
                @csrf @method('PUT')
                <x-card title="Details" icon="info">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name', $user->name) }}" required />
                        </x-field>
                        <x-field label="Email" required :error="$errors->first('email')">
                            <x-input type="email" name="email" value="{{ old('email', $user->email) }}" required />
                        </x-field>
                        <x-field label="Username" hint="Used with your password to sign in over SFTP, as username.serverid."
                                 :error="$errors->first('username')">
                            <x-input name="username" value="{{ old('username', $user->username) }}" required />
                        </x-field>
                        <x-field label="Timezone" required hint="Every timestamp in the panel is shown in this zone.">
                            <x-input name="timezone" value="{{ old('timezone', $user->timezone) }}" required />
                        </x-field>
                    </div>
                    <x-slot:footer>
                        <div class="flex justify-end"><x-button type="submit" size="sm">Save Details</x-button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            <x-card title="Recent Activity" icon="book" flush>
                <ul class="divide-y divide-slate-100">
                    @forelse ($recent as $entry)
                        <li class="px-5 py-3 flex items-start gap-3">
                            <span class="mt-1.5"><x-status-dot :tone="$entry->tone()" label="" /></span>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800">{{ $entry->description }}</p>
                                <p class="text-xs text-slate-400">{{ $entry->ip }} &middot; {{ $entry->created_at->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-sm text-slate-500">Nothing recorded yet.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Security" icon="shield">
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('settings.password.edit') }}" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm border border-transparent hover:bg-slate-50 hover:border-slate-200 transition">
                            <span class="flex items-center gap-2.5 text-slate-700"><x-icon name="lock" class="w-4 h-4 text-slate-400" /> Change Password</span>
                            <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('settings.2fa.show') }}" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm border border-transparent hover:bg-slate-50 hover:border-slate-200 transition">
                            <span class="flex items-center gap-2.5 text-slate-700"><x-icon name="shield" class="w-4 h-4 text-slate-400" /> Two-Factor Authentication</span>
                            <span class="flex items-center gap-2">
                                @if ($user->hasTwoFactor())<x-badge color="success">On</x-badge>@else<x-badge color="neutral">Off</x-badge>@endif
                                <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('account.api.index') }}" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm border border-transparent hover:bg-slate-50 hover:border-slate-200 transition">
                            <span class="flex items-center gap-2.5 text-slate-700"><x-icon name="key" class="w-4 h-4 text-slate-400" /> API Credentials</span>
                            <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                        </a>
                    </li>
                </ul>
            </x-card>

            <x-card title="Account" icon="users">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Role</dt><dd class="text-slate-900">{{ $user->isAdmin() ? ($user->isRootAdmin() ? 'Root Admin' : 'Admin') : 'Client' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Servers Owned</dt><dd class="tabular text-slate-900">{{ $user->servers()->count() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Shared With You</dt><dd class="tabular text-slate-900">{{ $user->subusers()->count() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Password Changed</dt><dd class="text-slate-900">{{ $user->password_changed_at?->diffForHumans() ?? 'never' }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>
