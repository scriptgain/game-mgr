
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo e(route('favicon.svg')); ?>">
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
<body class="h-full bg-slate-50">
<div class="min-h-full flex flex-col">
    <div class="h-1 bg-gradient-to-r from-brand-600 via-brand-400 to-brand-600"></div>

    <main class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-8 text-center border-b border-slate-100">
                    <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $server->template?->game?->icon ?: 'controller','class' => 'w-7 h-7']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->template?->game?->icon ?: 'controller'),'class' => 'w-7 h-7']); ?>
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
                    </span>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900"><?php echo e($page->headline ?: $server->name); ?></h1>
                    <p class="mt-1 text-sm text-slate-500"><?php echo e($server->template?->game?->name); ?></p>

                    <div class="mt-5 inline-flex items-center gap-2.5 rounded-full px-4 py-2 ring-1 ring-inset
                                <?php echo e($server->power_state === 'running' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'); ?>">
                        <span class="relative flex h-2 w-2">
                            <?php if($server->power_state === 'running'): ?>
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <?php endif; ?>
                            <span class="relative inline-flex h-2 w-2 rounded-full <?php echo e($server->power_state === 'running' ? 'bg-emerald-500' : 'bg-slate-400'); ?>"></span>
                        </span>
                        <span class="text-sm font-medium"><?php echo e($server->power_state === 'running' ? 'Online' : 'Offline'); ?></span>
                    </div>
                </div>

                <dl class="divide-y divide-slate-100">
                    <?php if($page->show_address): ?>
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Connect To</dt>
                            <dd class="font-mono text-sm text-slate-900 truncate"><?php echo e($server->address()); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if($page->show_players): ?>
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Players</dt>
                            <dd class="text-sm font-medium text-slate-900 tabular">
                                <?php echo e($server->cached_players); ?><?php if($server->cached_max_players): ?> / <?php echo e($server->cached_max_players); ?><?php endif; ?>
                            </dd>
                        </div>
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Busiest Today</dt>
                            <dd class="text-sm font-medium text-slate-900 tabular"><?php echo e((int) $peak); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if($page->show_uptime && $server->last_started_at): ?>
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Up Since</dt>
                            <dd class="text-sm text-slate-900"><?php echo e($server->last_started_at->diffForHumans()); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if($page->show_version): ?>
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Running</dt>
                            <dd class="text-sm text-slate-900"><?php echo e($server->template?->name); ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <dt class="text-sm text-slate-500">Region</dt>
                        <dd class="text-sm text-slate-900"><?php echo e($server->node?->location?->flag); ?> <?php echo e($server->node?->location?->name); ?></dd>
                    </div>
                </dl>
            </div>

            <p class="mt-4 text-center text-xs text-slate-400">
                Updated <?php echo e($server->cached_at?->diffForHumans() ?? 'recently'); ?> &middot; Hosted with <?php echo e(config('brand.name')); ?>

            </p>
        </div>
    </main>
</div>
</body>
</html>
<?php /**PATH /var/www/gamemgr/resources/views/status.blade.php ENDPATH**/ ?>