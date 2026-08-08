<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label' => null, 'code' => null, 'empty' => 'Not set.', 'tall' => false]));

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

foreach (array_filter((['label' => null, 'code' => null, 'empty' => 'Not set.', 'tall' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php $code = trim((string) $code); ?>

<style>
    .gm-code-bar { display: flex; align-items: center; justify-content: space-between; gap: .75rem;
                   padding: .5rem .625rem .5rem .875rem; border-bottom: 1px solid rgba(255, 255, 255, .08); }
    .gm-code-title { font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
                     color: #94a3b8; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gm-code-copy { display: inline-flex; align-items: center; gap: .375rem; flex: 0 0 auto; padding: .25rem .625rem;
                    border-radius: .375rem; font-size: .75rem; font-weight: 500; color: #cbd5e1; cursor: pointer;
                    background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .08);
                    transition: background .15s, color .15s, border-color .15s; }
    .gm-code-copy:hover { background: rgba(255, 255, 255, .12); color: #fff; border-color: rgba(255, 255, 255, .28); }
    .gm-code-copy svg { width: .875rem; height: .875rem; }
    /* overflow-wrap:anywhere, not just white-space:pre-wrap. A 60 character
       path or an unbroken ${VAR:-default} run has no space to break at, and one
       such line is enough to set a horizontal floor for the entire page. */
    .gm-code-pre { margin: 0; padding: .75rem .875rem; max-height: 17rem; overflow-y: auto; overflow-x: hidden;
                   white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; tab-size: 2; }
    .gm-code-pre.is-tall { max-height: 26rem; }
</style>

<?php if($code === ''): ?>
    <div <?php echo e($attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500'])); ?>>
        <?php echo e($empty); ?>

    </div>
<?php else: ?>
    <div <?php echo e($attributes->merge(['class' => 'console-pane overflow-hidden'])); ?> x-data="copyPane">
        <div class="gm-code-bar">
            <span class="gm-code-title"><?php echo e($label ?: 'Command'); ?></span>
            <button type="button" class="gm-code-copy" @click="copy()">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'copy','xShow' => '! copied']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'copy','x-show' => '! copied']); ?>
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
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'check','xShow' => 'copied','xCloak' => true,'class' => 'text-emerald-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','x-show' => 'copied','x-cloak' => true,'class' => 'text-emerald-400']); ?>
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
                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
            </button>
        </div>
        <pre class="gm-code-pre vx-scroll <?php echo e($tall ? 'is-tall' : ''); ?>" x-ref="pane"><?php echo e($code); ?></pre>
    </div>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/components/code-pane.blade.php ENDPATH**/ ?>