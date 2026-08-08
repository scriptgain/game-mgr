<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id']));

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

foreach (array_filter((['id']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php foreach ((['active']) as $__key => $__value) {
    $__consumeVariable = is_string($__key) ? $__key : $__value;
    $$__consumeVariable = is_string($__key) ? $__env->getConsumableComponentData($__key, $__value) : $__env->getConsumableComponentData($__value);
} ?>

<section id="pane-<?php echo e($id); ?>" role="tabpanel" aria-labelledby="tab-<?php echo e($id); ?>" tabindex="0"
         <?php echo e($attributes->merge(['class' => 'gm-pane min-w-0 space-y-6 '.($active === $id ? 'is-active' : '')])); ?>

         :class="{ 'is-active': tab === <?php echo \Illuminate\Support\Js::from($id)->toHtml() ?> }">
    <?php echo e($slot); ?>

</section>
<?php /**PATH /var/www/gamemgr/resources/views/components/tab-pane.blade.php ENDPATH**/ ?>