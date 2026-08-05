@props(['name', 'value' => '1', 'checked' => false, 'icon' => null])
{{-- A real toggle switch that submits natively, with no JavaScript. Wraps a
     visually hidden checkbox; the visible track and knob are driven purely by
     :checked in CSS, so it works in multi-select groups (name="x[]").
     The .vx-switch styles live in resources/css/app.css. --}}
<label {{ $attributes->merge(['class' => 'vx-switch']) }}>
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked)>
    <span class="vx-switch-track"><span class="vx-switch-knob"></span></span>
    @if ($icon)<x-icon :name="$icon" />@endif
    <span>{{ $slot }}</span>
</label>
