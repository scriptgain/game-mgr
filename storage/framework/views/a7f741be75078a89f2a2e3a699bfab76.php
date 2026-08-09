
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => null, 'maxWidth' => null]));

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

foreach (array_filter((['title' => null, 'maxWidth' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php $maxWidth = $maxWidth ?: config('gamemgr.max_width', 'max-w-7xl'); ?>
<?php
    $u = auth()->user();
    $isAdmin = $u?->isAdmin() ?? false;
    $openAlerts = $isAdmin ? \App\Models\Alert::whereNull('acknowledged_at')->count() : 0;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title ? $title . ' | ' . config('brand.name') : config('brand.name')); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo e(route('favicon.svg')); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo e(route('favicon.png')); ?>">
    <link rel="apple-touch-icon" href="<?php echo e(route('favicon.apple')); ?>">
    
    <style>
        [x-cloak] { display: none !important; }
    </style>
    
    
    <script defer src="<?php echo e(asset('js/gamemgr.js')); ?>?v=<?php echo e(\App\Support\Asset::version('js/gamemgr.js')); ?>"></script>
    <?php if (isset($component)) { $__componentOriginald8148f5689903f6ad943797ae197f7c9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald8148f5689903f6ad943797ae197f7c9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tailwind-cdn','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tailwind-cdn'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald8148f5689903f6ad943797ae197f7c9)): ?>
<?php $attributes = $__attributesOriginald8148f5689903f6ad943797ae197f7c9; ?>
<?php unset($__attributesOriginald8148f5689903f6ad943797ae197f7c9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald8148f5689903f6ad943797ae197f7c9)): ?>
<?php $component = $__componentOriginald8148f5689903f6ad943797ae197f7c9; ?>
<?php unset($__componentOriginald8148f5689903f6ad943797ae197f7c9); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal5c21fda6aabe43da67ada52ce874b5b1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.accent-style','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('accent-style'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1)): ?>
<?php $attributes = $__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1; ?>
<?php unset($__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5c21fda6aabe43da67ada52ce874b5b1)): ?>
<?php $component = $__componentOriginal5c21fda6aabe43da67ada52ce874b5b1; ?>
<?php unset($__componentOriginal5c21fda6aabe43da67ada52ce874b5b1); ?>
<?php endif; ?>
</head>
<body class="h-full min-h-full bg-slate-50">
<?php if (isset($component)) { $__componentOriginal6dc4d714d22675e23fa4e9295e23ab4c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6dc4d714d22675e23fa4e9295e23ab4c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.demo-banner','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('demo-banner'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6dc4d714d22675e23fa4e9295e23ab4c)): ?>
<?php $attributes = $__attributesOriginal6dc4d714d22675e23fa4e9295e23ab4c; ?>
<?php unset($__attributesOriginal6dc4d714d22675e23fa4e9295e23ab4c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6dc4d714d22675e23fa4e9295e23ab4c)): ?>
<?php $component = $__componentOriginal6dc4d714d22675e23fa4e9295e23ab4c; ?>
<?php unset($__componentOriginal6dc4d714d22675e23fa4e9295e23ab4c); ?>
<?php endif; ?>
<div class="min-h-full flex flex-col">

    
    <div class="h-0.5 bg-gradient-to-r from-brand-600 via-brand-400 to-brand-600"></div>

    
    <div class="bg-chrome text-slate-300 text-sm ring-1 ring-inset ring-white/5">
        <div class="<?php echo e($maxWidth); ?> mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-12 items-center justify-between gap-4">
                <?php if (isset($component)) { $__componentOriginal6328f0deb07a8bef5ad2cd5691beb925 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6328f0deb07a8bef5ad2cd5691beb925 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand','data' => ['class' => 'text-white']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'text-white']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6328f0deb07a8bef5ad2cd5691beb925)): ?>
<?php $attributes = $__attributesOriginal6328f0deb07a8bef5ad2cd5691beb925; ?>
<?php unset($__attributesOriginal6328f0deb07a8bef5ad2cd5691beb925); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6328f0deb07a8bef5ad2cd5691beb925)): ?>
<?php $component = $__componentOriginal6328f0deb07a8bef5ad2cd5691beb925; ?>
<?php unset($__componentOriginal6328f0deb07a8bef5ad2cd5691beb925); ?>
<?php endif; ?>
                <div class="flex items-center gap-2 sm:gap-3">
                    <?php if($isAdmin): ?>
                        <a href="<?php echo e(route('admin.alerts.index')); ?>" title="Alerts"
                           class="hidden sm:inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition
                                  <?php echo e($openAlerts > 0 ? 'bg-amber-400/10 text-amber-200 ring-amber-400/25 hover:bg-amber-400/20' : 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/20 hover:bg-emerald-400/20'); ?>">
                            <?php if($openAlerts > 0): ?>
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'warning','class' => 'w-3.5 h-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'warning','class' => 'w-3.5 h-3.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <?php echo e($openAlerts); ?> Open <?php echo e(\Illuminate\Support\Str::plural('Alert', $openAlerts)); ?>

                            <?php else: ?>
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                </span>
                                All Clear
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo e(route('docs')); ?>" target="_blank" rel="noopener" title="Documentation"
                       class="hidden md:inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'book','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'book','class' => 'w-4 h-4']); ?>
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
                    </a>
                    <span class="hidden sm:inline-block h-5 w-px bg-white/10"></span>
                    <?php if (isset($component)) { $__componentOriginaldf8083d4a852c446488d8d384bbc7cbe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dropdown','data' => ['align' => 'right']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['align' => 'right']); ?>
                         <?php $__env->slot('trigger', null, []); ?> 
                            <button class="inline-flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-white/10 transition">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-brand-500/20 text-brand-200 text-xs font-semibold ring-1 ring-brand-400/40"><?php echo e($u?->initials() ?? 'GM'); ?></span>
                                <span class="hidden sm:block text-xs font-medium text-slate-200 max-w-[8rem] truncate"><?php echo e(\Illuminate\Support\Str::of($u?->name ?? 'Account')->explode(' ')->first()); ?></span>
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'chevron-down','class' => 'w-4 h-4 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-down','class' => 'w-4 h-4 text-slate-400']); ?>
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
                            </button>
                         <?php $__env->endSlot(); ?>
                        <?php if($u): ?>
                            <div class="px-3 py-2.5 border-b border-slate-100">
                                <p class="text-sm font-medium text-slate-900 truncate"><?php echo e($u->name); ?></p>
                                <p class="text-xs text-slate-500 truncate"><?php echo e($u->email); ?></p>
                                <span class="mt-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset
                                             <?php echo e($isAdmin ? 'bg-brand-50 text-brand-700 ring-brand-200' : 'bg-slate-100 text-slate-600 ring-slate-200'); ?>">
                                    <?php echo e($isAdmin ? ($u->isRootAdmin() ? 'Root Admin' : 'Admin') : 'Client'); ?>

                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dropdown-item','data' => ['icon' => 'user-group','href' => ''.e(route('account.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'user-group','href' => ''.e(route('account.index')).'']); ?>My Account <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $attributes = $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $component = $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dropdown-item','data' => ['icon' => 'key','href' => ''.e(route('account.api.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'key','href' => ''.e(route('account.api.index')).'']); ?>API Credentials <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $attributes = $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $component = $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
                        <?php if($isAdmin): ?>
                            <div class="my-1 border-t border-slate-100"></div>
                            <?php if (isset($component)) { $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dropdown-item','data' => ['icon' => 'settings','href' => ''.e(route('settings.general.edit')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'settings','href' => ''.e(route('settings.general.edit')).'']); ?>Panel Settings <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $attributes = $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $component = $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dropdown-item','data' => ['icon' => 'users','href' => ''.e(route('admin.users.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'users','href' => ''.e(route('admin.users.index')).'']); ?>Users <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $attributes = $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $component = $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dropdown-item','data' => ['icon' => 'book','href' => ''.e(route('settings.audit.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'book','href' => ''.e(route('settings.audit.index')).'']); ?>Audit Log <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $attributes = $__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__attributesOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022)): ?>
<?php $component = $__componentOriginal6b1d0d55421798f4a1c7b596bea6c022; ?>
<?php unset($__componentOriginal6b1d0d55421798f4a1c7b596bea6c022); ?>
<?php endif; ?>
                        <?php endif; ?>
                        <div class="my-1 border-t border-slate-100"></div>
                        <form method="POST" action="<?php echo e(route('logout')); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-left text-rose-600 hover:bg-rose-50">
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'x-circle','class' => 'w-4 h-4 shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x-circle','class' => 'w-4 h-4 shrink-0']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Sign Out
                            </button>
                        </form>
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe)): ?>
<?php $attributes = $__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe; ?>
<?php unset($__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldf8083d4a852c446488d8d384bbc7cbe)): ?>
<?php $component = $__componentOriginaldf8083d4a852c446488d8d384bbc7cbe; ?>
<?php unset($__componentOriginaldf8083d4a852c446488d8d384bbc7cbe); ?>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    
    <?php
        if ($isAdmin) {
            $nav = [
                ['type' => 'link', 'label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'dashboard',
                    'active' => request()->routeIs('dashboard')],
                ['type' => 'link', 'label' => 'Servers', 'href' => route('admin.servers.index'), 'icon' => 'server',
                    'active' => request()->routeIs('admin.servers.*', 'servers.*', 'server.*')],
                ['type' => 'group', 'label' => 'Infrastructure', 'icon' => 'cloud',
                    'active' => request()->routeIs('admin.locations.*', 'admin.nodes.*', 'admin.mounts.*', 'admin.database-hosts.*'),
                    'items' => [
                        ['Nodes', route('admin.nodes.index'), 'server', request()->routeIs('admin.nodes.*')],
                        ['Locations', route('admin.locations.index'), 'globe', request()->routeIs('admin.locations.*')],
                        ['Database Hosts', route('admin.database-hosts.index'), 'database', request()->routeIs('admin.database-hosts.*')],
                        ['Mounts', route('admin.mounts.index'), 'folder', request()->routeIs('admin.mounts.*')],
                    ]],
                ['type' => 'group', 'label' => 'Catalogue', 'icon' => 'cube',
                    'active' => request()->routeIs('admin.games.*', 'admin.templates.*', 'admin.blueprints.*'),
                    'items' => [
                        ['Games', route('admin.games.index'), 'controller', request()->routeIs('admin.games.*')],
                        // Excludes import explicitly: 'admin.templates.*' matches
                        // 'admin.templates.import' too, so both entries lit up at
                        // once and neither told you where you were.
                        ['Templates', route('admin.templates.index'), 'cube',
                            request()->routeIs('admin.templates.*') && ! request()->routeIs('admin.templates.import')],
                        ['Import Template', route('admin.templates.import'), 'download', request()->routeIs('admin.templates.import')],
                        ['Blueprints', route('admin.blueprints.index'), 'copy', request()->routeIs('admin.blueprints.*')],
                    ]],
                ['type' => 'group', 'label' => 'Operations', 'icon' => 'bolt',
                    'active' => request()->routeIs('admin.alerts.*', 'admin.watchdog.*', 'admin.channels.*', 'admin.webhooks.*'),
                    'items' => [
                        ['Alerts', route('admin.alerts.index'), 'warning', request()->routeIs('admin.alerts.*')],
                        ['Watchdog Rules', route('admin.watchdog.index'), 'shield', request()->routeIs('admin.watchdog.*')],
                        ['Notification Channels', route('admin.channels.index'), 'bell', request()->routeIs('admin.channels.*')],
                        ['Webhooks', route('admin.webhooks.index'), 'link', request()->routeIs('admin.webhooks.*')],
                    ]],
            ];
        } else {
            $nav = [
                ['type' => 'link', 'label' => 'My Servers', 'href' => route('dashboard'), 'icon' => 'server',
                    'active' => request()->routeIs('dashboard', 'server.*')],
                ['type' => 'link', 'label' => 'Account', 'href' => route('account.index'), 'icon' => 'user-group',
                    'active' => request()->routeIs('account.*')],
            ];
        }

    ?>
    <header x-data="{ mobileOpen: false }" class="bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 border-b border-slate-200 sticky top-0 z-30">
        <div class="<?php echo e($maxWidth); ?> mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-14 items-center justify-between gap-3">
                <div class="flex items-center gap-1 min-w-0">
                    <button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-label="Toggle menu"
                        class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 hover:bg-slate-100 transition shrink-0">
                        <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                        <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                    <nav class="hidden lg:flex items-center gap-1">
                        <?php $__currentLoopData = $nav; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if($item['type'] === 'link'): ?>
                                <?php if (isset($component)) { $__componentOriginalc295f12dca9d42f28a259237a5724830 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc295f12dca9d42f28a259237a5724830 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.nav-link','data' => ['href' => $item['href'],'icon' => $item['icon'],'active' => $item['active']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['href']),'icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['icon']),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['active'])]); ?><?php echo e($item['label']); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc295f12dca9d42f28a259237a5724830)): ?>
<?php $attributes = $__attributesOriginalc295f12dca9d42f28a259237a5724830; ?>
<?php unset($__attributesOriginalc295f12dca9d42f28a259237a5724830); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc295f12dca9d42f28a259237a5724830)): ?>
<?php $component = $__componentOriginalc295f12dca9d42f28a259237a5724830; ?>
<?php unset($__componentOriginalc295f12dca9d42f28a259237a5724830); ?>
<?php endif; ?>
                            <?php else: ?>
                                <?php $gActive = $item['active']; ?>
                                <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
                                    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                                        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                            'inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition ring-1 ring-inset',
                                            'text-brand-700 bg-brand-50 ring-brand-200' => $gActive,
                                            'text-slate-600 ring-transparent hover:text-slate-900 hover:bg-slate-100 hover:ring-slate-200' => ! $gActive,
                                        ]); ?>">
                                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $item['icon'],'class' => 'w-4 h-4 shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['icon']),'class' => 'w-4 h-4 shrink-0']); ?>
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
                                        <?php echo e($item['label']); ?>

                                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'chevron-down','class' => 'w-4 h-4 -mr-0.5 text-slate-400 transition-transform',':class' => 'open && \'rotate-180\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-down','class' => 'w-4 h-4 -mr-0.5 text-slate-400 transition-transform',':class' => 'open && \'rotate-180\'']); ?>
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
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-40 mt-2 w-60 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-slate-200 py-1"
                                         @click="open = false">
                                        <?php $__currentLoopData = $item['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $href, $icon, $active]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <a href="<?php echo e($href); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                                'flex items-center gap-2.5 px-3 py-2 text-sm transition',
                                                'text-brand-700 bg-brand-50 font-medium' => $active,
                                                'text-slate-700 hover:bg-slate-100' => ! $active,
                                            ]); ?>">
                                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-4 h-4 shrink-0 '.e($active ? 'text-brand-600' : 'text-slate-400').'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-4 h-4 shrink-0 '.e($active ? 'text-brand-600' : 'text-slate-400').'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <?php echo e($label); ?>

                                            </a>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </nav>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if($isAdmin): ?>
                        <?php if (isset($component)) { $__componentOriginal3926d578091497730d65289e5ea3ba49 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3926d578091497730d65289e5ea3ba49 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.create-menu','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('create-menu'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3926d578091497730d65289e5ea3ba49)): ?>
<?php $attributes = $__attributesOriginal3926d578091497730d65289e5ea3ba49; ?>
<?php unset($__attributesOriginal3926d578091497730d65289e5ea3ba49); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3926d578091497730d65289e5ea3ba49)): ?>
<?php $component = $__componentOriginal3926d578091497730d65289e5ea3ba49; ?>
<?php unset($__componentOriginal3926d578091497730d65289e5ea3ba49); ?>
<?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="lg:hidden border-t border-slate-100 bg-white shadow-sm">
            <nav class="<?php echo e($maxWidth); ?> mx-auto px-4 sm:px-6 py-3 space-y-3">
                <?php $__currentLoopData = $nav; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($item['type'] === 'link'): ?>
                        <a href="<?php echo e($item['href']); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                            'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $item['active'],
                            'text-slate-600 hover:bg-slate-100' => ! $item['active'],
                        ]); ?>">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $item['icon'],'class' => 'w-4 h-4 shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['icon']),'class' => 'w-4 h-4 shrink-0']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <?php echo e($item['label']); ?>

                        </a>
                    <?php else: ?>
                        <div>
                            <p class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400"><?php echo e($item['label']); ?></p>
                            <div class="grid grid-cols-2 gap-1.5">
                                <?php $__currentLoopData = $item['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $href, $icon, $active]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <a href="<?php echo e($href); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                        'flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                                        'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $active,
                                        'text-slate-600 hover:bg-slate-100' => ! $active,
                                    ]); ?>">
                                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-4 h-4 shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-4 h-4 shrink-0']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <?php echo e($label); ?>

                                    </a>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </nav>
        </div>
    </header>

    <?php if (isset($component)) { $__componentOriginal81fc2c1cb3a33996210a2d0eb6512684 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal81fc2c1cb3a33996210a2d0eb6512684 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.impersonation-banner','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('impersonation-banner'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal81fc2c1cb3a33996210a2d0eb6512684)): ?>
<?php $attributes = $__attributesOriginal81fc2c1cb3a33996210a2d0eb6512684; ?>
<?php unset($__attributesOriginal81fc2c1cb3a33996210a2d0eb6512684); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal81fc2c1cb3a33996210a2d0eb6512684)): ?>
<?php $component = $__componentOriginal81fc2c1cb3a33996210a2d0eb6512684; ?>
<?php unset($__componentOriginal81fc2c1cb3a33996210a2d0eb6512684); ?>
<?php endif; ?>

    
    <main class="flex-1 py-8">
        <div class="<?php echo e($maxWidth); ?> mx-auto px-4 sm:px-6 lg:px-8">
            <?php if (isset($component)) { $__componentOriginalc2d5772b82fc71dd792b40d519e4e3f1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2d5772b82fc71dd792b40d519e4e3f1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.update-banner','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('update-banner'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc2d5772b82fc71dd792b40d519e4e3f1)): ?>
<?php $attributes = $__attributesOriginalc2d5772b82fc71dd792b40d519e4e3f1; ?>
<?php unset($__attributesOriginalc2d5772b82fc71dd792b40d519e4e3f1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc2d5772b82fc71dd792b40d519e4e3f1)): ?>
<?php $component = $__componentOriginalc2d5772b82fc71dd792b40d519e4e3f1; ?>
<?php unset($__componentOriginalc2d5772b82fc71dd792b40d519e4e3f1); ?>
<?php endif; ?>
            <?php if(session('status')): ?>
                <div class="mb-6"><?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'success']); ?><?php echo e(session('status')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?></div>
            <?php endif; ?>
            <?php if(session('warning')): ?>
                <div class="mb-6"><?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'warn']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warn']); ?><?php echo e(session('warning')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?></div>
            <?php endif; ?>
            <?php if(session('error')): ?>
                <div class="mb-6"><?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger']); ?><?php echo e(session('error')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?></div>
            <?php endif; ?>
            
            <?php if(request()->routeIs('settings.*')): ?>
                <div class="settings-shell">
                    <aside class="settings-aside"><?php if (isset($component)) { $__componentOriginal7a95aaf2cce22832cb2ab23237ac2978 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a95aaf2cce22832cb2ab23237ac2978 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.settings-tabs','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('settings-tabs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a95aaf2cce22832cb2ab23237ac2978)): ?>
<?php $attributes = $__attributesOriginal7a95aaf2cce22832cb2ab23237ac2978; ?>
<?php unset($__attributesOriginal7a95aaf2cce22832cb2ab23237ac2978); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a95aaf2cce22832cb2ab23237ac2978)): ?>
<?php $component = $__componentOriginal7a95aaf2cce22832cb2ab23237ac2978; ?>
<?php unset($__componentOriginal7a95aaf2cce22832cb2ab23237ac2978); ?>
<?php endif; ?></aside>
                    <div><?php echo e($slot); ?></div>
                </div>
            <?php else: ?>
                <?php echo e($slot); ?>

            <?php endif; ?>
        </div>
    </main>

    
    <footer class="border-t border-slate-200 bg-white">
        <div class="<?php echo e($maxWidth); ?> mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <span><?php echo e(config('brand.name')); ?> &middot; <?php echo e(config('brand.tagline')); ?></span>
            <span class="tabular">v<?php echo e(\App\Services\UpdateService::currentVersion()); ?> &middot; Docker, SteamCMD and LinuxGSM</span>
        </div>
    </footer>

</div>


<style>
    .vx-tip{position:fixed;z-index:9999;max-width:22rem;padding:.5rem .625rem;border-radius:.5rem;background:#0f172a;color:#f8fafc;font-size:.75rem;line-height:1.2rem;white-space:pre-line;box-shadow:0 8px 24px rgba(2,6,23,.22);pointer-events:none;opacity:0;transition:opacity .12s ease;display:none}
    .vx-tip strong{color:#fff}
    /* Integrated thin scrollbar for scroll areas (matches the UI, not the OS chrome). */
    .vx-scroll{scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
    .vx-scroll::-webkit-scrollbar{width:9px;height:9px}
    .vx-scroll::-webkit-scrollbar-track{background:transparent}
    .vx-scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:9999px;border:2px solid transparent;background-clip:content-box}
    .vx-scroll::-webkit-scrollbar-thumb:hover{background:#94a3b8;background-clip:content-box}
    .vx-scroll::-webkit-scrollbar-corner{background:transparent}
    /* Dialog bodies and file panes must NEVER scroll sideways. Long paths,
       snapshot ids and shell one-liners wrap; anything genuinely un-wrappable
       (a wide table) scrolls inside its own .vx-x-scroll box instead. Setting
       overflow-y alone would silently make overflow-x scroll too. */
    /* white-space is inherited, so a dialog opened from inside a table cell
       (.vx-table sets nowrap) used to render its body as one un-readable line
       that overflow-x:hidden then clipped. Reset it here rather than at every
       call site: a wrapping body is the standard, never a horizontal scroll. */
    .vx-wrap{overflow-wrap:anywhere;white-space:normal}
    .vx-modal,.vx-modal h3,.vx-modal p,.vx-modal span,.vx-modal div{white-space:normal}
    .vx-wrap pre,.vx-wrap code{white-space:pre-wrap;overflow-wrap:anywhere}
    .vx-wrap table{width:100%;table-layout:fixed}
    .vx-wrap .vx-x-scroll{overflow-x:auto;max-width:100%}
    /* Inputs carry a ~20ch intrinsic width, which is what pushes two-column
       forms wider than the dialog on narrow viewports. */
    .vx-wrap input,.vx-wrap select,.vx-wrap textarea{min-width:0;max-width:100%}
    .vx-wrap .grid{min-width:0}
</style>
<script>
    (function () {
        var tip;
        function ensure() {
            if (!tip) { tip = document.createElement('div'); tip.className = 'vx-tip'; document.body.appendChild(tip); }
            return tip;
        }
        function show(el) {
            var t = el.getAttribute('data-tip');
            if (!t) return;
            var n = ensure();
            n.textContent = t;
            n.style.display = 'block';
            n.style.opacity = '0';
            var r = el.getBoundingClientRect(), tr = n.getBoundingClientRect();
            var left = Math.max(8, Math.min(r.left + r.width / 2 - tr.width / 2, window.innerWidth - tr.width - 8));
            var top = r.top - tr.height - 8;
            if (top < 8) top = r.bottom + 8; // flip below when there's no room above
            n.style.left = left + 'px';
            n.style.top = top + 'px';
            n.style.opacity = '1';
        }
        function hide() { if (tip) { tip.style.opacity = '0'; tip.style.display = 'none'; } }
        document.addEventListener('mouseover', function (e) { var el = e.target.closest('[data-tip]'); if (el) show(el); });
        document.addEventListener('mouseout', function (e) { var el = e.target.closest('[data-tip]'); if (el) hide(); });
        document.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
    })();
</script>
</body>
</html>
<?php /**PATH /var/www/gamemgr/resources/views/components/layouts/app.blade.php ENDPATH**/ ?>