@props(['value' => null, 'all' => false, 'label' => null])
{{-- Row selection. A toggle switch, never a bare checkbox, per house style.

     The switch is a visually hidden checkbox whose track and knob are drawn
     purely from :checked in CSS (.vx-switch in resources/css/app.css), so it
     needs no JavaScript of its own. It carries no name: x-mass-actions copies
     the checked values into its form at submit time, which keeps the DOM the
     only place that knows what is selected. --}}
@if ($all)
    <label class="vx-switch">
        <input type="checkbox" x-ref="selectAll" @change="toggleAll($event.target.checked)" :checked="everything">
        <span class="vx-switch-track"><span class="vx-switch-knob"></span></span>
        <span class="sr-only">Select Everything On This Page</span>
    </label>
@else
    <label class="vx-switch">
        <input type="checkbox" value="{{ $value }}" data-select-row>
        <span class="vx-switch-track"><span class="vx-switch-knob"></span></span>
        <span class="sr-only">Select {{ $label ?? 'This Row' }}</span>
    </label>
@endif
