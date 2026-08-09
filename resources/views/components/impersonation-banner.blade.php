@php
    // Sticky and loud on purpose. The whole risk of acting as somebody else is
    // forgetting that you are, then doing something destructive believing it
    // was your own account. This sits above everything on every page until it
    // is ended.
    $impersonatorId = session('impersonator_id');
    $impersonator = $impersonatorId ? \App\Models\User::find($impersonatorId) : null;
@endphp

@if ($impersonator && auth()->check())
    <div class="sticky top-0 z-50 border-b border-amber-300 bg-amber-100">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-6 lg:px-8">
            <p class="flex min-w-0 items-center gap-2 text-sm text-amber-900">
                <x-icon name="eye" class="h-4 w-4 shrink-0" />
                <span class="min-w-0">
                    You are acting as <span class="font-semibold">{{ auth()->user()->name }}</span>.
                    <span class="hidden sm:inline">Everything you do is recorded against {{ $impersonator->name }}.</span>
                </span>
            </p>
            <form method="POST" action="{{ route('act-as.stop') }}" class="shrink-0">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-400 bg-white px-3 py-1.5 text-sm font-medium text-amber-900 transition hover:border-amber-500 hover:bg-amber-50">
                    <x-icon name="x" class="h-3.5 w-3.5" />
                    Back To My Account
                </button>
            </form>
        </div>
    </div>
@endif
