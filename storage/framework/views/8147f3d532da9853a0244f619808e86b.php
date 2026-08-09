<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['server' => null]));

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

foreach (array_filter((['server' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    use App\Services\Dns\DnsConfig;
    use Illuminate\Support\Facades\Route as RouteFacade;

    // Only an admin, because only an admin can act on any of this. A customer
    // reading "turn names on in Settings" is being pointed at a door they
    // cannot open, which is worse than the bare address they already have.
    $show = auth()->check() && auth()->user()->isAdmin()
        && RouteFacade::has('settings.domains.edit')
        && ! ($server?->connectName());

    $node = $server?->node;
    $link = null;
    $message = null;

    if ($show && ! DnsConfig::active()) {
        $message = 'Connection names are off, so this shows the direct address only.';
        $link = ['Settings, Domains', route('settings.domains.edit')];
    } elseif ($show && ! DnsConfig::ready()) {
        $message = 'Connection names are on but nothing can write them, so this shows the direct address only.';
        $link = ['Settings, Domains', route('settings.domains.edit')];
    } elseif ($show && $node && ! $node->dns_label) {
        $message = 'This server is on '.$node->name.', which has no label, so it hands out no names.';
        $link = RouteFacade::has('admin.nodes.edit')
            ? ['Give It One', route('admin.nodes.edit', $node)]
            : null;
    } elseif ($show) {
        $message = 'No name has been built for this server yet. The hourly sync will add one.';
        $link = ['Settings, Domains', route('settings.domains.edit')];
    }
?>

<?php if($message): ?>
    <p <?php echo e($attributes->merge(['class' => 'mt-3 flex items-start gap-1.5 text-xs text-slate-500'])); ?>>
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'globe','class' => 'w-3.5 h-3.5 shrink-0 mt-px text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'globe','class' => 'w-3.5 h-3.5 shrink-0 mt-px text-slate-400']); ?>
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
        <span class="min-w-0">
            <?php echo e($message); ?>

            <?php if($link): ?>
                <a href="<?php echo e($link[1]); ?>" class="font-medium text-slate-600 underline hover:text-slate-900"><?php echo e($link[0]); ?></a>.
            <?php endif; ?>
        </span>
    </p>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/components/domains-hint.blade.php ENDPATH**/ ?>