<x-layouts.app title="Mods And Plugins">
    <x-page-header title="Mods And Plugins" icon="puzzle"
                   subtitle="Where one-click installs come from, and the two keys that unlock the two catalogues needing one.">
        <x-slot:actions>
            <x-button variant="secondary" icon="settings" href="{{ route('settings.index') }}">Settings</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        <x-card title="Catalogues" icon="puzzle" flush
                subtitle="A template decides which of these a given server may use. This page decides which of them work at all.">
            <x-table flush>
                <thead>
                    <tr><th>Catalogue</th><th>Needs A Key</th><th>State</th></tr>
                </thead>
                <tbody>
                    @foreach ($sources as $source)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $source->label() }}</td>
                            <td class="text-slate-500">
                                {{ in_array($source->key(), ['curseforge', 'workshop'], true) ? 'Yes' : 'No' }}
                            </td>
                            <td>
                                @if ($source->available())
                                    <x-status-dot tone="success" label="Ready" />
                                @else
                                    <x-status-dot tone="warn" label="Unavailable" />
                                    <span class="block text-xs text-slate-500 [overflow-wrap:anywhere]">{{ $source->unavailableReason() }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        <form method="POST" action="{{ route('settings.mods.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="CurseForge" icon="key"
                    subtitle="The largest library, and the only one here that carries games other than Minecraft.">
                <div class="space-y-5">
                    <x-alert type="info" title="Some Projects Cannot Be Installed, Whatever The Key Says">
                        An author can switch off third-party downloads, and the API then returns no download link at all.
                        Those projects are shown with a link to their page instead of an install button. Working around it
                        by rebuilding the download URL is against CurseForge's terms and this panel will not do it.
                    </x-alert>

                    <x-field label="API Key" for="mods_curseforge_key"
                             :error="$errors->first('mods_curseforge_key')"
                             hint="{{ $hasCurseForge ? 'A key is stored. Leave this blank to keep it.' : 'Free from console.curseforge.com, under API Keys.' }}">
                        <x-input id="mods_curseforge_key" name="mods_curseforge_key" type="password"
                                 autocomplete="new-password" data-lpignore="true"
                                 placeholder="{{ $hasCurseForge ? 'Stored. Type a new key to replace it.' : '$2a$10$...' }}" />
                    </x-field>

                    @if ($hasCurseForge)
                        <x-confirm-action name="clear-curseforge-key" tone="danger"
                                          :action="route('settings.mods.clear', 'curseforge')" method="POST"
                                          title="Delete The Stored CurseForge Key?"
                                          message="Mods already installed stay exactly where they are. CurseForge simply cannot be searched or updated from here until a new key is saved."
                                          confirm="Delete Key" confirm-variant="danger" confirm-icon="trash">
                            <x-button type="button" variant="danger-soft" size="sm" icon="trash">Delete Stored Key</x-button>
                        </x-confirm-action>
                    @endif
                </div>
            </x-card>

            <x-card title="Steam Workshop" icon="key"
                    subtitle="For ARK, Counter-Strike and the other Steam games. A key is only needed to SEARCH.">
                <div class="space-y-5">
                    <x-alert type="info" title="Installing By Id Needs No Key">
                        Pasting a Workshop id or URL works without any key at all, which is the normal path: people find
                        items on the Workshop website and arrive with a link. A Steam Web API key only adds the search box.
                    </x-alert>

                    <x-field label="Steam Web API Key" for="mods_steam_key"
                             :error="$errors->first('mods_steam_key')"
                             hint="{{ $hasSteam ? 'A key is stored. Leave this blank to keep it.' : 'Free from steamcommunity.com/dev/apikey.' }}">
                        <x-input id="mods_steam_key" name="mods_steam_key" type="password"
                                 autocomplete="new-password" data-lpignore="true"
                                 placeholder="{{ $hasSteam ? 'Stored. Type a new key to replace it.' : 'A 32 character hex string' }}" />
                    </x-field>

                    @if ($hasSteam)
                        <x-confirm-action name="clear-steam-key" tone="danger"
                                          :action="route('settings.mods.clear', 'steam')" method="POST"
                                          title="Delete The Stored Steam Key?"
                                          message="Workshop items already installed stay where they are, and installing by id keeps working. Only the search box goes away."
                                          confirm="Delete Key" confirm-variant="danger" confirm-icon="trash">
                            <x-button type="button" variant="danger-soft" size="sm" icon="trash">Delete Stored Key</x-button>
                        </x-confirm-action>
                    @endif
                </div>

                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-button variant="secondary" href="{{ route('settings.index') }}">Cancel</x-button>
                        <x-button type="submit" icon="check">Save</x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-layouts.app>
