<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name' => null, 'checked' => false, 'label' => null, 'description' => null, 'value' => 1]));

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

foreach (array_filter((['name' => null, 'checked' => false, 'label' => null, 'description' => null, 'value' => 1]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<label x-data="{ on: <?php echo e($checked ? 'true' : 'false'); ?> }" class="flex items-start gap-3 cursor-pointer select-none">
    <?php if($name): ?><input type="hidden" name="<?php echo e($name); ?>" :value="on ? '<?php echo e($value); ?>' : 0"><?php endif; ?>
    <button type="button" role="switch" :aria-checked="on.toString()" @click="on = !on"
            :class="on ? 'bg-brand-600' : 'bg-slate-300'"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
        <span :class="on ? 'translate-x-6' : 'translate-x-1'"
              class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
    </button>
    <?php if(trim($slot) !== ''): ?>
        <span class="min-w-0 flex-1"><?php echo e($slot); ?></span>
    <?php elseif($label || $description): ?>
        <span class="text-sm">
            <?php if($label): ?><span class="font-medium text-slate-900"><?php echo e($label); ?></span><?php endif; ?>
            <?php if($description): ?><span class="block text-slate-500"><?php echo e($description); ?></span><?php endif; ?>
        </span>
    <?php endif; ?>
</label>
<?php /**PATH /var/www/gamemgr/resources/views/components/toggle.blade.php ENDPATH**/ ?>