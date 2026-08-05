<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['type' => 'info', 'title' => null]));

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

foreach (array_filter((['type' => 'info', 'title' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $map = [
        'info' => ['bg-brand-50 ring-brand-200 text-brand-800', 'text-brand-600', 'info'],
        'success' => ['bg-emerald-50 ring-emerald-200 text-emerald-800', 'text-emerald-600', 'check-circle'],
        'warn' => ['bg-amber-50 ring-amber-200 text-amber-800', 'text-amber-600', 'warning'],
        'danger' => ['bg-rose-50 ring-rose-200 text-rose-800', 'text-rose-600', 'x-circle'],
    ];
    [$box, $iconColor, $icon] = $map[$type] ?? $map['info'];
?>
<div <?php echo e($attributes->merge(['class' => "flex gap-3 rounded-lg ring-1 ring-inset p-4 $box"])); ?> role="alert">
    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-5 h-5 shrink-0 mt-0.5 '.e($iconColor).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-5 h-5 shrink-0 mt-0.5 '.e($iconColor).'']); ?>
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
    <div class="text-sm min-w-0">
        <?php if($title): ?><p class="font-semibold"><?php echo e($title); ?></p><?php endif; ?>
        <div class="<?php echo e($title ? 'mt-0.5 opacity-90' : ''); ?>"><?php echo e($slot); ?></div>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/alert.blade.php ENDPATH**/ ?>