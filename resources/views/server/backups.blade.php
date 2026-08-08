<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Backups" icon="archive"
            subtitle="{{ $used }} of {{ $server->backup_limit ?: 'unlimited' }} used. Locked backups sit outside the limit."
            flush>
        <x-slot:actions>
            @can('check', [$server, 'backup.create'])
                <x-button type="button" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'new-backup')">Take Backup</x-button>
            @endcan
        </x-slot:actions>

        @if ($backups->isEmpty())
            <x-empty-state icon="archive" title="No Backups Yet"
                           description="Take one before your next big change. A backup you did not take is the one you will want." />
        @else
            <x-mass-actions :action="route('server.bulk', [$server, 'backups'])" label="backup">
            <x-slot:table>
                <x-table flush>
                <thead>
                <tr><th class="w-10"><x-select-toggle all /></th>
                <th>Name</th>
                <th>Size</th>
                <th>Stored On</th>
                <th>Taken</th>
                <th>Status</th>
                <th class="text-right vx-act-3">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($backups as $backup)
                <tr>
                <td class="w-10"><x-select-toggle :value="$backup->id" :label="$backup->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $backup->name }}</span>
                @if ($backup->is_locked)
                <x-badge color="info" class="ml-1.5"><x-icon name="lock" class="w-3 h-3" /> Locked</x-badge>
                @endif
                @if ($backup->failure_reason)
                <span class="block text-xs text-rose-600">{{ $backup->failure_reason }}</span>
                @endif
                </td>
                <td class="tabular text-slate-500">{{ \App\Support\Format::bytes($backup->bytes) }}</td>
                <td><x-badge color="neutral">{{ $backup->diskLabel() }}</x-badge></td>
                <td class="text-slate-500 text-xs">{{ $backup->completed_at?->diffForHumans() ?? 'In progress' }}</td>
                <td><x-status-dot :tone="$backup->statusTone()" :label="$backup->statusLabel()" /></td>
                <td class="text-right vx-act-3">
                <div class="inline-flex items-center gap-1">
                @can('check', [$server, 'backup.create'])
                <form method="POST" action="{{ route('server.backups.lock', [$server, $backup]) }}">
                @csrf<x-icon-button type="submit" :icon="$backup->is_locked ? 'lock' : 'key'"
                :title="$backup->is_locked ? 'Unlock, Retention Can Remove It' : 'Lock, Retention Will Leave It Alone'" />
                </form>
                @endcan
                @can('check', [$server, 'backup.restore'])
                @if ($backup->is_successful)
                <x-confirm-action
                name="restore-{{ $backup->id }}"
                :action="route('server.backups.restore', [$server, $backup])"
                tone="warn"
                title="Restore This Backup?"
                message="Everything on the server is replaced with the contents of this backup. Anything created since it was taken is gone. The server stops while it runs."
                confirm="Restore It"
                confirm-variant="primary">
                <x-icon-button icon="restore" title="Restore This Backup" />
                </x-confirm-action>
                @endif
                @endcan
                @can('check', [$server, 'backup.delete'])
                @unless ($backup->is_locked)
                <x-delete-button
                name="delete-backup-{{ $backup->id }}"
                :action="route('server.backups.destroy', [$server, $backup])"
                title="Delete This Backup?"
                message="The archive is removed from storage. This cannot be undone."
                label="Delete Backup" />
                @endunless
                @endcan
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="lock" icon="lock">Lock</x-mass-action>
            <x-mass-action action="unlock" icon="key">Unlock</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="The archives are removed from storage. Locked ones are skipped. This cannot be undone." confirm-title="Delete These Backups?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>

    @can('check', [$server, 'backup.create'])
        <x-modal name="new-backup" title="Take A Backup" icon="archive">
            <form method="POST" action="{{ route('server.backups.store', $server) }}" id="new-backup-form" class="space-y-4">
                @csrf
                <x-field label="Name" hint="Leave blank and it is named after today's date.">
                    <x-input name="name" placeholder="Before the 1.21 update" />
                </x-field>
                <x-field label="Stored On" hint="Off-node storage survives losing the node itself.">
                    <x-select name="disk">
                        <option value="local">Node Local</option>
                        <option value="s3">S3</option>
                        <option value="storagemgr">StorageMGR</option>
                    </x-select>
                </x-field>
                <x-field label="Skip These Paths" hint="One per line. Large caches and logs are worth skipping.">
                    <textarea name="ignored" rows="3" placeholder="logs/&#10;cache/"
                              class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"></textarea>
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'new-backup')">Cancel</x-button>
                <x-button size="sm" type="submit" form="new-backup-form" icon="archive">Take Backup</x-button>
            </x-slot:footer>
        </x-modal>
    @endcan
</x-layouts.app>
