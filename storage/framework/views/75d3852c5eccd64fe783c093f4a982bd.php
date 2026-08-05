<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'required' => false]));

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

foreach (array_filter((['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'required' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div <?php echo e($attributes->merge(['class' => 'space-y-1.5'])); ?>>
    <?php if($label): ?>
        <label <?php if($for): ?> for="<?php echo e($for); ?>" <?php endif; ?> class="block text-sm font-medium text-slate-700">
            <?php echo e($label); ?>

            <?php if($required): ?><span class="text-rose-500">*</span><?php endif; ?>
        </label>
    <?php endif; ?>
    <?php echo e($slot); ?>

    <?php if($error): ?>
        <p class="text-sm text-rose-600"><?php echo e($error); ?></p>
    <?php elseif($hint): ?>
        <p class="text-sm text-slate-500"><?php echo e($hint); ?></p>
    <?php endif; ?>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/field.blade.php ENDPATH**/ ?>