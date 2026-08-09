@php
    $ask = auth()->check() && auth()->user()->isAdmin()
        && \Illuminate\Support\Facades\Route::has('settings.telemetry.edit')
        && ! \App\Services\Telemetry::acknowledged();
@endphp
{{-- Asked once, rather than assumed.

     Telemetry defaults to on, which is only defensible if the first admin to
     log in is told so plainly and can turn it off from the same line. Both
     buttons record the answer, so this never appears again either way. --}}
@if ($ask)
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
        <p class="text-sm text-slate-700">
            <span class="font-semibold">This install sends anonymous counts to ScriptGain.</span>
            How many servers and nodes, the version, and which runtimes. Never a name, an address or an email.
            <a href="{{ route('settings.telemetry.edit') }}" class="font-medium text-brand-700 underline">See exactly what goes</a>.
        </p>
        <div class="flex shrink-0 items-center gap-2">
            <form method="POST" action="{{ route('settings.telemetry.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="telemetry_enabled" value="0">
                <x-button type="submit" variant="secondary" size="sm">Turn It Off</x-button>
            </form>
            <form method="POST" action="{{ route('settings.telemetry.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="telemetry_enabled" value="1">
                <x-button type="submit" size="sm" icon="check">Keep It On</x-button>
            </form>
        </div>
    </div>
@endif
