@props(['id'])
@aware(['active'])
{{-- One pane of an <x-tab-set>. The active id comes from the parent with
     @aware, so the call site states the default exactly once. --}}
<section id="pane-{{ $id }}" role="tabpanel" aria-labelledby="tab-{{ $id }}" tabindex="0"
         {{ $attributes->merge(['class' => 'gm-pane min-w-0 space-y-6 '.($active === $id ? 'is-active' : '')]) }}
         :class="{ 'is-active': tab === @js($id) }">
    {{ $slot }}
</section>
