<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="database"
                   subtitle="A MySQL or MariaDB server that per-server game databases get carved out of. Clients only ever see the account created for them." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $host->exists ? route('admin.database-hosts.update', $host) : route('admin.database-hosts.store') }}">
        @csrf
        @if ($host->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Server" icon="database">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')"
                                 hint="What you will recognise it by in a list. Not the hostname.">
                            <x-input name="name" value="{{ old('name', $host->name) }}" required placeholder="phx-mysql-01" />
                        </x-field>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-field label="Host" required class="sm:col-span-2" :error="$errors->first('host')"
                                     hint="The address this panel connects on.">
                                <x-input name="host" value="{{ old('host', $host->host) }}" required placeholder="10.0.0.20" />
                            </x-field>
                            <x-field label="Port" required :error="$errors->first('port')">
                                <x-input type="number" name="port" value="{{ old('port', $host->port ?: 3306) }}" required />
                            </x-field>
                        </div>
                        <x-field label="Address Servers Connect To" :error="$errors->first('linked_ip')"
                                 hint="Only needed if game servers should reach it on a different address to the one the panel uses.">
                            <x-input name="linked_ip" value="{{ old('linked_ip', $host->linked_ip) }}" placeholder="db.internal" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Privileged Credentials" icon="key"
                        subtitle="The account this panel uses to create databases and users on demand.">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Username" required :error="$errors->first('username')">
                                <x-input name="username" value="{{ old('username', $host->username) }}" required autocomplete="off" />
                            </x-field>
                            <x-field label="Password" :required="! $host->exists"
                                     :hint="$host->exists ? 'Leave blank to keep the current one.' : null"
                                     :error="$errors->first('password')">
                                <x-input type="password" name="password" autocomplete="new-password" />
                            </x-field>
                        </div>
                        <x-alert type="warn">
                            This account needs CREATE USER and GRANT. It is stored encrypted and never shown to a client, but it
                            is a privileged credential: give it its own MySQL user, not root.
                        </x-alert>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Availability" icon="users" subtitle="Who is offered a database on this host, and how many.">
                    <div class="space-y-4">
                        <x-field label="Linked Node" :error="$errors->first('node_id')"
                                 hint="Optional. Only offered to servers on this node when set.">
                            <x-select name="node_id">
                                <option value="">Any node</option>
                                @foreach ($nodes as $node)
                                    <option value="{{ $node->id }}" @selected(old('node_id', $host->node_id) == $node->id)>{{ $node->name }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                        <x-field label="Maximum Databases" required :error="$errors->first('max_databases')" hint="0 means no limit.">
                            <x-input type="number" name="max_databases" value="{{ old('max_databases', $host->max_databases ?? 0) }}" required />
                        </x-field>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $host->exists ? 'Save Host' : 'Create Host' }}</x-button>
                        <x-button href="{{ route('admin.database-hosts.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
