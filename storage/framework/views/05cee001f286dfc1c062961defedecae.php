<?php
    // Only after a create. Everything here comes from the reservation that just
    // happened, so there is nothing to fetch and nothing to get stale.
    $created = session('created_server');
?>

<?php if($created): ?>
    <div class="mb-6 overflow-hidden rounded-xl bg-white ring-1 ring-emerald-200 shadow-sm">
        <div class="flex items-center gap-2.5 border-b border-emerald-100 bg-emerald-50/60 px-5 py-3">
            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'check-circle','class' => 'h-5 w-5 shrink-0 text-emerald-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check-circle','class' => 'h-5 w-5 shrink-0 text-emerald-600']); ?>
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
            <p class="font-semibold text-emerald-900">Server Created</p>
            <span class="ml-auto text-xs text-emerald-700">Installing on <?php echo e($created['node']); ?></span>
        </div>

        <div class="grid gap-x-8 gap-y-4 px-5 py-4 sm:grid-cols-2">
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Players Connect To</p>
                <p class="mt-1 font-mono text-lg text-slate-900"><?php echo e($created['address']); ?></p>
                <?php if($created['dedicated']): ?>
                    <p class="mt-1 text-xs text-slate-500">On its own address, so the port is the game's own number.</p>
                <?php endif; ?>
            </div>

            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    <?php echo e(count($created['ports']) === 1 ? 'Port Reserved' : 'Ports Reserved'); ?>

                </p>
                <ul class="mt-1 space-y-0.5">
                    <?php $__currentLoopData = $created['ports']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $port): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="flex items-baseline gap-2 text-sm">
                            <span class="font-mono text-slate-900"><?php echo e($port['port']); ?>/<?php echo e($port['protocol']); ?></span>
                            <span class="text-slate-500"><?php echo e(implode(' + ', $port['roles'])); ?></span>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </div>

        
        <?php if($created['canonical'] === false): ?>
            <div class="border-t border-amber-100 bg-amber-50/60 px-5 py-3">
                <p class="text-sm text-amber-900">
                    <span class="font-medium">The usual port was taken.</span>
                    <?php echo e($created['canonical_port']); ?> was already in use on <?php echo e($created['ip']); ?>, so the whole set moved
                    by <?php echo e($created['shift'] > 0 ? '+' : ''); ?><?php echo e($created['shift']); ?>. Tell players
                    <span class="font-mono"><?php echo e($created['address']); ?></span>, not
                    <span class="font-mono"><?php echo e($created['canonical_port']); ?></span>.
                </p>
            </div>
        <?php endif; ?>

        <?php if(! empty($created['notes'])): ?>
            <div class="border-t border-slate-100 px-5 py-3">
                <?php $__currentLoopData = $created['notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p class="text-xs text-slate-500"><?php echo e($note); ?></p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/admin/servers/_created.blade.php ENDPATH**/ ?>