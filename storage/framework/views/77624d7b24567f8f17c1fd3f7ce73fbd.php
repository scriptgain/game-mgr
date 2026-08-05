<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['align' => 'right', 'width' => 'w-48']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['align' => 'right', 'width' => 'w-48']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $alignment = $align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right';
?>
<div x-data="{ open: false }" class="relative" @click.outside="open = false">
    <div @click="open = !open">
        <?php echo e($trigger); ?>

    </div>
    <div x-show="open" x-cloak x-transition
         class="absolute z-40 mt-2 <?php echo e($width); ?> <?php echo e($alignment); ?> rounded-lg bg-white shadow-lg ring-1 ring-slate-200 py-1"
         @click="open = false">
        <?php echo e($slot); ?>

    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/dropdown.blade.php ENDPATH**/ ?>