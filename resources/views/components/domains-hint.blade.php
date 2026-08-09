@props(['server' => null])
@php
    use App\Services\Dns\DnsConfig;
    use Illuminate\Support\Facades\Route as RouteFacade;

    // Only an admin, because only an admin can act on any of this. A customer
    // reading "turn names on in Settings" is being pointed at a door they
    // cannot open, which is worse than the bare address they already have.
    $show = auth()->check() && auth()->user()->isAdmin()
        && RouteFacade::has('settings.domains.edit')
        && ! ($server?->connectName());

    $node = $server?->node;
    $link = null;
    $message = null;

    if ($show && ! DnsConfig::active()) {
        $message = 'Connection names are off, so this shows the direct address only.';
        $link = ['Settings, Domains', route('settings.domains.edit')];
    } elseif ($show && ! DnsConfig::ready()) {
        $message = 'Connection names are on but nothing can write them, so this shows the direct address only.';
        $link = ['Settings, Domains', route('settings.domains.edit')];
    } elseif ($show && $node && ! $node->dns_label) {
        $message = 'This server is on '.$node->name.', which has no label, so it hands out no names.';
        $link = RouteFacade::has('admin.nodes.edit')
            ? ['Give It One', route('admin.nodes.edit', $node)]
            : null;
    } elseif ($show) {
        $message = 'No name has been built for this server yet. The hourly sync will add one.';
        $link = ['Settings, Domains', route('settings.domains.edit')];
    }
@endphp
{{-- A feature that is switched off should say so where somebody would look for
     it, rather than vanish. Without this line a server shows a bare IP and no
     hint that a name is available five minutes away.

     One line, not an alert box: it sits inside cards that already have a job,
     and the direct address above it is not a problem to be warned about. --}}
@if ($message)
    <p {{ $attributes->merge(['class' => 'mt-3 flex items-start gap-1.5 text-xs text-slate-500']) }}>
        <x-icon name="globe" class="w-3.5 h-3.5 shrink-0 mt-px text-slate-400" />
        <span class="min-w-0">
            {{ $message }}
            @if ($link)
                <a href="{{ $link[1] }}" class="font-medium text-slate-600 underline hover:text-slate-900">{{ $link[0] }}</a>.
            @endif
        </span>
    </p>
@endif
