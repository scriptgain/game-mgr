<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['href' => null, 'icon', 'title' => null, 'variant' => 'secondary', 'type' => 'button']));

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

foreach (array_filter((['href' => null, 'icon', 'title' => null, 'variant' => 'secondary', 'type' => 'button']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $variants = [
        'secondary' => 'bg-white ring-1 ring-inset ring-slate-300 text-slate-600 hover:bg-slate-50 hover:text-slate-900',
        'ghost' => 'text-slate-500 hover:bg-slate-100 hover:text-slate-900',
        'danger' => 'bg-white ring-1 ring-inset ring-rose-200 text-rose-600 hover:bg-rose-50 hover:ring-rose-300',
        'brand' => 'bg-brand-50 ring-1 ring-inset ring-brand-200 text-brand-700 hover:bg-brand-100',
    ];
    $classes = 'inline-flex items-center justify-center w-9 h-9 rounded-lg transition ' . ($variants[$variant] ?? $variants['secondary']);
?>

<span class="inline-flex"
    <?php if($title): ?>
        x-data="{ tip: false, tx: 0, ty: 0 }"
        @mouseenter="const r = $el.getBoundingClientRect(); tx = r.left + r.width / 2; ty = r.top - 8; tip = true"
        @mouseleave="tip = false"
    <?php endif; ?>>
    <?php if($href): ?>
        <a href="<?php echo e($href); ?>" <?php if($title): ?> aria-label="<?php echo e($title); ?>" <?php endif; ?> <?php echo e($attributes->merge(['class' => $classes])); ?>>
            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-4 h-4']); ?>
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
        </a>
    <?php else: ?>
        <button type="<?php echo e($type); ?>" <?php if($title): ?> aria-label="<?php echo e($title); ?>" <?php endif; ?> <?php echo e($attributes->merge(['class' => $classes])); ?>>
            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-4 h-4']); ?>
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
        </button>
    <?php endif; ?>
    <?php if($title): ?>
        <template x-teleport="body">
            <div x-show="tip" x-cloak :style="`left:${tx}px;top:${ty}px`"
                class="fixed -translate-x-1/2 -translate-y-full pointer-events-none z-[100] whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">
                <?php echo e($title); ?>

            </div>
        </template>
    <?php endif; ?>
</span>
<?php /**PATH /var/www/gamemgr/resources/views/components/icon-button.blade.php ENDPATH**/ ?>