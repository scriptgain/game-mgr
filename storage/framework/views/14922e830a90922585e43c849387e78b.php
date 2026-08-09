
<?php
    $groups = [
        [
            'label' => 'Servers And Games',
            'icon' => 'controller',
            'items' => [
                ['Server', 'admin.servers.create', 'server', 'A game server on one of your nodes'],
                ['Template', 'admin.templates.create', 'cube', 'How a game installs and starts'],
                ['Import An Egg', 'admin.templates.import', 'download', 'Bring in a Pterodactyl egg definition'],
                ['Game', 'admin.games.create', 'controller', 'A title to group templates under'],
                ['Blueprint', 'admin.blueprints.create', 'sparkles', 'A named size operators can pick'],
            ],
        ],
        [
            'label' => 'Infrastructure',
            'icon' => 'server',
            'items' => [
                ['Node', 'admin.nodes.create', 'cpu', 'A machine that runs game servers'],
                ['Location', 'admin.locations.create', 'map', 'A region to group nodes under'],
                ['Database Host', 'admin.database-hosts.create', 'database', 'Where player databases are created'],
                ['Mount', 'admin.mounts.create', 'folder', 'Share a directory into servers'],
                // Guarded by Route::has below, so this stays invisible until the
                // domains feature exists rather than offering a dead link.
                ['Domain', 'admin.domains.create', 'globe', 'Give a server a name players can type'],
            ],
        ],
        [
            'label' => 'People And Automation',
            'icon' => 'users',
            'items' => [
                ['User', 'admin.users.create', 'users', 'An account that can own servers'],
                ['Watchdog Rule', 'admin.watchdog.create', 'shield', 'Act on a crash, or a log line'],
                ['Notification Channel', 'admin.channels.create', 'bell', 'Where alerts are delivered'],
                ['Webhook', 'admin.webhooks.create', 'link', 'Post events to another system'],
            ],
        ],
    ];
?>

<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'plus','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'plus','class' => 'w-4 h-4']); ?>
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
        <span>Create</span>
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'chevron-down','class' => 'w-3.5 h-3.5 transition-transform',':class' => 'open && \'rotate-180\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-down','class' => 'w-3.5 h-3.5 transition-transform',':class' => 'open && \'rotate-180\'']); ?>
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

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="open = false"
         class="absolute right-0 z-50 mt-2 w-[min(56rem,calc(100vw-1.5rem))] origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
        <div class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="min-w-0">
                    <p class="flex items-center gap-2 px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <span class="flex h-5 w-5 items-center justify-center rounded border border-slate-200 bg-slate-50 text-slate-400">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $group['icon'],'class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($group['icon']),'class' => 'w-3 h-3']); ?>
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
                        <?php echo e($group['label']); ?>

                    </p>
                    <div class="space-y-0.5">
                        <?php $__currentLoopData = $group['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $route, $icon, $blurb]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if(Route::has($route)): ?>
                                <a href="<?php echo e(route($route)); ?>"
                                   class="group flex items-start gap-3 rounded-lg border border-transparent px-2 py-2 transition hover:border-brand-200 hover:bg-brand-50/60">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 transition group-hover:border-brand-300 group-hover:bg-brand-50 group-hover:text-brand-700">
                                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'w-4 h-4']); ?>
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
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-slate-900"><?php echo e($label); ?></span>
                                        <span class="block text-xs text-slate-500" style="overflow-wrap: anywhere;"><?php echo e($blurb); ?></span>
                                    </span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/create-menu.blade.php ENDPATH**/ ?>