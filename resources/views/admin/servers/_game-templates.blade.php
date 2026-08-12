@php
    /** The templates belonging to one game, fetched when that game is picked.
     *
     * Rendered separately because the create page used to hold every template
     * on the panel at once. One game has one to three of these; the catalogue
     * has two hundred and fifty nine.
     */
@endphp
<div role="radiogroup" aria-label="{{ $game->name }} templates"
     class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($templates as $template)
        <label class="gm-pick group relative flex cursor-pointer gap-3 rounded-xl bg-white p-3 ring-1 ring-inset transition"
               :class="templateId === '{{ $template->id }}'
                   ? 'ring-2 ring-brand-500 bg-brand-50/60 shadow-sm'
                   : 'ring-slate-200 hover:ring-brand-300 hover:shadow-sm'">
            <input type="radio" name="template_id" value="{{ $template->id }}" x-model="templateId" class="peer sr-only">
            <x-game-art :game="$game" class="h-11 w-11 rounded-lg" icon-class="w-5 h-5" />
            <span class="min-w-0 flex-1">
                <span class="block truncate pe-5 text-sm font-semibold text-slate-900">{{ $template->name }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">
                    <x-runtime-badge :runtime="$template->runtime" />
                    @if ($template->requires_steam_account)
                        <span class="ms-1 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">Steam Account</span>
                    @endif
                </span>
                @if ($template->description)
                    <span class="mt-1 line-clamp-2 block text-xs text-slate-500">{{ $template->description }}</span>
                @endif
            </span>
            <x-icon name="check-circle" x-show="templateId === '{{ $template->id }}'" x-cloak
                    class="absolute end-2 top-2 h-4 w-4 text-brand-600" />
        </label>
    @endforeach
</div>
