<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'model',
    'name' => null,
    'title' => null,
    'description' => null,
    'icon' => null,
    'switchLabel' => null,
    'value' => 1,
]));

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

foreach (array_filter(([
    'model',
    'name' => null,
    'title' => null,
    'description' => null,
    'icon' => null,
    'switchLabel' => null,
    'value' => 1,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php $label = $switchLabel ?: $title; ?>
<div @click="<?php echo e($model); ?> = ! <?php echo e($model); ?>"
     class="group relative flex h-full cursor-pointer flex-col rounded-xl p-4 ring-1 ring-inset transition"
     :class="<?php echo e($model); ?>

        ? 'bg-brand-50/60 ring-brand-300 shadow-sm'
        : 'bg-white ring-slate-200 hover:ring-slate-300'">
    <?php if($name): ?><input type="hidden" name="<?php echo e($name); ?>" :value="<?php echo e($model); ?> ? '<?php echo e($value); ?>' : 0"><?php endif; ?>

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <?php if(isset($heading)): ?>
                <?php echo e($heading); ?>

            <?php elseif($title): ?>
                <div class="flex items-center gap-2 min-w-0">
                    <?php if($icon): ?>
                        <span class="inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg ring-1 transition"
                              :class="<?php echo e($model); ?> ? 'bg-brand-100 text-brand-700 ring-brand-200' : 'bg-slate-100 text-slate-500 ring-slate-200'">
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
                        </span>
                    <?php endif; ?>
                    <p class="text-sm font-semibold text-slate-900 min-w-0"><?php echo e($title); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <button type="button" role="switch"
                :aria-checked="(<?php echo e($model); ?>).toString()"
                <?php if($label): ?> aria-label="<?php echo e($label); ?>" <?php endif; ?>
                @click.stop="<?php echo e($model); ?> = ! <?php echo e($model); ?>"
                :class="<?php echo e($model); ?> ? 'bg-brand-600' : 'bg-slate-300'"
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
            <span :class="<?php echo e($model); ?> ? 'translate-x-6' : 'translate-x-1'"
                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
        </button>
    </div>

    <?php if($description): ?>
        <p class="mt-2 text-sm text-slate-500"><?php echo e($description); ?></p>
    <?php endif; ?>

    <?php if(trim($slot) !== ''): ?>
        <div class="mt-auto pt-3"><?php echo e($slot); ?></div>
    <?php endif; ?>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/node-switch-card.blade.php ENDPATH**/ ?>