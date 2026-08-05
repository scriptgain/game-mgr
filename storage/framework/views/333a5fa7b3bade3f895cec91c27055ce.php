<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tone' => 'slate', 'label' => null, 'pulse' => false]));

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

foreach (array_filter((['tone' => 'slate', 'label' => null, 'pulse' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $map = [
        'emerald' => ['bg-emerald-500', 'text-emerald-700'],
        'amber' => ['bg-amber-500', 'text-amber-700'],
        'rose' => ['bg-rose-500', 'text-rose-700'],
        'sky' => ['bg-sky-500', 'text-sky-700'],
        'slate' => ['bg-slate-400', 'text-slate-600'],
    ];
    [$dot, $text] = $map[$tone] ?? $map['slate'];
?>
<span <?php echo e($attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm font-medium '.$text])); ?>>
    <span class="relative flex h-2 w-2 shrink-0">
        <?php if($pulse): ?>
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full <?php echo e($dot); ?> opacity-70"></span>
        <?php endif; ?>
        <span class="relative inline-flex h-2 w-2 rounded-full <?php echo e($dot); ?>"></span>
    </span>
    <?php echo e($label ?? $slot); ?>

</span>
<?php /**PATH /var/www/gamemgr/resources/views/components/status-dot.blade.php ENDPATH**/ ?>