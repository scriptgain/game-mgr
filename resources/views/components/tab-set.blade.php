@props(['tabs', 'active', 'label' => 'Sections'])
{{-- In-page tabs.

     x-server-tabs is a strip of links to OTHER pages. This one switches panes
     on the page you are already on, which is how a detail page stops being one
     long scroll. Panes are <x-tab-pane> children of this component's slot and
     read the active id back with @aware, so a call site names the default once.

     Visibility is a plain CSS class toggled by :class, not x-show. The default
     pane is therefore already visible in the server-rendered HTML: no x-cloak,
     no blank frame while the Alpine CDN script is still in flight, and the page
     still reads if Alpine never arrives at all.

     $tabs is a list of ['id' => .., 'label' => .., 'icon' => .., 'count' => ..].
     A null entry is dropped, so a call site can make a tab conditional inline.

     Nothing scrolls sideways: the strip wraps onto a second row on a phone
     rather than growing past its box. --}}
@php
    $tabs = array_values(array_filter($tabs));
    $ids = array_column($tabs, 'id');
@endphp

<style>
    /* Plain CSS, not Tailwind: is-active is toggled by JS and a purged build
       has no way to know the class was ever used. */
    .gm-itabs { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; min-width: 0; }
    .gm-itab { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border-radius: .5rem;
               font-size: .875rem; font-weight: 500; color: #475569; white-space: nowrap; background: none;
               border: 1px solid transparent; cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
    /* The border is always there, only its colour changes, so hovering never
       nudges the row. */
    .gm-itab:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
    .gm-itab.is-active { background: var(--color-brand-100, #ede9fe); color: var(--color-brand-700, #5b21b6);
                         border-color: var(--color-brand-200, #ddd6fe); font-weight: 600; }
    .gm-itab svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
    .gm-itab-count { font-size: .6875rem; font-weight: 600; line-height: 1.25rem; min-width: 1.25rem; text-align: center;
                     border-radius: 9999px; padding: 0 .375rem; background: #e2e8f0; color: #475569;
                     font-variant-numeric: tabular-nums; }
    .gm-itab.is-active .gm-itab-count { background: var(--color-brand-200, #ddd6fe); color: var(--color-brand-800, #4c1d95); }
    .gm-pane { display: none; }
    .gm-pane.is-active { display: block; }
    .gm-pane:focus { outline: none; }
</style>

<div {{ $attributes->merge(['class' => 'min-w-0']) }} x-data="tabSet(@js($active), @js($ids))">
    <div class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm px-2 py-1.5">
        <nav class="gm-itabs" role="tablist" aria-label="{{ $label }}" @keydown="onKey($event)">
            @foreach ($tabs as $tab)
                <button type="button" role="tab"
                        id="tab-{{ $tab['id'] }}"
                        data-tab-id="{{ $tab['id'] }}"
                        aria-controls="pane-{{ $tab['id'] }}"
                        aria-selected="{{ $active === $tab['id'] ? 'true' : 'false' }}"
                        :aria-selected="(tab === @js($tab['id'])).toString()"
                        class="gm-itab {{ $active === $tab['id'] ? 'is-active' : '' }}"
                        :class="{ 'is-active': tab === @js($tab['id']) }"
                        @click="select(@js($tab['id']))">
                    @if (! empty($tab['icon']))<x-icon :name="$tab['icon']" />@endif
                    <span>{{ $tab['label'] }}</span>
                    @if (isset($tab['count']))<span class="gm-itab-count">{{ $tab['count'] }}</span>@endif
                </button>
            @endforeach
        </nav>
    </div>

    <div class="mt-6 min-w-0">
        {{ $slot }}
    </div>
</div>
