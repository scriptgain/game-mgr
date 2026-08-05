<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['type' => 'text']));

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

foreach (array_filter((['type' => 'text']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<input type="<?php echo e($type); ?>"
    <?php echo e($attributes->merge(['class' =>
        'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 '
        . 'ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 '
        . 'focus:ring-2 focus:ring-inset focus:ring-brand-500 disabled:opacity-60 disabled:bg-slate-50'])); ?>>
<?php /**PATH /var/www/gamemgr/resources/views/components/input.blade.php ENDPATH**/ ?>