<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    // [['label' => 'Identity', 'hint' => 'What it is called', 'icon' => 'book'], ...]
    'steps',
    // Free navigation when editing, forward-only when creating.
    'editing' => false,
    // Which step to open on. The form passes the step holding the first
    // validation error, so a failed save reopens what actually failed.
    'start' => 1,
    'component' => 'formWizard',
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
    // [['label' => 'Identity', 'hint' => 'What it is called', 'icon' => 'book'], ...]
    'steps',
    // Free navigation when editing, forward-only when creating.
    'editing' => false,
    // Which step to open on. The form passes the step holding the first
    // validation error, so a failed save reopens what actually failed.
    'start' => 1,
    'component' => 'formWizard',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $total = count($steps);
?>


<style>
    [x-cloak] { display: none !important; }
    @media (prefers-reduced-motion: reduce) {
        .gm-step, .gm-rail { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
    }
</style>

<div x-data="<?php echo e($component); ?>({ total: <?php echo e($total); ?>, step: <?php echo e((int) $start); ?>, editing: <?php echo e($editing ? 'true' : 'false'); ?> })"
     @keydown.enter="onEnter($event)"
     class="grid gap-6 lg:grid-cols-4 items-start">

    <div class="lg:col-span-3 space-y-6">
        <?php echo e($slot); ?>

    </div>

    <div class="lg:col-span-1">
        <div class="gm-rail rounded-xl bg-white p-4 ring-1 ring-inset ring-slate-200 shadow-sm lg:sticky lg:top-6">
            <div class="mb-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-500 transition-all duration-300" :style="progress()"></div>
            </div>

            <ol class="space-y-0.5">
                <?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $n = $i + 1; ?>
                    <li>
                        
                        <button type="button" @click="go(<?php echo e($n); ?>)" :disabled="! unlocked(<?php echo e($n); ?>)"
                                class="flex w-full items-start gap-2.5 rounded-lg border border-transparent px-2.5 py-2 text-left transition"
                                :class="step === <?php echo e($n); ?>

                                    ? 'bg-brand-50 border-brand-200'
                                    : (unlocked(<?php echo e($n); ?>)
                                        ? 'hover:border-slate-200 hover:bg-slate-50 cursor-pointer'
                                        : 'cursor-not-allowed')">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ring-1 ring-inset transition"
                                  :class="step === <?php echo e($n); ?>

                                      ? 'bg-brand-600 text-white ring-brand-600'
                                      : (step > <?php echo e($n); ?>

                                          ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                          : 'bg-white text-slate-400 ring-slate-200')">
                                <span x-show="step <= <?php echo e($n); ?>"><?php echo e($n); ?></span>
                                <span x-show="step > <?php echo e($n); ?>" x-cloak>&check;</span>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium"
                                      :class="step === <?php echo e($n); ?> ? 'text-brand-700' : (unlocked(<?php echo e($n); ?>) ? 'text-slate-700' : 'text-slate-400')"><?php echo e($step['label']); ?></span>
                                <?php if(! empty($step['hint'])): ?>
                                    <span class="block truncate text-xs"
                                          :class="unlocked(<?php echo e($n); ?>) ? 'text-slate-400' : 'text-slate-300'"><?php echo e($step['hint']); ?></span>
                                <?php endif; ?>
                            </span>
                        </button>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ol>

            
            <div class="mt-3 border-t border-slate-100 pt-3" x-show="editing" x-cloak>
                <?php echo e($save ?? ''); ?>

            </div>
        </div>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/form-wizard.blade.php ENDPATH**/ ?>