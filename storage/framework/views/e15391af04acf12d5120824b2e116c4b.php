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

    
    <?php if($portShift !== 0): ?>
        <div class="mb-6">
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'warn','title' => 'This Server Is Not On '.e($server->template?->name).'\'s Usual Port']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warn','title' => 'This Server Is Not On '.e($server->template?->name).'\'s Usual Port']); ?>
                <?php echo e($canonicalPort); ?> was already taken on <?php echo e($server->allocation?->ip); ?> by another server, so this one and
                every port it uses moved by <?php echo e($portShift > 0 ? '+'.$portShift : $portShift); ?>. Players connect to
                <span class="font-mono font-semibold"><?php echo e($server->address()); ?></span>, not port <?php echo e($canonicalPort); ?>.
                A dedicated address would give this server the real port. Ask an administrator to move it to one.
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
        </div>
    <?php elseif($canonicalPort): ?>
        <div class="mb-6">
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'success','title' => 'On The Real Port']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'success','title' => 'On The Real Port']); ?>
                This server holds <?php echo e($server->template?->name); ?>'s canonical port <?php echo e($canonicalPort); ?>, so players can
                connect with <span class="font-mono font-semibold"><?php echo e($server->address()); ?></span> and every guide written
                for this game applies as written.
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
        </div>
    <?php endif; ?>

    
    <?php if($server->connectAddress()): ?>
        <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'How Players Connect','icon' => 'globe','class' => 'mb-6','subtitle' => 'Two addresses for the same server. The name is easier to hand out; the address below it works with no DNS at all.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'How Players Connect','icon' => 'globe','class' => 'mb-6','subtitle' => 'Two addresses for the same server. The name is easier to hand out; the address below it works with no DNS at all.']); ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php if (isset($component)) { $__componentOriginal4689e078d981419fe3d32c3868109c4f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4689e078d981419fe3d32c3868109c4f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.copy-field','data' => ['label' => 'Connect','value' => $server->connectAddress()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('copy-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Connect','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->connectAddress())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4689e078d981419fe3d32c3868109c4f)): ?>
<?php $attributes = $__attributesOriginal4689e078d981419fe3d32c3868109c4f; ?>
<?php unset($__attributesOriginal4689e078d981419fe3d32c3868109c4f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4689e078d981419fe3d32c3868109c4f)): ?>
<?php $component = $__componentOriginal4689e078d981419fe3d32c3868109c4f; ?>
<?php unset($__componentOriginal4689e078d981419fe3d32c3868109c4f); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal4689e078d981419fe3d32c3868109c4f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4689e078d981419fe3d32c3868109c4f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.copy-field','data' => ['label' => 'Direct','value' => $server->address()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('copy-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Direct','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->address())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4689e078d981419fe3d32c3868109c4f)): ?>
<?php $attributes = $__attributesOriginal4689e078d981419fe3d32c3868109c4f; ?>
<?php unset($__attributesOriginal4689e078d981419fe3d32c3868109c4f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4689e078d981419fe3d32c3868109c4f)): ?>
<?php $component = $__componentOriginal4689e078d981419fe3d32c3868109c4f; ?>
<?php unset($__componentOriginal4689e078d981419fe3d32c3868109c4f); ?>
<?php endif; ?>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                The name follows the node this server is on. Moving it to another node gives it a new name, and the
                direct address keeps working throughout.
            </p>
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
    <?php endif; ?>

    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Network','icon' => 'network','subtitle' => ''.e($allocations->count()).' of '.e($server->allocation_limit ?: 'unlimited').' allocations used. The primary address is the one players connect to.','flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Network','icon' => 'network','subtitle' => ''.e($allocations->count()).' of '.e($server->allocation_limit ?: 'unlimited').' allocations used. The primary address is the one players connect to.','flush' => true]); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'allocation.create'])): ?>
                <form method="POST" action="<?php echo e(route('server.network.store', $server)); ?>">
                    <?php echo csrf_field(); ?><?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','size' => 'sm','icon' => 'plus']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','size' => 'sm','icon' => 'plus']); ?>Add Allocation <?php echo $__env->renderComponent(); ?>
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
            <?php endif; ?>
         <?php $__env->endSlot(); ?>

        <?php if($allocations->isEmpty()): ?>
            <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'network','title' => 'No Ports Allocated','description' => 'A server needs at least one address before it can accept players.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'network','title' => 'No Ports Allocated','description' => 'A server needs at least one address before it can accept players.']); ?>
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
            <?php if (isset($component)) { $__componentOriginal983d366924d4e4b5324edcaeffbb36b1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal983d366924d4e4b5324edcaeffbb36b1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-actions','data' => ['action' => route('server.bulk', [$server, 'allocations']),'label' => 'allocation']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('server.bulk', [$server, 'allocations'])),'label' => 'allocation']); ?>
             <?php $__env->slot('table', null, []); ?> 
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
                <tr><th class="w-10"><?php if (isset($component)) { $__componentOriginale7cc125ac67e961dd14784be2099d7c2 = $component; } ?>
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
                <th>Address</th>
                <th>Purpose</th>
                <th>Protocol</th>
                <th>Role</th>
                <th class="text-right vx-act-2">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php $__currentLoopData = $allocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allocation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                <td class="w-10"><?php if (isset($component)) { $__componentOriginale7cc125ac67e961dd14784be2099d7c2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale7cc125ac67e961dd14784be2099d7c2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select-toggle','data' => ['value' => $allocation->id,'label' => $allocation->address()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select-toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($allocation->id),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($allocation->address())]); ?>
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
                
                <td class="font-mono text-slate-900 vx-cell-wrap">
                <span class="text-slate-500 [overflow-wrap:anywhere]"><?php echo e($allocation->ip_alias ?: $allocation->ip); ?></span><wbr><span class="font-medium">:<?php echo e($allocation->port); ?></span>
                </td>
                <td class="text-slate-500"><?php echo e($allocation->roleLabel()); ?></td>
                <td><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => ''.e($allocation->protocol === 'both' ? 'info' : 'neutral').'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => ''.e($allocation->protocol === 'both' ? 'info' : 'neutral').'']); ?><?php echo e($allocation->protocolLabel()); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?></td>
                <td>
                <?php if($server->allocation_id === $allocation->id): ?>
                <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'success','dot' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'success','dot' => true]); ?>Primary <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'neutral']); ?>Additional <?php echo $__env->renderComponent(); ?>
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
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'allocation.update'])): ?>
                <?php if($server->allocation_id !== $allocation->id): ?>
                <form method="POST" action="<?php echo e(route('server.network.primary', [$server, $allocation])); ?>">
                <?php echo csrf_field(); ?><?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['type' => 'submit','icon' => 'star','title' => 'Make This The Primary Address']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'star','title' => 'Make This The Primary Address']); ?>
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
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'allocation.delete'])): ?>
                <?php if($server->allocation_id !== $allocation->id): ?>
                <?php if (isset($component)) { $__componentOriginalec2502b834f860c8e30d229aa8f280e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalec2502b834f860c8e30d229aa8f280e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.delete-button','data' => ['name' => 'release-allocation-'.e($allocation->id).'','action' => route('server.network.destroy', [$server, $allocation]),'title' => 'Release '.e($allocation->address()).'?','message' => 'The port goes back to the node\'s pool. Anything pointed at it stops connecting.','confirm' => 'Release','label' => 'Release Allocation']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('delete-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'release-allocation-'.e($allocation->id).'','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('server.network.destroy', [$server, $allocation])),'title' => 'Release '.e($allocation->address()).'?','message' => 'The port goes back to the node\'s pool. Anything pointed at it stops connecting.','confirm' => 'Release','label' => 'Release Allocation']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalec2502b834f860c8e30d229aa8f280e2)): ?>
<?php $attributes = $__attributesOriginalec2502b834f860c8e30d229aa8f280e2; ?>
<?php unset($__attributesOriginalec2502b834f860c8e30d229aa8f280e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalec2502b834f860c8e30d229aa8f280e2)): ?>
<?php $component = $__componentOriginalec2502b834f860c8e30d229aa8f280e2; ?>
<?php unset($__componentOriginalec2502b834f860c8e30d229aa8f280e2); ?>
<?php endif; ?>
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
             <?php $__env->endSlot(); ?>

            <?php if (isset($component)) { $__componentOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6940bd2b9fbcd0d4be46bbcc29cd11f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mass-action','data' => ['action' => 'release','icon' => 'trash','tone' => 'danger','confirm' => 'The ports go back to the node pool. Anything pointed at them stops connecting. The primary address is skipped.','confirmTitle' => 'Release These Ports?']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mass-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => 'release','icon' => 'trash','tone' => 'danger','confirm' => 'The ports go back to the node pool. Anything pointed at them stops connecting. The primary address is skipped.','confirm-title' => 'Release These Ports?']); ?>Release <?php echo $__env->renderComponent(); ?>
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
<?php /**PATH /var/www/gamemgr/resources/views/server/network.blade.php ENDPATH**/ ?>