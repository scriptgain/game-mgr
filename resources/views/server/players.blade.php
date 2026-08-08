<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Online Now" icon="user-group" :subtitle="$online->count().' '.Str::plural('player', $online->count()).' connected'" flush>
                @if ($online->isEmpty())
                    <x-empty-state icon="user-group" title="Nobody Is On"
                                   description="Players appear here as soon as they connect." />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Playtime</th>
                                <th>Flags</th>
                                <th class="text-right vx-act-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($online as $player)
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900">{{ $player->name }}</span>
                                        <span class="block font-mono text-xs text-slate-400 truncate">{{ $player->identifier }}</span>
                                    </td>
                                    <td class="tabular text-slate-500">{{ $player->playtime() }}</td>
                                    <td>
                                        <span class="flex items-center gap-1">
                                            @if ($player->is_op)<x-badge color="info"><x-icon name="star" class="w-3.5 h-3.5" /> Op</x-badge>@endif
                                            @if ($player->is_whitelisted)<x-badge color="success">Whitelisted</x-badge>@endif
                                        </span>
                                    </td>
                                    <td class="text-right vx-act-3">
                                        <div class="inline-flex items-center gap-1">
                                            @can('check', [$server, 'player.manage'])
                                                <form method="POST" action="{{ route('server.players.op', [$server, $player]) }}">
                                                    @csrf<x-icon-button type="submit" icon="star"
                                                                        :variant="$player->is_op ? 'brand' : 'secondary'"
                                                                        :title="$player->is_op ? 'Remove Operator' : 'Make Operator'" />
                                                </form>
                                            @endcan
                                            @can('check', [$server, 'player.kick'])
                                                <form method="POST" action="{{ route('server.players.kick', [$server, $player]) }}">
                                                    @csrf<x-icon-button type="submit" icon="x-circle" title="Kick From The Server" />
                                                </form>
                                            @endcan
                                            @can('check', [$server, 'player.ban'])
                                                <x-confirm-action
                                                    name="ban-{{ $player->id }}"
                                                    :action="route('server.players.ban', [$server, $player])"
                                                    tone="danger"
                                                    title="Ban {{ $player->name }}?"
                                                    message="They are removed now and cannot rejoin until you unban them. The ban is recorded against this server only."
                                                    confirm="Ban Player"
                                                    confirm-variant="danger">
                                                    <x-icon-button icon="ban" variant="danger" title="Ban Player" />
                                                </x-confirm-action>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>

            <x-mass-actions :action="route('server.bulk', [$server, 'players'])" label="player">
                <x-slot:table>
                    <x-card title="Everyone Who Has Played" icon="users" flush>
                    <x-slot:actions>
                    <form method="GET" class="flex items-center gap-2">
                    <x-input name="q" value="{{ $search }}" placeholder="Search by name or id" class="w-56" />
                    <x-button type="submit" variant="secondary" size="sm" icon="search">Search</x-button>
                    </form>
                    </x-slot:actions>
                    @if ($players->isEmpty())
                    <x-empty-state icon="user-group" title="No Players Recorded"
                    description="Nobody has connected to this server yet, or the query protocol has not reported any." />
                    @else
                    <x-table flush>
                    <thead>
                    <tr>
                    <th class="w-10"><x-select-toggle all /></th>
                    <th>Player</th>
                    <th>Last Seen</th>
                    <th>Playtime</th>
                    <th>Status</th>
                    <th class="text-right vx-act-1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($players as $player)
                    <tr>
                    <td class="w-10"><x-select-toggle :value="$player->id" :label="$player->name" /></td>
                    <td>
                    <span class="font-medium text-slate-900">{{ $player->name }}</span>
                    <span class="block font-mono text-xs text-slate-400 truncate">{{ $player->identifier }}</span>
                    </td>
                    <td class="text-slate-500 text-xs">{{ $player->last_seen_at?->diffForHumans() }}</td>
                    <td class="tabular text-slate-500">{{ $player->playtime() }}</td>
                    <td>
                    @if ($player->is_banned)
                    <x-badge color="danger" dot>Banned</x-badge>
                    @elseif ($player->is_online)
                    <x-badge color="success" dot>Online</x-badge>
                    @else
                    <x-badge color="neutral" dot>Offline</x-badge>
                    @endif
                    </td>
                    <td class="text-right vx-act-1">
                    @can('check', [$server, 'player.ban'])
                    @if ($player->is_banned)
                    <form method="POST" action="{{ route('server.players.unban', [$server, $player]) }}">
                    @csrf<x-icon-button type="submit" icon="check-circle" title="Unban Player" />
                    </form>
                    @endif
                    @endcan
                    </td>
                    </tr>
                    @endforeach
                    </tbody>
                    </x-table>
                    @endif
                    </x-card>
                </x-slot:table>

                <x-mass-action action="whitelist" icon="check">Whitelist</x-mass-action>
                <x-mass-action action="unwhitelist" icon="x-circle">Remove From Whitelist</x-mass-action>
                <x-mass-action action="kick" icon="x-circle" confirm="Anyone currently connected is disconnected. They can rejoin straight away." confirm-title="Kick These Players?">Kick</x-mass-action>
                <x-mass-action action="ban" icon="ban" tone="danger" confirm="They are removed now and cannot rejoin until you unban them. The ban is recorded against this server only." confirm-title="Ban These Players?">Ban</x-mass-action>
                <x-mass-action action="unban" icon="check-circle">Unban</x-mass-action>
            </x-mass-actions>
        </div>

        <div>
            <x-card title="Recent Events" icon="book" flush>
                <ul class="divide-y divide-slate-100">
                    @forelse ($events as $event)
                        <li class="px-5 py-3 flex items-start gap-3">
                            <span class="mt-1.5"><x-status-dot :tone="$event->tone()" label="" /></span>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800">
                                    <span class="font-medium">{{ $event->player?->name ?? 'Someone' }}</span>
                                    {{ ['join' => 'joined', 'leave' => 'left', 'kick' => 'was kicked', 'ban' => 'was banned', 'unban' => 'was unbanned'][$event->event] ?? $event->event }}
                                </p>
                                @if ($event->detail)<p class="text-xs text-slate-500">{{ $event->detail }}</p>@endif
                                <p class="text-xs text-slate-400">{{ $event->occurred_at->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-sm text-slate-500">No player events recorded yet.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
