<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['value' => null, 'all' => false, 'label' => null]));

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

foreach (array_filter((['value' => null, 'all' => false, 'label' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if($all): ?>
    <label class="vx-switch">
        <input type="checkbox" x-ref="selectAll" @change="toggleAll($event.target.checked)" :checked="everything">
        <span class="vx-switch-track"><span class="vx-switch-knob"></span></span>
        <span class="sr-only">Select Everything On This Page</span>
    </label>
<?php else: ?>
    <label class="vx-switch">
        <input type="checkbox" value="<?php echo e($value); ?>" data-select-row>
        <span class="vx-switch-track"><span class="vx-switch-knob"></span></span>
        <span class="sr-only">Select <?php echo e($label ?? 'This Row'); ?></span>
    </label>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/components/select-toggle.blade.php ENDPATH**/ ?>