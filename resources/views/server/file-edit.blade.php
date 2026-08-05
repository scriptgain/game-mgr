<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <form method="POST" action="{{ route('server.files.save', $server) }}">
        @csrf
        <input type="hidden" name="path" value="{{ $path }}">

        <x-card flush>
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-slate-100">
                <nav class="flex items-center gap-1 text-sm min-w-0 flex-wrap" aria-label="Breadcrumb">
                    @foreach ($crumbs as $i => $crumb)
                        @if ($i > 0)<x-icon name="chevron-right" class="w-4 h-4 text-slate-300 shrink-0" />@endif
                        @if ($loop->last)
                            <span class="font-medium text-slate-900 truncate">{{ $crumb['name'] }}</span>
                        @else
                            <a href="{{ route('server.files', [$server, 'path' => $crumb['path']]) }}"
                               class="text-slate-500 hover:text-brand-700 transition truncate">{{ $crumb['name'] }}</a>
                        @endif
                    @endforeach
                </nav>
                <span class="text-xs text-slate-400">{{ strlen($content) }} bytes</span>
            </div>

            {{-- A plain textarea, deliberately. A CDN editor would be prettier
                 and would also break on any install with a strict CSP or no
                 outbound network, which self-hosted installs frequently are. --}}
            <textarea name="content" spellcheck="false" @readonly($readOnly)
                      class="console-pane vx-scroll block w-full rounded-none ring-0 border-0 h-[32rem] px-4 py-3 resize-y focus:ring-0"
            >{{ $content }}</textarea>

            <x-slot:footer>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        @if ($readOnly)
                            You can read this file but not change it.
                        @else
                            Most game servers only reread their configuration on restart.
                        @endif
                    </p>
                    <div class="flex items-center gap-2">
                        <x-button href="{{ route('server.files', [$server, 'path' => dirname($path)]) }}" variant="secondary" size="sm">Back</x-button>
                        @unless ($readOnly)
                            <x-button type="submit" size="sm" icon="check">Save File</x-button>
                        @endunless
                    </div>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
