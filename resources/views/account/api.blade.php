<x-layouts.app :title="$title">
    <x-page-header title="API Credentials" icon="key"
                   subtitle="The same client and application split as Pterodactyl, so tooling ports across with a base URL change." />

    @if (session('plain_token'))
        <div class="mb-6">
            <x-card title="Your New Token" subtitle="Shown once. Copy it now; only its hash is stored.">
                <x-copy-field :value="session('plain_token')" masked />
            </x-card>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Tokens" flush>
                @if ($tokens->isEmpty())
                    <x-empty-state icon="key" title="No Tokens"
                                   description="Create one to drive the panel from a script, a bot or your own front end." />
                @else
                    <x-table flush>
                        <thead><tr><th>Name</th><th>Scope</th><th>Last Used</th><th>Expires</th><th class="text-right vx-act-1">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($tokens as $token)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $token->name }}</td>
                                    <td><x-badge :color="$token->scope === 'application' ? 'warn' : 'neutral'">{{ $token->scopeLabel() }}</x-badge></td>
                                    <td class="text-slate-500 text-xs">{{ $token->last_used_at?->diffForHumans() ?? 'never' }}</td>
                                    <td class="text-slate-500 text-xs">
                                        @if ($token->isExpired())<span class="text-rose-600">expired</span>
                                        @else{{ $token->expires_at?->diffForHumans() ?? 'never' }}@endif
                                    </td>
                                    <td class="text-right vx-act-1">
                                        <x-delete-button
                                            name="revoke-token-{{ $token->id }}"
                                            :action="route('account.api.destroy', $token)"
                                            title="Revoke {{ $token->name }}?"
                                            message="Anything using this token stops working immediately."
                                            confirm="Revoke"
                                            label="Revoke Token" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <form method="POST" action="{{ route('account.api.store') }}">
                @csrf
                <x-card title="New Token">
                    <div class="space-y-4">
                        <x-field label="Name" required hint="What is it for? You will thank yourself later." :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name') }}" required placeholder="Discord status bot" />
                        </x-field>
                        <x-field label="Scope" required>
                            <x-select name="scope">
                                <option value="client">Client: only your servers</option>
                                @if (auth()->user()->isAdmin())
                                    <option value="application">Application: the whole panel</option>
                                @endif
                            </x-select>
                        </x-field>
                        <x-field label="Expires In (days)" hint="Blank means it never expires. Prefer a number.">
                            <x-input type="number" name="expires_days" value="{{ old('expires_days') }}" placeholder="90" />
                        </x-field>
                    </div>
                    <x-slot:footer>
                        <div class="flex justify-end"><x-button type="submit" size="sm" icon="plus">Create Token</x-button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            <x-card title="Using It">
                <p class="text-sm text-slate-600">Send it as a bearer token:</p>
                <pre class="console-pane vx-scroll mt-2 p-3 text-xs overflow-x-auto">curl -H "Authorization: Bearer gm_..." \
  {{ rtrim(config('app.url'), '/') }}/api/client/servers</pre>
            </x-card>
        </div>
    </div>
</x-layouts.app>
