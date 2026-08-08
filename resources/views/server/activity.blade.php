<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Activity" icon="book" subtitle="Every action taken against this server, by anyone." flush>
        @if ($entries->isEmpty())
            <x-empty-state icon="book" title="Nothing Recorded Yet"
                           description="Actions appear here as soon as somebody does something." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($entries as $entry)
                    <li class="px-5 py-3.5 flex items-start gap-3">
                        <span class="mt-1.5"><x-status-dot :tone="$entry->tone()" label="" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-800">{{ $entry->description }}</p>
                            @if (! empty($entry->properties['command']))
                                <p class="mt-1 font-mono text-xs text-slate-500 bg-slate-50 rounded px-2 py-1 inline-block break-all">{{ $entry->properties['command'] }}</p>
                            @endif
                            <p class="text-xs text-slate-400">
                                {{ $entry->user?->name ?? 'System' }}
                                @if ($entry->ip) &middot; {{ $entry->ip }} @endif
                                &middot; {{ $entry->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-slot:footer>{{ $entries->links() }}</x-slot:footer>
        @endif
    </x-card>
</x-layouts.app>
