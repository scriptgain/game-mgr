<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Databases" icon="database"
            subtitle="{{ $databases->count() }} of {{ $server->database_limit ?: 'unlimited' }} used."
            flush>
        <x-slot:actions>
            @can('check', [$server, 'database.create'])
                <x-button type="button" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'new-database')">New Database</x-button>
            @endcan
        </x-slot:actions>

        @if ($databases->isEmpty())
            <x-empty-state icon="database" title="No Databases"
                           description="Plugins that store data, economies and web maps all want one. Credentials are generated for you." />
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($databases as $database)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900 font-mono">{{ $database->database }}</p>
                                <p class="text-xs text-slate-500">{{ $database->host?->name }} &middot; {{ \App\Support\Format::bytes($database->bytes) }}</p>
                            </div>
                            @can('check', [$server, 'database.delete'])
                                <x-delete-button
                                    name="drop-db-{{ $database->id }}"
                                    :action="route('server.databases.destroy', [$server, $database])"
                                    title="Delete {{ $database->database }}?"
                                    message="The database and everything in it is dropped. Any plugin using it will start throwing errors immediately."
                                    confirm="Delete Database"
                                    label="Delete Database" />
                            @endcan
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <x-copy-field label="Host" :value="($database->host?->linked_ip ?: $database->host?->host).':'.($database->host?->port ?? 3306)" />
                            <x-copy-field label="Database" :value="$database->database" />
                            <x-copy-field label="Username" :value="$database->username" />
                            <x-copy-field label="Password" :value="$database->password" masked />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    @can('check', [$server, 'database.create'])
        <x-modal name="new-database" title="New Database" icon="database">
            <form method="POST" action="{{ route('server.databases.store', $server) }}" id="new-database-form" class="space-y-4">
                @csrf
                <x-field label="Name" hint="Letters, numbers and underscores. Your server id is prefixed automatically." required>
                    <x-input name="name" required placeholder="main" pattern="[a-zA-Z0-9_]+" />
                </x-field>
                <x-field label="Database Host">
                    <x-select name="database_host_id" required>
                        @foreach ($hosts as $host)
                            <option value="{{ $host->id }}">{{ $host->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Connections Allowed From" hint="% allows any address. Narrow this if you know where the plugin connects from.">
                    <x-input name="remote" value="%" required />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'new-database')">Cancel</x-button>
                <x-button size="sm" type="submit" form="new-database-form">Create Database</x-button>
            </x-slot:footer>
        </x-modal>
    @endcan
</x-layouts.app>
