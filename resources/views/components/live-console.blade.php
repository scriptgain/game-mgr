@props([
    'server',
    // Where the command form posts. Both the client console and the admin
    // server page send to the same endpoint; ServerPolicy decides who may.
    'action' => null,
    'height' => 'h-[26rem]',
    'title' => 'Console',
])
{{-- The live console pane: output, feed indicator, follow toggle and the
     command line.

     SCOPE CONTRACT: this markup carries no x-data of its own. It expects to sit
     inside an element running the shared `gameConsole` Alpine component (see
     public/js/gamemgr.js), because the pages that use it also drive their own
     header dot and gauges from the same state. One component, one source of
     truth, no second console implementation to keep in step.

     The pane scrolls vertically on its own, which is the one place scrolling is
     wanted. It never scrolls sideways: long lines wrap. --}}
@php $action = $action ?: route('server.command', $server); @endphp

<x-card flush {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2.5 border-b border-slate-100">
        <div class="flex items-center gap-2 text-sm min-w-0">
            <x-icon name="terminal" class="w-4 h-4 text-slate-400 shrink-0" />
            <span class="font-medium text-slate-900 truncate">{{ $title }}</span>
            <span class="inline-flex items-center gap-1.5 text-xs shrink-0"
                  :class="connected ? 'text-emerald-600' : (unreachable ? 'text-rose-600' : (polled ? 'text-amber-600' : 'text-slate-400'))"
                  data-tip="Live means the browser holds an event stream open to the node. Near Live means this node connects out to the panel rather than accepting connections, so output is fetched every couple of seconds instead of streamed. Polling means the stream is unavailable and the panel is fetching output on your behalf. Node Unreachable means the panel could not reach the daemon either, so nothing on screen was measured.">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-70"
                          x-show="connected" x-cloak></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full"
                          :class="connected ? 'bg-emerald-500' : (unreachable ? 'bg-rose-500' : (polled ? 'bg-amber-500' : 'bg-slate-300'))"></span>
                </span>
                <span x-text="feedLabel()">Reconnecting</span>
            </span>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            {{-- A switch, never a bare checkbox. Bound straight to the shared
                 component so scrolling the pane by hand flips it too. --}}
            <span class="inline-flex items-center gap-2">
                <button type="button" role="switch" :aria-checked="autoScroll.toString()"
                        @click="autoScroll = !autoScroll; scroll()"
                        :class="autoScroll ? 'bg-brand-600' : 'bg-slate-300'"
                        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
                    <span :class="autoScroll ? 'translate-x-4' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                </button>
                <span class="text-xs text-slate-500 whitespace-nowrap">Follow Output</span>
            </span>
            <x-icon-button icon="trash" title="Clear The Pane" variant="ghost" @click="clear()" />
        </div>
    </div>

    <div x-ref="output" @scroll="onScroll()"
         class="console-pane vx-scroll rounded-none ring-0 {{ $height }} overflow-y-auto overflow-x-hidden px-4 py-3 space-y-0.5">
        <template x-for="(line, i) in lines" :key="i">
            <div class="whitespace-pre-wrap [overflow-wrap:anywhere]"
                 :class="{
                    'text-rose-300': line.includes('ERROR') || line.includes('/SEVERE') || line.includes('FATAL'),
                    'text-amber-300': line.includes('WARN'),
                    'text-brand-300': line.startsWith('[gamemgr]'),
                 }"
                 x-text="line"></div>
        </template>
        <div x-show="!lines.length" class="text-slate-500">Waiting for output from the node.</div>
    </div>

    @can('check', [$server, 'control.command'])
        <form method="POST" action="{{ $action }}"
              @submit="remember()"
              class="flex items-center gap-2 px-4 py-3 border-t border-slate-100">
            @csrf
            <span class="text-slate-400 font-mono text-sm select-none" aria-hidden="true">&gt;</span>
            <label for="console-command-{{ $server->uuid_short }}" class="sr-only">Console Command</label>
            <input type="text" name="command" id="console-command-{{ $server->uuid_short }}"
                   x-model="command" autocomplete="off"
                   @keydown.arrow-up.prevent="recall(1)" @keydown.arrow-down.prevent="recall(-1)"
                   placeholder="Type a command and press Enter"
                   :disabled="stats.state !== 'running'"
                   class="flex-1 min-w-0 rounded-lg border-0 bg-slate-50 px-3 py-2 text-sm font-mono text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500 disabled:opacity-60">
            <x-button type="submit" size="sm" icon="bolt" ::disabled="stats.state !== 'running'">Send</x-button>
        </form>
        <p x-show="stats.state !== 'running'" x-cloak
           class="px-4 pb-3 -mt-1 text-xs text-slate-500">
            Commands go to a running process. Start the server first.
        </p>
    @endcan
</x-card>
