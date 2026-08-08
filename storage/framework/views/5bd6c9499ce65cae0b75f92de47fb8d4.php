<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tabs', 'active', 'label' => 'Sections']));

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

foreach (array_filter((['tabs', 'active', 'label' => 'Sections']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $tabs = array_values(array_filter($tabs));
    $ids = array_column($tabs, 'id');
?>

<style>
    /* Plain CSS, not Tailwind: is-active is toggled by JS and a purged build
       has no way to know the class was ever used. */
    .gm-itabs { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; min-width: 0; }
    .gm-itab { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border-radius: .5rem;
               font-size: .875rem; font-weight: 500; color: #475569; white-space: nowrap; background: none;
               border: 1px solid transparent; cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
    /* The border is always there, only its colour changes, so hovering never
       nudges the row. */
    .gm-itab:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
    .gm-itab.is-active { background: var(--color-brand-100, #ede9fe); color: var(--color-brand-700, #5b21b6);
                         border-color: var(--color-brand-200, #ddd6fe); font-weight: 600; }
    .gm-itab svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
    .gm-itab-count { font-size: .6875rem; font-weight: 600; line-height: 1.25rem; min-width: 1.25rem; text-align: center;
                     border-radius: 9999px; padding: 0 .375rem; background: #e2e8f0; color: #475569;
                     font-variant-numeric: tabular-nums; }
    .gm-itab.is-active .gm-itab-count { background: var(--color-brand-200, #ddd6fe); color: var(--color-brand-800, #4c1d95); }
    .gm-pane { display: none; }
    .gm-pane.is-active { display: block; }
    .gm-pane:focus { outline: none; }
</style>

<div <?php echo e($attributes->merge(['class' => 'min-w-0'])); ?> x-data="tabSet(<?php echo \Illuminate\Support\Js::from($active)->toHtml() ?>, <?php echo \Illuminate\Support\Js::from($ids)->toHtml() ?>)">
    <div class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm px-2 py-1.5">
        <nav class="gm-itabs" role="tablist" aria-label="<?php echo e($label); ?>" @keydown="onKey($event)">
            <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button type="button" role="tab"
                        id="tab-<?php echo e($tab['id']); ?>"
                        data-tab-id="<?php echo e($tab['id']); ?>"
                        aria-controls="pane-<?php echo e($tab['id']); ?>"
                        aria-selected="<?php echo e($active === $tab['id'] ? 'true' : 'false'); ?>"
                        :aria-selected="(tab === <?php echo \Illuminate\Support\Js::from($tab['id'])->toHtml() ?>).toString()"
                        class="gm-itab <?php echo e($active === $tab['id'] ? 'is-active' : ''); ?>"
                        :class="{ 'is-active': tab === <?php echo \Illuminate\Support\Js::from($tab['id'])->toHtml() ?> }"
                        @click="select(<?php echo \Illuminate\Support\Js::from($tab['id'])->toHtml() ?>)">
                    <?php if(! empty($tab['icon'])): ?><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $tab['icon']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tab['icon'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><?php endif; ?>
                    <span><?php echo e($tab['label']); ?></span>
                    <?php if(isset($tab['count'])): ?><span class="gm-itab-count"><?php echo e($tab['count']); ?></span><?php endif; ?>
                </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>
    </div>

    <div class="mt-6 min-w-0">
        <?php echo e($slot); ?>

    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/tab-set.blade.php ENDPATH**/ ?>