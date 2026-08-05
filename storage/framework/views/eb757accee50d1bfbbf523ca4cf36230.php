<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['color' => 'neutral', 'dot' => false]));

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

foreach (array_filter((['color' => 'neutral', 'dot' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $map = [
        'neutral' => ['bg-slate-100 text-slate-700 ring-slate-200', 'bg-slate-400'],
        'info' => ['bg-brand-50 text-brand-700 ring-brand-200', 'bg-brand-500'],
        'success' => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'bg-emerald-500'],
        'warn' => ['bg-amber-50 text-amber-700 ring-amber-200', 'bg-amber-500'],
        'danger' => ['bg-rose-50 text-rose-700 ring-rose-200', 'bg-rose-500'],
    ];
    [$chip, $dotColor] = $map[$color] ?? $map['neutral'];
?>
<span <?php echo e($attributes->merge(['class' => "vx-badge inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset $chip"])); ?>>
    <?php if($dot): ?><span class="w-1.5 h-1.5 rounded-full <?php echo e($dotColor); ?>"></span><?php endif; ?>
    <?php echo e($slot); ?>

</span>
<?php /**PATH /var/www/gamemgr/resources/views/components/badge.blade.php ENDPATH**/ ?>