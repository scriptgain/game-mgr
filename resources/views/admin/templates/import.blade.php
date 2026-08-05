<x-layouts.app :title="$title">
    <x-page-header title="Import A Template" icon="download"
                   subtitle="Paste an existing template definition and it becomes a GameMGR template, variables and install script included." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.templates.import.store') }}" enctype="multipart/form-data">
                @csrf
                <x-card title="Definition">
                    <div class="space-y-4">
                        <x-field label="Paste The JSON" hint="The whole file, exactly as exported. Both common format versions work.">
                            <textarea name="json" rows="14" spellcheck="false" placeholder='{ "meta": { "version": "..." }, "name": "Paper", "startup": "java -jar server.jar", ... }'
                                      class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-xs text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('json') }}</textarea>
                        </x-field>

                        <div class="relative">
                            <div class="section-divider"></div>
                            <span class="absolute left-1/2 -translate-x-1/2 -top-2.5 bg-white px-3 text-xs uppercase tracking-wide text-slate-400">or</span>
                        </div>

                        <x-field label="Upload A File" hint="A .json export, up to 4 MB.">
                            <input type="file" name="file" accept=".json,application/json"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                        </x-field>

                        <x-field label="Import Into" hint="Leave on automatic and the game is worked out from the template's name.">
                            <x-select name="game_id">
                                <option value="">Work it out automatically</option>
                                @foreach ($games as $game)
                                    <option value="{{ $game->id }}">{{ $game->name }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>
                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2">
                            <x-button href="{{ route('admin.templates.index') }}" variant="secondary" size="sm">Cancel</x-button>
                            <x-button type="submit" size="sm" icon="download">Import Template</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>

        <div class="space-y-6">
            <x-card title="What Gets Imported">
                <ul class="space-y-3 text-sm text-slate-600">
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> Name, author and description.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> Container images, including the label to image map.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> The startup command and its done marker.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> Config file parsers, kept verbatim.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> Every variable, with its rules and visibility flags.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> The install script and its container.</li>
                </ul>
            </x-card>

            <x-card title="What It Works Out For You">
                <ul class="space-y-3 text-sm text-slate-600">
                    <li class="flex gap-2.5"><x-icon name="sparkles" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" /> Which game it belongs to, from its name.</li>
                    <li class="flex gap-2.5"><x-icon name="sparkles" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" /> The Steam app id, dug out of the install script.</li>
                    <li class="flex gap-2.5"><x-icon name="sparkles" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" /> Whether RCON and a query protocol apply.</li>
                    <li class="flex gap-2.5"><x-icon name="sparkles" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" /> Which mod catalogues to search for it.</li>
                    <li class="flex gap-2.5"><x-icon name="sparkles" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" /> Whether it would run better on the native SteamCMD runtime, which it tells you about rather than changing behind your back.</li>
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
