<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="database" />

    <form method="POST" action="{{ $host->exists ? route('admin.database-hosts.update', $host) : route('admin.database-hosts.store') }}" class="max-w-2xl">
        @csrf
        @if ($host->exists)@method('PUT')@endif

        <x-card title="Database Host">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $host->name) }}" required placeholder="phx-mysql-01" />
                </x-field>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Host" required class="sm:col-span-2" :error="$errors->first('host')">
                        <x-input name="host" value="{{ old('host', $host->host) }}" required placeholder="10.0.0.20" />
                    </x-field>
                    <x-field label="Port" required>
                        <x-input type="number" name="port" value="{{ old('port', $host->port ?: 3306) }}" required />
                    </x-field>
                </div>
                <x-field label="Address Servers Connect To" hint="If servers should reach it on a different address to the one the panel uses.">
                    <x-input name="linked_ip" value="{{ old('linked_ip', $host->linked_ip) }}" />
                </x-field>
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
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Linked Node" hint="Optional. Only offered to servers on this node when set.">
                        <x-select name="node_id">
                            <option value="">Any node</option>
                            @foreach ($nodes as $node)
                                <option value="{{ $node->id }}" @selected(old('node_id', $host->node_id) == $node->id)>{{ $node->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Maximum Databases" required hint="0 means no limit.">
                        <x-input type="number" name="max_databases" value="{{ old('max_databases', $host->max_databases ?? 0) }}" required />
                    </x-field>
                </div>
                <x-alert type="warn">
                    This account needs CREATE USER and GRANT. It is stored encrypted and never shown to a client, but it
                    is a privileged credential: give it its own MySQL user, not root.
                </x-alert>
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.database-hosts.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $host->exists ? 'Save Host' : 'Create Host' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
