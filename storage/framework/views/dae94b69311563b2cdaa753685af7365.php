<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['value' => 0, 'max' => 100, 'label' => null, 'suffix' => '', 'tone' => null]));

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

foreach (array_filter((['value' => 0, 'max' => 100, 'label' => null, 'suffix' => '', 'tone' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $max = max(1, (float) $max);
    $pct = min(100, round((float) $value / $max * 100, 1));
    $tone = $tone ?? match (true) {
        $pct >= 90 => 'rose',
        $pct >= 75 => 'amber',
        default => 'brand',
    };
    $bar = ['brand' => 'bg-brand-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500', 'emerald' => 'bg-emerald-500'][$tone];
?>
<div <?php echo e($attributes->merge(['class' => 'space-y-1.5'])); ?>>
    <?php if($label): ?>
        <div class="flex items-baseline justify-between gap-3 text-sm">
            <span class="font-medium text-slate-700"><?php echo e($label); ?></span>
            <span class="tabular text-slate-500"><?php echo e($slot->isEmpty() ? $pct.'%' : $slot); ?><?php echo e($suffix); ?></span>
        </div>
    <?php endif; ?>
    <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full rounded-full <?php echo e($bar); ?> transition-all" style="width: <?php echo e($pct); ?>%"></div>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/meter.blade.php ENDPATH**/ ?>