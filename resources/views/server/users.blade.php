<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Users" subtitle="Share this server without handing over the whole account." flush>
        <x-slot:actions>
            @can('check', [$server, 'user.create'])
                <x-button href="{{ route('server.users.create', $server) }}" size="sm" icon="plus">Invite A User</x-button>
            @endcan
        </x-slot:actions>

        <x-table flush>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Access</th>
                    <th>Permissions</th>
                    <th class="text-right vx-act-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="font-medium text-slate-900">{{ $server->owner?->name }}</span>
                        <span class="block text-xs text-slate-400">{{ $server->owner?->email }}</span>
                    </td>
                    <td><x-badge color="info">Owner</x-badge></td>
                    <td class="text-slate-500">Everything</td>
                    <td class="text-right vx-act-2"></td>
                </tr>
                @foreach ($subusers as $subuser)
                    <tr>
                        <td>
                            <span class="font-medium text-slate-900">{{ $subuser->user?->name }}</span>
                            <span class="block text-xs text-slate-400">{{ $subuser->user?->email }}</span>
                        </td>
                        <td><x-badge color="neutral">Subuser</x-badge></td>
                        <td class="text-slate-500 tabular">{{ count($subuser->permissions ?? []) }} of {{ count(\App\Models\Subuser::allPermissions()) }}</td>
                        <td class="text-right vx-act-2">
                            <div class="inline-flex items-center gap-1">
                                @can('check', [$server, 'user.update'])
                                    <x-icon-button href="{{ route('server.users.edit', [$server, $subuser]) }}" icon="edit" title="Edit Access" />
                                @endcan
                                @can('check', [$server, 'user.delete'])
                                    <x-delete-button
                                        name="remove-subuser-{{ $subuser->id }}"
                                        :action="route('server.users.destroy', [$server, $subuser])"
                                        title="Remove {{ $subuser->user?->name }}?"
                                        message="They lose access to this server immediately. Their own account is untouched."
                                        confirm="Remove Access"
                                        label="Remove Access" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>
</x-layouts.app>
