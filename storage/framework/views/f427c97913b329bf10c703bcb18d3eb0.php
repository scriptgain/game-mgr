<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'name',
    'action',
    'method' => 'POST',
    'title' => 'Are You Sure?',
    'message' => '',
    'confirm' => 'Confirm',
    'confirmIcon' => null,
    'confirmVariant' => 'primary',
    'tone' => 'default',
    // Extra payload for the confirm form, as name => value. Lets one endpoint
    // back several buttons (e.g. which repository, which kind of task) without
    // the caller hand-rolling a form outside the modal.
    'fields' => [],
    // Shown in place of the label once the action is on its way.
    'working' => 'Working',
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
    'name',
    'action',
    'method' => 'POST',
    'title' => 'Are You Sure?',
    'message' => '',
    'confirm' => 'Confirm',
    'confirmIcon' => null,
    'confirmVariant' => 'primary',
    'tone' => 'default',
    // Extra payload for the confirm form, as name => value. Lets one endpoint
    // back several buttons (e.g. which repository, which kind of task) without
    // the caller hand-rolling a form outside the modal.
    'fields' => [],
    // Shown in place of the label once the action is on its way.
    'working' => 'Working',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    // match, not an array lookup with ??. The null coalesce binds to the whole
    // concatenation rather than to the array access, so an unknown variant
    // threw "Undefined array key" instead of falling back, and took every page
    // carrying a confirm button with it.
    $confirmTone = match ($confirmVariant) {
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-200 hover:bg-slate-50',
        default => 'bg-brand-600 text-white hover:bg-brand-700',
    };
    $confirmClasses = 'inline-flex items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed '.$confirmTone;
?>


<span x-data
      @click="$el.querySelector('[disabled], [aria-disabled=&quot;true&quot;]') || $dispatch('open-modal', '<?php echo e($name); ?>')"
      class="inline-flex"><?php echo e($slot); ?></span>

<?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => $name,'title' => $title,'icon' => $tone === 'danger' ? 'warning' : 'info','tone' => $tone,'maxWidth' => 'max-w-md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title),'icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tone === 'danger' ? 'warning' : 'info'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tone),'maxWidth' => 'max-w-md']); ?>
    <?php echo e($message); ?>

     <?php $__env->slot('footer', null, []); ?> 
        
        <div x-data="{ busy: false }" class="flex items-center gap-2">
            <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'secondary','size' => 'sm','xOn:click' => '$dispatch(\'close-modal\', \''.e($name).'\')',':disabled' => 'busy']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','size' => 'sm','x-on:click' => '$dispatch(\'close-modal\', \''.e($name).'\')',':disabled' => 'busy']); ?>Cancel <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
            <form method="POST" action="<?php echo e($action); ?>" @submit="busy = true">
                <?php echo csrf_field(); ?>
                <?php if($method !== 'POST'): ?><?php echo method_field($method); ?><?php endif; ?>
                <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fieldName => $fieldValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <input type="hidden" name="<?php echo e($fieldName); ?>" value="<?php echo e($fieldValue); ?>">
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                
                <button type="submit" x-bind:disabled="busy"
                        class="<?php echo e($confirmClasses); ?>">
                    
                    <span x-show="busy" x-cloak style="display: none" class="inline-flex">
                        
                        <svg class="gm-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>
                    <?php if($confirmIcon): ?>
                        <span x-show="! busy"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $confirmIcon,'class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($confirmIcon),'class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
                    <?php endif; ?>
                    <span x-text="busy ? '<?php echo e($working); ?>' : '<?php echo e($confirm); ?>'"><?php echo e($confirm); ?></span>
                </button>
            </form>
        </div>
     <?php $__env->endSlot(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/components/confirm-action.blade.php ENDPATH**/ ?>