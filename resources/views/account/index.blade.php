<x-layouts.app :title="$title">
    {{-- This page was 1900px tall for four form fields and a list, with the
         right column running out half way down and leaving a long empty gutter
         beside a scrolling activity feed.

         Three things fixed it, none of them decoration. The form pairs its
         fields, because four one-per-row inputs is a column of air. The
         identity moves into the header where it costs nothing. And the activity
         feed gets a bounded height with its own scroll, so a busy account and a
         quiet one produce a page of the same size. --}}
    <x-page-header title="My Account" icon="user-group" subtitle="Your details and how you get in.">
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="lock" href="{{ route('settings.password.edit') }}">Change Password</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Who you are, at a glance, so the form below is editing rather than
         informing. The counts are the two questions people open this page to
         answer, and neither was visible without scrolling before. --}}
    <div class="mb-6 flex flex-wrap items-center gap-x-6 gap-y-4 rounded-xl bg-white px-5 py-4 ring-1 ring-slate-200">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
            {{ \Illuminate\Support\Str::of($user->name)->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}
        </span>
        <div class="min-w-0">
            <p class="truncate font-semibold text-slate-900">{{ $user->name }}</p>
            <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 sm:ml-auto">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Role</p>
                <p class="text-sm font-medium text-slate-900">{{ $user->isAdmin() ? ($user->isRootAdmin() ? 'Root Admin' : 'Admin') : 'Client' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Servers</p>
                <p class="tabular text-sm font-medium text-slate-900">{{ $user->servers()->count() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Shared With You</p>
                <p class="tabular text-sm font-medium text-slate-900">{{ $user->subusers()->count() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Two-Factor</p>
                <p class="text-sm font-medium">
                    @if ($user->hasTwoFactor())
                        <span class="text-emerald-700">On</span>
                    @else
                        <a href="{{ route('settings.2fa.show') }}" class="text-amber-700 underline hover:text-amber-800">Off</a>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('account.update') }}">
                @csrf @method('PUT')
                <x-card title="Details" icon="info">
                    {{-- Paired: name and email are one thought, and so are the
                         two that decide how the panel treats you. --}}
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name', $user->name) }}" required />
                        </x-field>
                        <x-field label="Email" required :error="$errors->first('email')">
                            <x-input type="email" name="email" value="{{ old('email', $user->email) }}" required />
                        </x-field>
                        <x-field label="Username" hint="Signs you in over SFTP, as username.serverid."
                                 :error="$errors->first('username')">
                            <x-input name="username" value="{{ old('username', $user->username) }}" required />
                        </x-field>
                        <x-field label="Timezone" required hint="Every timestamp in the panel is shown in this zone."
                                 :error="$errors->first('timezone')">
                            <x-input name="timezone" value="{{ old('timezone', $user->timezone) }}" required />
                        </x-field>
                    </div>
                    <x-slot:footer>
                        <div class="flex justify-end"><x-button type="submit" size="sm">Save Details</x-button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            <x-card title="Recent Activity" icon="book" flush
                    subtitle="Everything done from this account, newest first.">
                <x-slot:actions>
                    @can('admin')
                        <x-button href="{{ route('settings.audit.index') }}" variant="secondary" size="sm">Full Audit Log</x-button>
                    @endcan
                </x-slot:actions>

                {{-- Bounded and scrolled inside its own box. An account with two
                     hundred entries and one with three now make a page of the
                     same height, which is the whole complaint. --}}
                <ul class="vx-scroll max-h-[21rem] divide-y divide-slate-100 overflow-y-auto">
                    @forelse ($recent as $entry)
                        <li class="flex items-baseline gap-3 px-5 py-2.5">
                            <span class="translate-y-0.5"><x-status-dot :tone="$entry->tone()" label="" /></span>
                            <p class="min-w-0 flex-1 truncate text-sm text-slate-800">{{ $entry->description }}</p>
                            <p class="shrink-0 text-xs text-slate-400" title="{{ $entry->ip }}">{{ $entry->created_at->diffForHumans(short: true) }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">Nothing recorded yet.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Security" icon="shield">
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('settings.password.edit') }}" class="flex items-center justify-between gap-3 rounded-lg border border-transparent px-3 py-2.5 text-sm transition hover:border-slate-200 hover:bg-slate-50">
                            <span class="flex items-center gap-2.5 text-slate-700"><x-icon name="lock" class="w-4 h-4 text-slate-400" /> Change Password</span>
                            <span class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">{{ $user->password_changed_at?->diffForHumans(short: true) ?? 'never' }}</span>
                                <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('settings.2fa.show') }}" class="flex items-center justify-between gap-3 rounded-lg border border-transparent px-3 py-2.5 text-sm transition hover:border-slate-200 hover:bg-slate-50">
                            <span class="flex items-center gap-2.5 text-slate-700"><x-icon name="shield" class="w-4 h-4 text-slate-400" /> Two-Factor Authentication</span>
                            <span class="flex items-center gap-2">
                                @if ($user->hasTwoFactor())<x-badge color="success">On</x-badge>@else<x-badge color="neutral">Off</x-badge>@endif
                                <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('account.api.index') }}" class="flex items-center justify-between gap-3 rounded-lg border border-transparent px-3 py-2.5 text-sm transition hover:border-slate-200 hover:bg-slate-50">
                            <span class="flex items-center gap-2.5 text-slate-700"><x-icon name="key" class="w-4 h-4 text-slate-400" /> API Credentials</span>
                            <x-icon name="chevron-right" class="w-4 h-4 text-slate-300" />
                        </a>
                    </li>
                </ul>
            </x-card>

            {{-- Sits at the bottom of a column that used to run out early, and
                 answers the question the API Credentials link raises. --}}
            <x-card title="Using The API" icon="link">
                <p class="text-sm text-slate-600">
                    Everything in the panel is reachable over HTTP with a token from API Credentials above.
                </p>
                <x-slot:footer>
                    <x-button href="{{ route('api-docs') }}" variant="secondary" size="sm" icon="book">Read The Reference</x-button>
                </x-slot:footer>
            </x-card>
        </div>
    </div>
</x-layouts.app>
