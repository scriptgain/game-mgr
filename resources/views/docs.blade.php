<x-layouts.app title="Documentation">
    <x-page-header title="Documentation" icon="book"
                   subtitle="{{ config('brand.name') }}, the free game server control panel. One panel, nodes anywhere." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="How It Fits Together" icon="puzzle">
                <div class="space-y-4 text-sm text-slate-600">
                    <p>
                        The panel is the only thing you ever log into. Game servers run on <strong>nodes</strong>: any
                        Linux machine with the {{ config('brand.name') }} daemon on it. A node can be a VPS, a dedicated
                        server, a Proxmox VM or a spare PC at home.
                    </p>
                    <p>
                        Add a node in the admin area, run the one-line install it gives you on the machine, and it
                        appears here with its real CPU, memory, disk and free ports. Servers are then placed onto nodes,
                        either by hand or automatically onto whichever suitable node has the most room.
                    </p>
                </div>
            </x-card>

            <x-card title="Three Runtimes, Not One" icon="play">
                <div class="space-y-4">
                    <div class="flex gap-3">
                        <x-runtime-badge runtime="docker" class="shrink-0 mt-0.5" />
                        <p class="text-sm text-slate-600">
                            Containerised. This is what most panels do and what the widest range of community
                            template definitions target, so it is the default.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <x-runtime-badge runtime="steamcmd" class="shrink-0 mt-0.5" />
                        <p class="text-sm text-slate-600">
                            Native install through steamcmd, with no container in the way. Worth having for Source and
                            Unreal servers that misbehave under a container network namespace, and for bare metal nodes
                            where the container overhead buys you nothing.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <x-runtime-badge runtime="linuxgsm" class="shrink-0 mt-0.5" />
                        <p class="text-sm text-slate-600">
                            Wraps the LinuxGSM control scripts, which already know how to install, update and monitor
                            more than 130 games. Pointing at LinuxGSM means inheriting that catalogue rather than
                            reimplementing it one template at a time.
                        </p>
                    </div>
                </div>
            </x-card>

            <x-card title="Games And Templates" icon="controller">
                <div class="space-y-3 text-sm text-slate-600">
                    <p>
                        A <strong>Game</strong> holds <strong>Templates</strong>. Minecraft holds Paper, Forge and
                        Bedrock. A template says how the server is installed, started and stopped.
                    </p>
                    <p>
                        Existing template definitions import directly, variables and install script included, so a
                        migration starts with your whole catalogue rather than an empty one.
                    </p>
                </div>
            </x-card>

            <x-card title="Nodes Behind NAT" icon="network">
                <div class="space-y-3 text-sm text-slate-600">
                    <p>
                        A node can connect two ways. <strong>Direct</strong> means the panel dials the daemon, which
                        needs a reachable port. <strong>Reverse</strong> means the daemon holds an outbound connection to
                        the panel and work is pushed down it.
                    </p>
                    <p>
                        Reverse mode is what makes a machine on a domestic connection usable without port forwarding or
                        a static address, which is the case Pterodactyl has no answer for at all.
                    </p>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            {{-- Listed from the routes themselves rather than typed here. The
                 hand-written version was wrong about the endpoints that existed
                 within a week of being written, because a list nobody
                 regenerates is a list that drifts. --}}
            @php
                $apiRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
                    ->filter(fn ($r) => str_starts_with($r->uri(), 'api/application') || str_starts_with($r->uri(), 'api/client'))
                    ->map(fn ($r) => [
                        'method' => collect($r->methods())->first(fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS'])),
                        'uri' => '/'.$r->uri(),
                    ])
                    ->sortBy(fn ($r) => $r['uri'])
                    ->values();
            @endphp

            <x-card title="Panel API" icon="link">
                <p class="text-sm text-slate-600">
                    Two scopes, matching Pterodactyl so existing tooling ports across. Application drives
                    provisioning: create an account, create a server, suspend it, change the package, terminate it.
                    Client is scoped to the servers its owner can already reach.
                </p>
                <pre class="console-pane vx-scroll mt-3 p-3 text-xs overflow-x-auto">@foreach ($apiRoutes as $route){{ str_pad($route['method'], 7) }}{{ $route['uri'] }}
@endforeach</pre>
                <p class="mt-3 text-xs text-slate-500">
                    Responses carry the Pterodactyl envelope: one object as
                    <span class="font-mono">object</span> and <span class="font-mono">attributes</span>, a list as
                    <span class="font-mono">object: list</span> with <span class="font-mono">meta.pagination</span>.
                    Ask for related records with <span class="font-mono">?include=node,allocations</span>.
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    Create a token under <a href="{{ route('account.api.index') }}" class="text-brand-700 hover:text-brand-800">API Credentials</a>,
                    and send it as <span class="font-mono">Authorization: Bearer</span>. An application token needs an
                    edition that includes the API.
                </p>
            </x-card>

            <x-card title="Node API" icon="link">
                <p class="text-sm text-slate-600">What the daemon on each machine talks to.</p>
                <pre class="console-pane vx-scroll mt-3 p-3 text-xs overflow-x-auto">POST /api/node/enroll
POST /api/node/heartbeat
GET  /api/node/servers
POST /api/node/servers/{uuid}/state</pre>
            </x-card>

            <x-card title="Version" icon="info">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Panel</dt><dd class="text-slate-900 tabular">{{ \App\Services\UpdateService::currentVersion() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Licence</dt><dd class="text-slate-900">Free, no key needed</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>
