<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['server']));

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

foreach (array_filter((['server']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $installing = $server->status === 'installing';
    $failed = $server->status === 'install_failed';
    $pct = $server->install_progress;
    $phase = $server->install_phase ?: ($installing ? 'Waiting For The Node' : null);
    $log = trim((string) $server->install_log);
?>

<?php if($installing || $failed): ?>
    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => $failed ? 'Install Failed' : 'Installing','icon' => 'download','subtitle' => $failed
                ? 'The server has no game files yet, so it cannot start. The output below is what the node reported.'
                : 'The node is fetching this game. You can leave this page; it keeps going.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($failed ? 'Install Failed' : 'Installing'),'icon' => 'download','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($failed
                ? 'The server has no game files yet, so it cannot start. The output below is what the node reported.'
                : 'The node is fetching this game. You can leave this page; it keeps going.')]); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <?php if($installing): ?>
                <span class="inline-flex items-center gap-2 text-xs text-slate-500">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-brand-500"></span>
                    </span>
                    Live
                </span>
            <?php endif; ?>
         <?php $__env->endSlot(); ?>

        <div class="space-y-4">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm font-medium <?php echo e($failed ? 'text-rose-700' : 'text-slate-700'); ?>">
                    <?php echo e($phase ?? 'Unknown'); ?>

                </span>
                <span class="tabular text-sm text-slate-500">
                    <?php if($pct !== null): ?>
                        <?php echo e($pct); ?>%
                    <?php elseif($installing): ?>
                        In Progress
                    <?php endif; ?>
                </span>
            </div>

            <?php if($pct !== null): ?>
                <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['value' => $pct,'max' => 100,'tone' => $failed ? 'rose' : null]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pct),'max' => 100,'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($failed ? 'rose' : null)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
            <?php else: ?>
                
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full w-1/3 rounded-full <?php echo e($failed ? 'bg-rose-500' : 'bg-brand-500 animate-pulse'); ?>"></div>
                </div>
            <?php endif; ?>

            <?php if($server->install_started_at): ?>
                <p class="text-xs text-slate-500">
                    Started <?php echo e($server->install_started_at->diffForHumans()); ?>.
                    <?php if($installing): ?>
                        Large games can take a while: Palworld is roughly 8 GB and some Source servers are far more.
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if($log !== ''): ?>
                <?php if (isset($component)) { $__componentOriginal766da14cd9bbda5d69a52694b5aff6b7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.code-pane','data' => ['label' => $failed ? 'What The Node Reported' : 'Install Output','code' => $log]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('code-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($failed ? 'What The Node Reported' : 'Install Output'),'code' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($log)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7)): ?>
<?php $attributes = $__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7; ?>
<?php unset($__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal766da14cd9bbda5d69a52694b5aff6b7)): ?>
<?php $component = $__componentOriginal766da14cd9bbda5d69a52694b5aff6b7; ?>
<?php unset($__componentOriginal766da14cd9bbda5d69a52694b5aff6b7); ?>
<?php endif; ?>
            <?php else: ?>
                <p class="text-sm text-slate-500">No output from the node yet.</p>
            <?php endif; ?>

            <?php if($failed): ?>
                <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'warn']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warn']); ?>
                    Nothing is retried automatically, because retrying a multi gigabyte download
                    on its own turns one failure into several. Fix what the output points at, then
                    reinstall from Settings.
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
            <?php endif; ?>
        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $attributes = $__attributesOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__attributesOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $component = $__componentOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__componentOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/components/install-progress.blade.php ENDPATH**/ ?>