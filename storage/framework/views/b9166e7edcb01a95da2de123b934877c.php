<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => null, 'subtitle' => null, 'icon' => null, 'padding' => 'p-5 sm:p-6', 'flush' => false]));

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

foreach (array_filter((['title' => null, 'subtitle' => null, 'icon' => null, 'padding' => 'p-5 sm:p-6', 'flush' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
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
                <?php if($title): ?>
                    <h3 class="flex items-center gap-2 text-[15px] font-semibold text-slate-900">
                        <?php if($icon): ?>
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-500">
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-3.5 h-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-3.5 h-3.5']); ?>
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
                        <?php echo e($title); ?>

                    </h3>
                <?php endif; ?>
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