<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'title' => null, 'subtitle' => null, 'icon' => null, 'tone' => 'default', 'maxWidth' => 'max-w-lg']));

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

foreach (array_filter((['name', 'title' => null, 'subtitle' => null, 'icon' => null, 'tone' => 'default', 'maxWidth' => 'max-w-lg']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $toneChip = [
        'default' => 'bg-white text-brand-600 ring-brand-200',
        'danger' => 'bg-white text-rose-600 ring-rose-200',
        'warn' => 'bg-white text-amber-600 ring-amber-200',
    ][$tone] ?? 'bg-white text-brand-600 ring-brand-200';
    $toneHead = [
        'default' => 'from-brand-50',
        'danger' => 'from-rose-50',
        'warn' => 'from-amber-50',
    ][$tone] ?? 'from-brand-50';
?>

<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '<?php echo e($name); ?>') open = true"
     x-on:close-modal.window="if ($event.detail === '<?php echo e($name); ?>') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition.opacity.duration.200ms
         class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
    <div x-show="open"
         x-trap.inert.noscroll="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="vx-modal relative flex flex-col w-full <?php echo e($maxWidth); ?> max-h-[85vh] bg-white rounded-2xl shadow-2xl ring-1 ring-slate-200 overflow-hidden text-left">
        
        <div class="flex items-start gap-3.5 px-5 py-4 border-b border-slate-100 bg-gradient-to-br <?php echo e($toneHead); ?> via-white to-white shrink-0">
            <?php if($icon): ?>
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl ring-1 shadow-sm shrink-0 <?php echo e($toneChip); ?>">
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
            <div class="min-w-0 flex-1">
                <h3 class="text-xl font-semibold text-slate-900 leading-snug break-words"><?php echo e($title); ?></h3>
                <?php if($subtitle): ?><p class="mt-1 text-sm text-slate-500 leading-relaxed break-words"><?php echo e($subtitle); ?></p><?php endif; ?>
            </div>
            <button type="button" @click="open = false" class="shrink-0 -mr-1 -mt-1 text-slate-400 hover:text-slate-600 rounded-lg p-1">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'x','class' => 'w-5 h-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','class' => 'w-5 h-5']); ?>
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
        </div>
        <div class="vx-wrap vx-scroll flex-1 overflow-y-auto overflow-x-hidden px-5 py-4 text-sm text-slate-600 leading-relaxed">
            <?php echo e($slot); ?>

        </div>
        <?php if(isset($footer)): ?>
            <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-slate-100 bg-slate-50/70 shrink-0">
                <?php echo e($footer); ?>

            </div>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/modal.blade.php ENDPATH**/ ?>