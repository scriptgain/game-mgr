<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => null, 'subtitle' => null, 'padding' => 'p-5 sm:p-6', 'flush' => false]));

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

foreach (array_filter((['title' => null, 'subtitle' => null, 'padding' => 'p-5 sm:p-6', 'flush' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php $body = $flush ? '' : $padding; ?>
<div <?php echo e($attributes->merge(['class' => 'bg-white rounded-xl ring-1 ring-slate-200 shadow-sm' . ($flush ? ' overflow-hidden' : '')])); ?>>
    <?php if($title || isset($actions)): ?>
        <div class="flex items-start justify-between gap-4 px-5 sm:px-6 py-4 border-b border-slate-100">
            <div class="min-w-0">
                <?php if($title): ?><h3 class="text-[15px] font-semibold text-slate-900"><?php echo e($title); ?></h3><?php endif; ?>
                <?php if($subtitle): ?><p class="mt-0.5 text-sm text-slate-500"><?php echo e($subtitle); ?></p><?php endif; ?>
            </div>
            <?php if(isset($actions)): ?>
                <div class="flex items-center gap-2 shrink-0"><?php echo e($actions); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="<?php echo e($body); ?>">
        <?php echo e($slot); ?>

    </div>
    <?php if(isset($footer)): ?>
        <div class="px-5 sm:px-6 py-3 border-t border-slate-100 bg-slate-50/60 rounded-b-xl">
            <?php echo e($footer); ?>

        </div>
    <?php endif; ?>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/card.blade.php ENDPATH**/ ?>