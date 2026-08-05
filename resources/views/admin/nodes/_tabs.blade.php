@props(['node'])
{{-- Node sub-navigation. Six items fit comfortably on a phone, so this stays a
     simple strip rather than needing the overflow dropdown the server tabs use. --}}
@php
    $tabs = [
        ['Overview', route('admin.nodes.show', $node), 'dashboard', request()->routeIs('admin.nodes.show')],
        ['Allocations', route('admin.nodes.allocations', $node), 'network', request()->routeIs('admin.nodes.allocations')],
        ['Metrics', route('admin.nodes.metrics', $node), 'chart', request()->routeIs('admin.nodes.metrics')],
        ['Enrol', route('admin.nodes.enrol', $node), 'key', request()->routeIs('admin.nodes.enrol')],
        ['Configuration', route('admin.nodes.edit', $node), 'settings', request()->routeIs('admin.nodes.edit')],
    ];
@endphp
<div class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm px-2 py-1.5 mb-6">
    <nav class="flex items-center gap-1 flex-wrap" aria-label="Node sections">
        @foreach ($tabs as [$label, $href, $icon, $active])
            <a href="{{ $href }}" @class([
                'inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition border',
                'bg-brand-50 text-brand-700 border-brand-200' => $active,
                'text-slate-600 border-transparent hover:bg-slate-100 hover:text-slate-900 hover:border-slate-200' => ! $active,
            ])>
                <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
