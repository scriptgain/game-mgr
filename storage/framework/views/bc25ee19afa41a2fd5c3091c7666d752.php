<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => ['title' => $title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title)]); ?>
    <?php echo $__env->make('server._shell', ['server' => $server], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Online Now','icon' => 'user-group','subtitle' => $online->count().' '.Str::plural('player', $online->count()).' connected','flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Online Now','icon' => 'user-group','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($online->count().' '.Str::plural('player', $online->count()).' connected'),'flush' => true]); ?>
                <?php if($online->isEmpty()): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'user-group','title' => 'Nobody Is On','description' => 'Players appear here as soon as they connect.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'user-group','title' => 'Nobody Is On','description' => 'Players appear here as soon as they connect.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
                <?php else: ?>
                    <?php if (isset($component)) { $__componentOriginal163c8ba6efb795223894d5ffef5034f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal163c8ba6efb795223894d5ffef5034f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.table','data' => ['flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['flush' => true]); ?>
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Playtime</th>
                                <th>Flags</th>
                                <th class="text-right vx-act-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $online; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900"><?php echo e($player->name); ?></span>
                                        <span class="block font-mono text-xs text-slate-400 truncate"><?php echo e($player->identifier); ?></span>
                                    </td>
                                    <td class="tabular text-slate-500"><?php echo e($player->playtime()); ?></td>
                                    <td>
                                        <span class="flex items-center gap-1">
                                            <?php if($player->is_op): ?><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'info']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'info']); ?><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'star','class' => 'w-3.5 h-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'star','class' => 'w-3.5 h-3.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Op <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?><?php endif; ?>
                                            <?php if($player->is_whitelisted): ?><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'success']); ?>Whitelisted <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?><?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="text-right vx-act-3">
                                        <div class="inline-flex items-center gap-1">
                                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'player.manage'])): ?>
                                                <form method="POST" action="<?php echo e(route('server.players.op', [$server, $player])); ?>">
                                                    <?php echo csrf_field(); ?><?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['type' => 'submit','icon' => 'star','variant' => $player->is_op ? 'brand' : 'secondary','title' => $player->is_op ? 'Remove Operator' : 'Make Operator']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'star','variant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->is_op ? 'brand' : 'secondary'),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->is_op ? 'Remove Operator' : 'Make Operator')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $attributes = $__attributesOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__attributesOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $component = $__componentOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__componentOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'player.kick'])): ?>
                                                <form method="POST" action="<?php echo e(route('server.players.kick', [$server, $player])); ?>">
                                                    <?php echo csrf_field(); ?><?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['type' => 'submit','icon' => 'x-circle','title' => 'Kick From The Server']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'x-circle','title' => 'Kick From The Server']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $attributes = $__attributesOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__attributesOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $component = $__componentOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__componentOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'player.ban'])): ?>
                                                <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'ban-'.e($player->id).'','action' => route('server.players.ban', [$server, $player]),'tone' => 'danger','title' => 'Ban '.e($player->name).'?','message' => 'They are removed now and cannot rejoin until you unban them. The ban is recorded against this server only.','confirm' => 'Ban Player','confirmVariant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'ban-'.e($player->id).'','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('server.players.ban', [$server, $player])),'tone' => 'danger','title' => 'Ban '.e($player->name).'?','message' => 'They are removed now and cannot rejoin until you unban them. The ban is recorded against this server only.','confirm' => 'Ban Player','confirm-variant' => 'danger']); ?>
                                                    <?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['icon' => 'ban','variant' => 'danger','title' => 'Ban Player']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'ban','variant' => 'danger','title' => 'Ban Player']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $attributes = $__attributesOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__attributesOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $component = $__componentOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__componentOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
                                                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc44eb547b9799a0e4f62294149f13577)): ?>
<?php $attributes = $__attributesOriginalc44eb547b9799a0e4f62294149f13577; ?>
<?php unset($__attributesOriginalc44eb547b9799a0e4f62294149f13577); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc44eb547b9799a0e4f62294149f13577)): ?>
<?php $component = $__componentOriginalc44eb547b9799a0e4f62294149f13577; ?>
<?php unset($__componentOriginalc44eb547b9799a0e4f62294149f13577); ?>
<?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $attributes = $__attributesOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $component = $__componentOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__componentOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
                <?php endif; ?>
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

            <?php if (isset($component)) { $__componentOriginal983d366924d4e4b5324edcaeffbb36b1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal983d366924d4e4b5324edcaeffbb36b1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-actions','data' => ['action' => route('server.bulk', [$server, 'players']),'label' => 'player']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('server.bulk', [$server, 'players'])),'label' => 'player']); ?>
                 <?php $__env->slot('table', null, []); ?> 
                    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Everyone Who Has Played','icon' => 'users','flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Everyone Who Has Played','icon' => 'users','flush' => true]); ?>
                     <?php $__env->slot('actions', null, []); ?> 
                    <form method="GET" class="flex items-center gap-2">
                    <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'q','value' => ''.e($search).'','placeholder' => 'Search by name or id','class' => 'w-56']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'q','value' => ''.e($search).'','placeholder' => 'Search by name or id','class' => 'w-56']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $attributes = $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $component = $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','variant' => 'secondary','size' => 'sm','icon' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'secondary','size' => 'sm','icon' => 'search']); ?>Search <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
                    </form>
                     <?php $__env->endSlot(); ?>
                    <?php if($players->isEmpty()): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'user-group','title' => 'No Players Recorded','description' => 'Nobody has connected to this server yet, or the query protocol has not reported any.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'user-group','title' => 'No Players Recorded','description' => 'Nobody has connected to this server yet, or the query protocol has not reported any.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
                    <?php else: ?>
                    <?php if (isset($component)) { $__componentOriginal163c8ba6efb795223894d5ffef5034f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal163c8ba6efb795223894d5ffef5034f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.table','data' => ['flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['flush' => true]); ?>
                    <thead>
                    <tr>
                    <th class="w-10"><?php if (isset($component)) { $__componentOriginale7cc125ac67e961dd14784be2099d7c2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale7cc125ac67e961dd14784be2099d7c2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select-toggle','data' => ['all' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select-toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['all' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale7cc125ac67e961dd14784be2099d7c2)): ?>
<?php $attributes = $__attributesOriginale7cc125ac67e961dd14784be2099d7c2; ?>
<?php unset($__attributesOriginale7cc125ac67e961dd14784be2099d7c2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale7cc125ac67e961dd14784be2099d7c2)): ?>
<?php $component = $__componentOriginale7cc125ac67e961dd14784be2099d7c2; ?>
<?php unset($__componentOriginale7cc125ac67e961dd14784be2099d7c2); ?>
<?php endif; ?></th>
                    <th>Player</th>
                    <th>Last Seen</th>
                    <th>Playtime</th>
                    <th>Status</th>
                    <th class="text-right vx-act-1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                    <td class="w-10"><?php if (isset($component)) { $__componentOriginale7cc125ac67e961dd14784be2099d7c2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale7cc125ac67e961dd14784be2099d7c2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select-toggle','data' => ['value' => $player->id,'label' => $player->name]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select-toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->id),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->name)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale7cc125ac67e961dd14784be2099d7c2)): ?>
<?php $attributes = $__attributesOriginale7cc125ac67e961dd14784be2099d7c2; ?>
<?php unset($__attributesOriginale7cc125ac67e961dd14784be2099d7c2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale7cc125ac67e961dd14784be2099d7c2)): ?>
<?php $component = $__componentOriginale7cc125ac67e961dd14784be2099d7c2; ?>
<?php unset($__componentOriginale7cc125ac67e961dd14784be2099d7c2); ?>
<?php endif; ?></td>
                    <td>
                    <span class="font-medium text-slate-900"><?php echo e($player->name); ?></span>
                    <span class="block font-mono text-xs text-slate-400 truncate"><?php echo e($player->identifier); ?></span>
                    </td>
                    <td class="text-slate-500 text-xs"><?php echo e($player->last_seen_at?->diffForHumans()); ?></td>
                    <td class="tabular text-slate-500"><?php echo e($player->playtime()); ?></td>
                    <td>
                    <?php if($player->is_banned): ?>
                    <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'danger','dot' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'danger','dot' => true]); ?>Banned <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                    <?php elseif($player->is_online): ?>
                    <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'success','dot' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'success','dot' => true]); ?>Online <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                    <?php else: ?>
                    <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'neutral','dot' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'neutral','dot' => true]); ?>Offline <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                    <?php endif; ?>
                    </td>
                    <td class="text-right vx-act-1">
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'player.ban'])): ?>
                    <?php if($player->is_banned): ?>
                    <form method="POST" action="<?php echo e(route('server.players.unban', [$server, $player])); ?>">
                    <?php echo csrf_field(); ?><?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['type' => 'submit','icon' => 'check-circle','title' => 'Unban Player']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'check-circle','title' => 'Unban Player']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $attributes = $__attributesOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__attributesOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal658398a0e73a18931bb7def04d911f42)): ?>
<?php $component = $__componentOriginal658398a0e73a18931bb7def04d911f42; ?>
<?php unset($__componentOriginal658398a0e73a18931bb7def04d911f42); ?>
<?php endif; ?>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                    </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $attributes = $__attributesOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $component = $__componentOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__componentOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
                    <?php endif; ?>
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
                 <?php $__env->endSlot(); ?>

                <?php if (isset($component)) { $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-action','data' => ['action' => 'whitelist','icon' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => 'whitelist','icon' => 'check']); ?>Whitelist <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $attributes = $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $component = $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-action','data' => ['action' => 'unwhitelist','icon' => 'x-circle']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => 'unwhitelist','icon' => 'x-circle']); ?>Remove From Whitelist <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $attributes = $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $component = $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-action','data' => ['action' => 'kick','icon' => 'x-circle','confirm' => 'Anyone currently connected is disconnected. They can rejoin straight away.','confirmTitle' => 'Kick These Players?']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => 'kick','icon' => 'x-circle','confirm' => 'Anyone currently connected is disconnected. They can rejoin straight away.','confirm-title' => 'Kick These Players?']); ?>Kick <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $attributes = $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $component = $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-action','data' => ['action' => 'ban','icon' => 'ban','tone' => 'danger','confirm' => 'They are removed now and cannot rejoin until you unban them. The ban is recorded against this server only.','confirmTitle' => 'Ban These Players?']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => 'ban','icon' => 'ban','tone' => 'danger','confirm' => 'They are removed now and cannot rejoin until you unban them. The ban is recorded against this server only.','confirm-title' => 'Ban These Players?']); ?>Ban <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $attributes = $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $component = $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-action','data' => ['action' => 'unban','icon' => 'check-circle']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => 'unban','icon' => 'check-circle']); ?>Unban <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $attributes = $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5)): ?>
<?php $component = $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5; ?>
<?php unset($__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5); ?>
<?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal983d366924d4e4b5324edcaeffbb36b1)): ?>
<?php $attributes = $__attributesOriginal983d366924d4e4b5324edcaeffbb36b1; ?>
<?php unset($__attributesOriginal983d366924d4e4b5324edcaeffbb36b1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal983d366924d4e4b5324edcaeffbb36b1)): ?>
<?php $component = $__componentOriginal983d366924d4e4b5324edcaeffbb36b1; ?>
<?php unset($__componentOriginal983d366924d4e4b5324edcaeffbb36b1); ?>
<?php endif; ?>
        </div>

        <div>
            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Recent Events','icon' => 'book','flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Recent Events','icon' => 'book','flush' => true]); ?>
                <ul class="divide-y divide-slate-100">
                    <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <li class="px-5 py-3 flex items-start gap-3">
                            <span class="mt-1.5"><?php if (isset($component)) { $__componentOriginale122a964aaade1f8044b1545740ce9f7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale122a964aaade1f8044b1545740ce9f7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-dot','data' => ['tone' => $event->tone(),'label' => '']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-dot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->tone()),'label' => '']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale122a964aaade1f8044b1545740ce9f7)): ?>
<?php $attributes = $__attributesOriginale122a964aaade1f8044b1545740ce9f7; ?>
<?php unset($__attributesOriginale122a964aaade1f8044b1545740ce9f7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale122a964aaade1f8044b1545740ce9f7)): ?>
<?php $component = $__componentOriginale122a964aaade1f8044b1545740ce9f7; ?>
<?php unset($__componentOriginale122a964aaade1f8044b1545740ce9f7); ?>
<?php endif; ?></span>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800">
                                    <span class="font-medium"><?php echo e($event->player?->name ?? 'Someone'); ?></span>
                                    <?php echo e(['join' => 'joined', 'leave' => 'left', 'kick' => 'was kicked', 'ban' => 'was banned', 'unban' => 'was unbanned'][$event->event] ?? $event->event); ?>

                                </p>
                                <?php if($event->detail): ?><p class="text-xs text-slate-500"><?php echo e($event->detail); ?></p><?php endif; ?>
                                <p class="text-xs text-slate-400"><?php echo e($event->occurred_at->diffForHumans()); ?></p>
                            </div>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <li class="px-5 py-6 text-sm text-slate-500">No player events recorded yet.</li>
                    <?php endif; ?>
                </ul>
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
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $attributes = $__attributesOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__attributesOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $component = $__componentOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__componentOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/server/players.blade.php ENDPATH**/ ?>