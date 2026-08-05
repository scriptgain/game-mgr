<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'value', 'icon' => null, 'trend' => null, 'trendColor' => 'success']));

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

foreach (array_filter((['label', 'value', 'icon' => null, 'trend' => null, 'trendColor' => 'success']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $trendClasses = [
        'success' => 'text-emerald-600',
        'danger' => 'text-rose-600',
        'neutral' => 'text-slate-500',
    ][$trendColor] ?? 'text-slate-500';
?>
<div <?php echo e($attributes->merge(['class' => 'bg-white rounded-xl ring-1 ring-slate-200 shadow-sm p-5'])); ?>>
    <div class="flex items-center gap-3">
        <?php if($icon): ?>
            
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-5 h-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-5 h-5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
            </span>
        <?php endif; ?>
        <p class="text-sm font-medium text-slate-500"><?php echo e($label); ?></p>
    </div>
    <div class="mt-3 flex items-baseline gap-2">
        <span class="text-2xl font-semibold text-slate-900 tabular"><?php echo e($value); ?></span>
        <?php if($trend): ?><span class="text-xs font-medium <?php echo e($trendClasses); ?>"><?php echo e($trend); ?></span><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/stat.blade.php ENDPATH**/ ?>