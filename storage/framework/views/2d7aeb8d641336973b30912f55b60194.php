<?php
    use App\Support\Format;

    // The page is a command deck, not a scroll. Above the tabs sits only what an
    // operator wants without asking: what state it is in, where players connect,
    // the power controls, and four live vitals. Everything reference shaped is a
    // tab, because a fact you read once a month should not push the console off
    // the screen every day.
    $detailTabs = [
        ['id' => 'console', 'label' => 'Console', 'icon' => 'terminal'],
        ['id' => 'overview', 'label' => 'Overview', 'icon' => 'info'],
        ['id' => 'limits', 'label' => 'Limits', 'icon' => 'cpu'],
        ['id' => 'startup', 'label' => 'Startup', 'icon' => 'bolt', 'count' => $server->variables->count() ?: null],
        ['id' => 'access', 'label' => 'Access', 'icon' => 'users', 'count' => $server->subusers->count() ?: null],
        ['id' => 'backups', 'label' => 'Backups', 'icon' => 'archive', 'count' => $server->backups->count() ?: null],
    ];

    $powerAction = route('server.power', $server);
    $canControl = $server->isControllable();

    // The first frame comes from the model, the frames after it from the node.
    // Both are stated here so a disabled button is never disabled for one reason
    // server side and a different one client side.
    $liveStart = '! controllable() || stats.state === \'running\' || stats.state === \'starting\'';
    $liveStop = '! controllable() || (stats.state !== \'running\' && stats.state !== \'starting\')';
    $liveRestart = '! controllable() || stats.state !== \'running\'';
    $liveKill = '! controllable() || stats.state === \'offline\'';
?>

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
    
    <div class="space-y-6 min-w-0"
         x-data="gameConsole({
            streamUrl: <?php echo \Illuminate\Support\Js::from($streamUrl)->toHtml() ?>,
            pollUrl: <?php echo \Illuminate\Support\Js::from(route('server.stats', $server))->toHtml() ?>,
            backlog: <?php echo \Illuminate\Support\Js::from($backlog)->toHtml() ?>,
            memory: <?php echo e((int) $server->memory); ?>,
            disk: <?php echo e((int) $server->disk); ?>,
            diskUsed: <?php echo e((int) $server->cached_disk); ?>,
            cpuLimit: <?php echo e((int) $server->cpu); ?>,
            state: <?php echo \Illuminate\Support\Js::from($server->power_state)->toHtml() ?>,
            status: <?php echo \Illuminate\Support\Js::from($server->status)->toHtml() ?>
         })">

        
        <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['flush' => true]); ?>
            <div class="px-5 sm:px-6 py-5 flex flex-wrap items-start justify-between gap-x-4 gap-y-4">
                <div class="flex items-start gap-4 min-w-0">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-200 shrink-0">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'server','class' => 'w-6 h-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'server','class' => 'w-6 h-6']); ?>
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
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-slate-900 [overflow-wrap:anywhere]"><?php echo e($server->name); ?></h1>
                            
                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                  :class="{
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200': stateTone() === 'emerald',
                                    'bg-amber-50 text-amber-700 ring-amber-200': stateTone() === 'amber',
                                    'bg-rose-50 text-rose-700 ring-rose-200': stateTone() === 'rose',
                                    'bg-slate-100 text-slate-600 ring-slate-200': stateTone() === 'slate',
                                  }">
                                <span class="relative flex h-2 w-2">
                                    <span x-show="stateTone() !== 'slate'"
                                          class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-70"
                                          :class="{
                                            'bg-emerald-500': stateTone() === 'emerald',
                                            'bg-amber-500': stateTone() === 'amber',
                                            'bg-rose-500': stateTone() === 'rose',
                                          }"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full"
                                          :class="{
                                            'bg-emerald-500': stateTone() === 'emerald',
                                            'bg-amber-500': stateTone() === 'amber',
                                            'bg-rose-500': stateTone() === 'rose',
                                            'bg-slate-400': stateTone() === 'slate',
                                          }"></span>
                                </span>
                                <span x-text="stateLabel()"><?php echo e($server->statusLabel()); ?></span>
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-2 text-sm text-slate-500">
                            <span class="[overflow-wrap:anywhere]">
                                <?php echo e($server->template?->game?->name ?? 'No Game'); ?>

                                &middot; <?php echo e($server->template?->name ?? 'No Template'); ?>

                            </span>
                            
                            <span class="hidden text-slate-300 sm:inline" aria-hidden="true">|</span>
                            <span class="inline-flex items-center gap-1.5 [overflow-wrap:anywhere]">
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'server','class' => 'w-3.5 h-3.5 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'server','class' => 'w-3.5 h-3.5 text-slate-400']); ?>
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
                                <?php echo e($server->node?->name ?? 'No Node'); ?>

                            </span>
                            <?php if($server->node?->location): ?>
                                <span class="inline-flex items-center gap-1.5 [overflow-wrap:anywhere]">
                                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'globe','class' => 'w-3.5 h-3.5 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'globe','class' => 'w-3.5 h-3.5 text-slate-400']); ?>
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
                                    <?php echo e($server->node->location->flag); ?> <?php echo e($server->node->location->name); ?>

                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <?php if (isset($component)) { $__componentOriginal99cb7941a32bc885956a1a595193ad66 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal99cb7941a32bc885956a1a595193ad66 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.runtime-badge','data' => ['runtime' => $server->runtime]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('runtime-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['runtime' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->runtime)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal99cb7941a32bc885956a1a595193ad66)): ?>
<?php $attributes = $__attributesOriginal99cb7941a32bc885956a1a595193ad66; ?>
<?php unset($__attributesOriginal99cb7941a32bc885956a1a595193ad66); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal99cb7941a32bc885956a1a595193ad66)): ?>
<?php $component = $__componentOriginal99cb7941a32bc885956a1a595193ad66; ?>
<?php unset($__componentOriginal99cb7941a32bc885956a1a595193ad66); ?>
<?php endif; ?>
                            <?php if($server->auto_restart): ?><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'neutral']); ?><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'refresh','class' => 'w-3.5 h-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'refresh','class' => 'w-3.5 h-3.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Auto Restart <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?><?php endif; ?>
                            <?php if($server->auto_update): ?><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['color' => 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'neutral']); ?><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'download','class' => 'w-3.5 h-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'download','class' => 'w-3.5 h-3.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Auto Update <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?><?php endif; ?>
                            <span class="font-mono text-xs text-slate-400" data-tip="Short id. This is what client URLs use."><?php echo e($server->uuid_short); ?></span>
                        </div>
                    </div>
                </div>

                
                
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['href' => ''.e(route('server.console', $server)).'','variant' => 'secondary','size' => 'sm','icon' => 'terminal']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('server.console', $server)).'','variant' => 'secondary','size' => 'sm','icon' => 'terminal']); ?>Client View <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['href' => ''.e(route('admin.servers.edit', $server)).'','size' => 'sm','icon' => 'edit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('admin.servers.edit', $server)).'','size' => 'sm','icon' => 'edit']); ?>Edit <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>

                    <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:inline-block" aria-hidden="true"></span>

                    <?php if($server->isSuspended()): ?>
                        <form method="POST" action="<?php echo e(route('admin.servers.unsuspend', $server)); ?>">
                            <?php echo csrf_field(); ?><?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','variant' => 'secondary','size' => 'sm','icon' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'secondary','size' => 'sm','icon' => 'check']); ?>Unsuspend <?php echo $__env->renderComponent(); ?>
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
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'suspend-server','action' => route('admin.servers.suspend', $server),'tone' => 'warn','title' => 'Suspend '.e($server->name).'?','message' => 'The server stops and the owner loses every control except reading. Files, backups and databases are untouched.','confirm' => 'Suspend','confirmVariant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'suspend-server','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.servers.suspend', $server)),'tone' => 'warn','title' => 'Suspend '.e($server->name).'?','message' => 'The server stops and the owner loses every control except reading. Files, backups and databases are untouched.','confirm' => 'Suspend','confirm-variant' => 'danger']); ?>
                            <?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['icon' => 'ban','title' => 'Suspend Server']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'ban','title' => 'Suspend Server']); ?>
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

                    <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'reinstall-server-admin','action' => route('admin.servers.reinstall', $server),'fields' => ['wipe' => 0],'tone' => 'warn','title' => 'Reinstall '.e($server->name).'?','message' => 'The install script runs again over this server. Game files are replaced. Worlds, configs and anything else in the data directory are kept.','confirm' => 'Reinstall']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'reinstall-server-admin','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.servers.reinstall', $server)),'fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['wipe' => 0]),'tone' => 'warn','title' => 'Reinstall '.e($server->name).'?','message' => 'The install script runs again over this server. Game files are replaced. Worlds, configs and anything else in the data directory are kept.','confirm' => 'Reinstall']); ?>
                        <?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['icon' => 'refresh','title' => 'Reinstall Server']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'refresh','title' => 'Reinstall Server']); ?>
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

                    
                    <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'reinstall-server-wipe','action' => route('admin.servers.reinstall', $server),'fields' => ['wipe' => 1],'tone' => 'danger','confirmVariant' => 'danger','title' => 'Wipe And Reinstall '.e($server->name).'?','message' => 'Everything in the data directory goes: worlds, configs, plugins, saves. The node holds the old contents until the reinstall succeeds and puts them back if it fails, but once it succeeds they are gone.','confirm' => 'Wipe And Reinstall']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'reinstall-server-wipe','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.servers.reinstall', $server)),'fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['wipe' => 1]),'tone' => 'danger','confirm-variant' => 'danger','title' => 'Wipe And Reinstall '.e($server->name).'?','message' => 'Everything in the data directory goes: worlds, configs, plugins, saves. The node holds the old contents until the reinstall succeeds and puts them back if it fails, but once it succeeds they are gone.','confirm' => 'Wipe And Reinstall']); ?>
                        <?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['icon' => 'trash','variant' => 'danger','title' => 'Wipe And Reinstall']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'trash','variant' => 'danger','title' => 'Wipe And Reinstall']); ?>
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

                    <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'delete-server','action' => route('admin.servers.destroy', $server),'method' => 'DELETE','tone' => 'danger','title' => 'Delete '.e($server->name).'?','message' => 'The server record, its backups and its databases are removed and its ports are freed. There is no undo.','confirm' => 'Delete Server','confirmVariant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'delete-server','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.servers.destroy', $server)),'method' => 'DELETE','tone' => 'danger','title' => 'Delete '.e($server->name).'?','message' => 'The server record, its backups and its databases are removed and its ports are freed. There is no undo.','confirm' => 'Delete Server','confirm-variant' => 'danger']); ?>
                        <?php if (isset($component)) { $__componentOriginal658398a0e73a18931bb7def04d911f42 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal658398a0e73a18931bb7def04d911f42 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon-button','data' => ['icon' => 'trash','title' => 'Delete Server','variant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'trash','title' => 'Delete Server','variant' => 'danger']); ?>
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
                </div>
            </div>

            
            <div class="border-t border-slate-100 bg-slate-50/60 px-5 sm:px-6 py-4">
                <div class="flex flex-wrap items-end gap-x-8 gap-y-4">
                    <div class="min-w-0 flex-1 basis-72 lg:max-w-lg">
                        <p class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-slate-700">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'network','class' => 'w-4 h-4 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'network','class' => 'w-4 h-4 text-slate-400']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Connect Address
                        </p>
                        <?php if($server->connectAddress()): ?>
                            <div class="space-y-2">
                                <?php if (isset($component)) { $__componentOriginal4689e078d981419fe3d32c3868109c4f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4689e078d981419fe3d32c3868109c4f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.copy-field','data' => ['value' => $server->connectAddress()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('copy-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->connectAddress())]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.copy-field','data' => ['value' => $server->address()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('copy-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->address())]); ?>
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
                        <?php else: ?>
                            <?php if (isset($component)) { $__componentOriginal4689e078d981419fe3d32c3868109c4f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4689e078d981419fe3d32c3868109c4f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.copy-field','data' => ['value' => $server->address()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('copy-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->address())]); ?>
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
                        <?php endif; ?>
                    </div>

                    <div class="min-w-0">
                        <p class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-slate-700">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'power','class' => 'w-4 h-4 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'power','class' => 'w-4 h-4 text-slate-400']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Power
                        </p>
                        <div class="flex flex-wrap items-center gap-1.5 rounded-xl bg-white p-1.5 ring-1 ring-slate-200">
                            <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'start-server-admin','action' => $powerAction,'method' => 'POST','title' => 'Start '.e($server->name).'?','message' => 'The server will boot and begin accepting players. A large world can take a minute or two to load.','confirm' => 'Start It','fields' => ['action' => 'start']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'start-server-admin','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($powerAction),'method' => 'POST','title' => 'Start '.e($server->name).'?','message' => 'The server will boot and begin accepting players. A large world can take a minute or two to load.','confirm' => 'Start It','fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['action' => 'start'])]); ?>
                                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['size' => 'sm','icon' => 'play','disabled' => ! $server->canStart(),':disabled' => ''.e($liveStart).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'sm','icon' => 'play','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $server->canStart()),':disabled' => ''.e($liveStart).'']); ?>Start <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
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

                            
                            <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'restart-server-admin','action' => $powerAction,'method' => 'POST','tone' => 'warn','title' => 'Restart '.e($server->name).'?','message' => 'Everyone online is dropped while the game stops and boots again. The world is saved first, so nothing is lost, but a busy server will notice.','confirm' => 'Restart','fields' => ['action' => 'restart']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'restart-server-admin','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($powerAction),'method' => 'POST','tone' => 'warn','title' => 'Restart '.e($server->name).'?','message' => 'Everyone online is dropped while the game stops and boots again. The world is saved first, so nothing is lost, but a busy server will notice.','confirm' => 'Restart','fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['action' => 'restart'])]); ?>
                                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'secondary','size' => 'sm','icon' => 'refresh','disabled' => ! $server->canRestart(),':disabled' => ''.e($liveRestart).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','size' => 'sm','icon' => 'refresh','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $server->canRestart()),':disabled' => ''.e($liveRestart).'']); ?>Restart <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
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

                            <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'stop-server-admin','action' => $powerAction,'method' => 'POST','tone' => 'warn','title' => 'Stop '.e($server->name).'?','message' => 'Everyone online is disconnected and the server stays down until somebody starts it again. The world is saved first, so nothing is lost.','confirm' => 'Stop It','fields' => ['action' => 'stop']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'stop-server-admin','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($powerAction),'method' => 'POST','tone' => 'warn','title' => 'Stop '.e($server->name).'?','message' => 'Everyone online is disconnected and the server stays down until somebody starts it again. The world is saved first, so nothing is lost.','confirm' => 'Stop It','fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['action' => 'stop'])]); ?>
                                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'secondary','size' => 'sm','icon' => 'stop','disabled' => ! $server->canStop(),':disabled' => ''.e($liveStop).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','size' => 'sm','icon' => 'stop','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $server->canStop()),':disabled' => ''.e($liveStop).'']); ?>Stop <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
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

                            <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'kill-server-admin','action' => $powerAction,'method' => 'POST','tone' => 'danger','title' => 'Kill '.e($server->name).'?','message' => 'Kill pulls the plug without letting the game save. Anything since the last autosave is lost. Use Stop unless the server has stopped responding entirely.','confirm' => 'Kill It','confirmVariant' => 'danger','fields' => ['action' => 'kill']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'kill-server-admin','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($powerAction),'method' => 'POST','tone' => 'danger','title' => 'Kill '.e($server->name).'?','message' => 'Kill pulls the plug without letting the game save. Anything since the last autosave is lost. Use Stop unless the server has stopped responding entirely.','confirm' => 'Kill It','confirm-variant' => 'danger','fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['action' => 'kill'])]); ?>
                                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'danger-soft','size' => 'sm','icon' => 'bolt-slash','disabled' => ! $server->canKill(),':disabled' => ''.e($liveKill).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'danger-soft','size' => 'sm','icon' => 'bolt-slash','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $server->canKill()),':disabled' => ''.e($liveKill).'']); ?>Kill <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
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
                        </div>
                    </div>
                </div>

                
                <p class="mt-3 text-xs text-slate-500">
                    <?php if(! $canControl): ?>
                        This server is <?php echo e(strtolower($server->statusLabel())); ?>, so power actions are refused by the panel until that clears.
                    <?php else: ?>
                        <span x-text="stats.state === 'running'
                            ? 'Running. Stop asks the game to save and exit; Kill does not.'
                            : 'Not running. Start boots it on ' + <?php echo \Illuminate\Support\Js::from($server->node?->name ?? 'its node')->toHtml() ?> + '.'"></span>
                    <?php endif; ?>
                </p>
            </div>
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

        
        <?php if($memoryFloor): ?>
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'warn','title' => 'Memory Is Below What This Template Is Built For']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warn','title' => 'Memory Is Below What This Template Is Built For']); ?>
                This server has <?php echo e(Format::mib($server->memory)); ?>. The smallest published blueprint for
                <?php echo e($server->template?->name); ?>, "<?php echo e($memoryFloor['name']); ?>", sets <?php echo e(Format::mib($memoryFloor['memory'])); ?>.
                The limit is written as a hard cgroup memory.max with swap disabled, so the process is killed
                outright rather than slowed down, and that usually lands during world load.
                <a href="<?php echo e(route('admin.servers.edit', $server)); ?>" class="font-semibold underline hover:no-underline">Raise The Memory Limit</a>.
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
        <?php endif; ?>

        <?php if($nodeCheck && ! $nodeCheck['alive']): ?>
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'danger','title' => 'The Node Is Not Answering']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'The Node Is Not Answering']); ?>
                <?php echo e($server->node?->name ?? 'The node'); ?> did not respond to a health check, so nothing about this
                install can move. Check the daemon is running and reachable before looking at the server itself.
                <a href="<?php echo e($server->node ? route('admin.nodes.show', $server->node) : '#'); ?>" class="font-semibold underline hover:no-underline">Open The Node</a>.
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
        <?php elseif($nodeCheck && ! $nodeCheck['authenticated']): ?>
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'danger','title' => 'The Node Rejected The Panel\'s Token']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'The Node Rejected The Panel\'s Token']); ?>
                <?php echo e($server->node?->name); ?> answers its health check, which needs no credential, but refused an
                authenticated call. The panel therefore cannot start the install, read the log or send a command,
                and this server will sit at <?php echo e(strtolower($server->statusLabel())); ?> until the token matches.
                Re-enroll the node or check its stored daemon secret.
                <a href="<?php echo e($server->node ? route('admin.nodes.show', $server->node) : '#'); ?>" class="font-semibold underline hover:no-underline">Open The Node</a>.
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
        <?php elseif($nodeCheck && $server->isInstalling()): ?>
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'info','title' => 'Waiting On The Node']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'info','title' => 'Waiting On The Node']); ?>
                <?php echo e($server->node?->name); ?> is answering and accepting the panel's token. The panel is waiting for
                the install to report that it finished. Until it does this server has no game files, so it cannot
                be started and commands have nowhere to go.
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
        <?php endif; ?>

        <?php if($server->isSuspended()): ?>
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'warn','title' => 'Suspended']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warn','title' => 'Suspended']); ?>
                The owner has no controls beyond reading. Files, backups and databases are untouched.
                Unsuspend from the header when the reason has cleared.
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
        <?php endif; ?>

        
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 min-w-0">
            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'cpu','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'cpu','class' => 'w-4 h-4']); ?>
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
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">CPU</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="(Math.round(stats.cpu * 10) / 10) + '%'"><?php echo e(round((float) $server->cached_cpu, 1)); ?>%</span>
                    <span class="text-xs text-slate-400">of <?php echo e((int) $server->cpu); ?>%</span>
                </p>
                <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['class' => 'mt-2.5','value' => $server->cached_cpu,'max' => max(1, $server->cpu),'live' => 'cpuPercent()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-2.5','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_cpu),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(max(1, $server->cpu)),'live' => 'cpuPercent()']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
            </div>

            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 ring-1 ring-sky-200">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'memory','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'memory','class' => 'w-4 h-4']); ?>
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
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">Memory</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="formatMib(stats.memory_mib)"><?php echo e(Format::mib($server->cached_memory)); ?></span>
                    <span class="text-xs text-slate-400">of <?php echo e(Format::mib($server->memory)); ?></span>
                </p>
                <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['class' => 'mt-2.5','value' => $server->cached_memory,'max' => max(1, $server->memory),'live' => 'memoryPercent()','liveTone' => 'memoryPercent() >= 90 ? \'bg-rose-500\' : (memoryPercent() >= 75 ? \'bg-amber-500\' : \'bg-brand-500\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-2.5','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_memory),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(max(1, $server->memory)),'live' => 'memoryPercent()','live-tone' => 'memoryPercent() >= 90 ? \'bg-rose-500\' : (memoryPercent() >= 75 ? \'bg-amber-500\' : \'bg-brand-500\')']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
            </div>

            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 ring-1 ring-indigo-200">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'database','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'database','class' => 'w-4 h-4']); ?>
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
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">Disk</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="formatMib(stats.disk_mib || <?php echo e((int) $server->cached_disk); ?>)"><?php echo e(Format::mib($server->cached_disk)); ?></span>
                    <span class="text-xs text-slate-400">of <?php echo e(Format::mib($server->disk)); ?></span>
                </p>
                <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['class' => 'mt-2.5','value' => $server->cached_disk,'max' => max(1, $server->disk),'live' => 'diskPercent()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-2.5','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_disk),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(max(1, $server->disk)),'live' => 'diskPercent()']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
            </div>

            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'user-group','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'user-group','class' => 'w-4 h-4']); ?>
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
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">Players</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="stats.players ?? 0"><?php echo e((int) $server->cached_players); ?></span>
                    <span class="text-xs text-slate-400">of <span x-text="stats.max_players || 0"><?php echo e((int) $server->cached_max_players); ?></span></span>
                </p>
                
                <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['class' => 'mt-2.5','value' => $server->cached_players,'max' => max(1, $server->cached_max_players),'live' => 'stats.max_players ? Math.min(100, Math.round((stats.players / stats.max_players) * 100)) : 0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-2.5','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_players),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(max(1, $server->cached_max_players)),'live' => 'stats.max_players ? Math.min(100, Math.round((stats.players / stats.max_players) * 100)) : 0']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
            </div>
        </div>

        
        <?php if (isset($component)) { $__componentOriginal6feca5f538f5448397e0ed369c078c27 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6feca5f538f5448397e0ed369c078c27 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tab-set','data' => ['tabs' => $detailTabs,'active' => 'console','label' => 'Server Sections']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab-set'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tabs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($detailTabs),'active' => 'console','label' => 'Server Sections']); ?>

            <?php if (isset($component)) { $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tab-pane','data' => ['id' => 'console']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'console']); ?>
                <?php if (isset($component)) { $__componentOriginalfb734d67d5e6428e339c887d7cc7455a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb734d67d5e6428e339c887d7cc7455a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.install-progress','data' => ['server' => $server]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('install-progress'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['server' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfb734d67d5e6428e339c887d7cc7455a)): ?>
<?php $attributes = $__attributesOriginalfb734d67d5e6428e339c887d7cc7455a; ?>
<?php unset($__attributesOriginalfb734d67d5e6428e339c887d7cc7455a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfb734d67d5e6428e339c887d7cc7455a)): ?>
<?php $component = $__componentOriginalfb734d67d5e6428e339c887d7cc7455a; ?>
<?php unset($__componentOriginalfb734d67d5e6428e339c887d7cc7455a); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginala560c2b2e4789e5b9a2b3a8031a1cfb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala560c2b2e4789e5b9a2b3a8031a1cfb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.live-console','data' => ['server' => $server,'height' => 'h-80 sm:h-[26rem] lg:h-[30rem]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('live-console'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['server' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server),'height' => 'h-80 sm:h-[26rem] lg:h-[30rem]']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala560c2b2e4789e5b9a2b3a8031a1cfb5)): ?>
<?php $attributes = $__attributesOriginala560c2b2e4789e5b9a2b3a8031a1cfb5; ?>
<?php unset($__attributesOriginala560c2b2e4789e5b9a2b3a8031a1cfb5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala560c2b2e4789e5b9a2b3a8031a1cfb5)): ?>
<?php $component = $__componentOriginala560c2b2e4789e5b9a2b3a8031a1cfb5; ?>
<?php unset($__componentOriginala560c2b2e4789e5b9a2b3a8031a1cfb5); ?>
<?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $attributes = $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $component = $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tab-pane','data' => ['id' => 'overview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'overview']); ?>
                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Operator Facts','icon' => 'info']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Operator Facts','icon' => 'info']); ?>
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div class="min-w-0">
                            <dt class="text-slate-500">Owner</dt>
                            <dd class="text-slate-900 [overflow-wrap:anywhere]">
                                <?php if($server->owner): ?>
                                    <a href="<?php echo e(route('admin.users.edit', $server->owner)); ?>" class="text-brand-700 hover:text-brand-800"><?php echo e($server->owner->name); ?></a>
                                    <span class="block text-xs text-slate-400"><?php echo e($server->owner->email); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400">None</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Node</dt>
                            <dd class="[overflow-wrap:anywhere]">
                                <?php if($server->node): ?>
                                    <a href="<?php echo e(route('admin.nodes.show', $server->node)); ?>" class="text-brand-700 hover:text-brand-800"><?php echo e($server->node->name); ?></a>
                                    <span class="block text-xs text-slate-400"><?php echo e($server->node->location?->flag); ?> <?php echo e($server->node->location?->name); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400">Unassigned</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Template</dt>
                            <dd class="[overflow-wrap:anywhere]">
                                <?php if($server->template): ?>
                                    <a href="<?php echo e(route('admin.templates.show', $server->template)); ?>" class="text-brand-700 hover:text-brand-800"><?php echo e($server->template->name); ?></a>
                                    <span class="block text-xs text-slate-400"><?php echo e($server->template->game?->name); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400">None</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if($minecraft = $server->minecraft()): ?>
                            
                            <div class="min-w-0">
                                <dt class="text-slate-500">Server Software</dt>
                                <dd class="text-slate-900 [overflow-wrap:anywhere]">
                                    <?php echo e(\Illuminate\Support\Str::headline(mb_strtolower($minecraft['type']))); ?> <?php echo e($minecraft['version']); ?>

                                    <span class="block text-xs text-slate-400">
                                        <?php if($minecraft['build']): ?>
                                            Pinned to build <?php echo e($minecraft['build']); ?>

                                        <?php else: ?>
                                            Newest build at each start
                                        <?php endif; ?>
                                    </span>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if($server->connectName()): ?>
                            <div class="min-w-0">
                                <dt class="text-slate-500">Connection Name</dt>
                                <dd class="font-mono text-xs text-slate-900 [overflow-wrap:anywhere]"><?php echo e($server->connectAddress()); ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Primary Allocation</dt>
                            <dd class="font-mono text-xs text-slate-900 [overflow-wrap:anywhere]"><?php echo e($server->address()); ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">All Allocations</dt>
                            <dd class="text-slate-900">
                                <?php if($server->allocations->isEmpty()): ?>
                                    <span class="text-slate-400">None</span>
                                <?php else: ?>
                                    <span class="flex flex-wrap gap-1.5">
                                        <?php $__currentLoopData = $server->allocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allocation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-700"><?php echo e($allocation->address()); ?></span>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Runtime</dt>
                            <dd><?php if (isset($component)) { $__componentOriginal99cb7941a32bc885956a1a595193ad66 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal99cb7941a32bc885956a1a595193ad66 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.runtime-badge','data' => ['runtime' => $server->runtime]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('runtime-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['runtime' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->runtime)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal99cb7941a32bc885956a1a595193ad66)): ?>
<?php $attributes = $__attributesOriginal99cb7941a32bc885956a1a595193ad66; ?>
<?php unset($__attributesOriginal99cb7941a32bc885956a1a595193ad66); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal99cb7941a32bc885956a1a595193ad66)): ?>
<?php $component = $__componentOriginal99cb7941a32bc885956a1a595193ad66; ?>
<?php unset($__componentOriginal99cb7941a32bc885956a1a595193ad66); ?>
<?php endif; ?></dd>
                        </div>
                        
                        <div class="min-w-0">
                            <dt class="text-slate-500">Installed</dt>
                            <dd class="text-slate-900"><?php echo e($server->installed_at?->diffForHumans() ?? 'Not Yet'); ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Created</dt>
                            <dd class="text-slate-900"><?php echo e($server->created_at?->diffForHumans()); ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Last Started</dt>
                            <dd class="text-slate-900"><?php echo e($server->last_started_at?->diffForHumans() ?? 'Never'); ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Last Crashed</dt>
                            <dd class="text-slate-900"><?php echo e($server->last_crashed_at?->diffForHumans() ?? 'Never'); ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Last Sample</dt>
                            <dd class="text-slate-900"><?php echo e($server->cached_at?->diffForHumans() ?? 'Never Taken'); ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">UUID</dt>
                            <dd class="font-mono text-xs text-slate-500 [overflow-wrap:anywhere]"><?php echo e($server->uuid); ?></dd>
                        </div>
                    </dl>

                    <?php if($server->description): ?>
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <p class="text-sm text-slate-500">Description</p>
                            <p class="mt-1 text-sm text-slate-800 [overflow-wrap:anywhere]"><?php echo e($server->description); ?></p>
                        </div>
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

                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Client Tools','icon' => 'link','subtitle' => 'The real tools live in the client area. These open it as this server.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Client Tools','icon' => 'link','subtitle' => 'The real tools live in the client area. These open it as this server.']); ?>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        <?php $__currentLoopData = $clientLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(route($link['route'], $server)); ?>"
                               class="flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm text-slate-700 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50 hover:text-slate-900 hover:ring-slate-400">
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $link['icon'],'class' => 'w-4 h-4 shrink-0 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($link['icon']),'class' => 'w-4 h-4 shrink-0 text-slate-400']); ?>
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
                                <span class="truncate"><?php echo e($link['label']); ?></span>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
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
<?php if (isset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $attributes = $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $component = $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tab-pane','data' => ['id' => 'limits']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'limits']); ?>
                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Resource Limits','icon' => 'memory','subtitle' => 'What the node enforces. Usage shown is the last cached sample.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Resource Limits','icon' => 'memory','subtitle' => 'What the node enforces. Usage shown is the last cached sample.']); ?>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['label' => 'Memory','value' => $server->cached_memory,'max' => max(1, $server->memory)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Memory','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_memory),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(max(1, $server->memory))]); ?>
                            <?php echo e(Format::mibPair($server->cached_memory, $server->memory)); ?>

                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['label' => 'Disk','value' => $server->cached_disk,'max' => max(1, $server->disk)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Disk','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_disk),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(max(1, $server->disk))]); ?>
                            <?php echo e(Format::mibPair($server->cached_disk, $server->disk)); ?>

                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['label' => 'CPU','value' => $server->cached_cpu,'max' => max(1, $server->cpu)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'CPU','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_cpu),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(max(1, $server->cpu))]); ?>
                            <?php echo e(round((float) $server->cached_cpu, 1)); ?> / <?php echo e((int) $server->cpu); ?>%
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $attributes = $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be)): ?>
<?php $component = $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be; ?>
<?php unset($__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be); ?>
<?php endif; ?>
                    </div>

                    <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-3">
                        <div><dt class="text-slate-500">Swap</dt><dd class="tabular text-slate-900"><?php echo e((int) $server->swap === -1 ? 'Unlimited' : Format::mib($server->swap)); ?></dd></div>
                        <div><dt class="text-slate-500">Block IO Weight</dt><dd class="tabular text-slate-900"><?php echo e($server->io); ?></dd></div>
                        <div><dt class="text-slate-500">OOM Killer</dt><dd class="text-slate-900"><?php echo e($server->oom_disabled ? 'Disabled' : 'Enabled'); ?></dd></div>
                    </dl>
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

                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Feature Caps','icon' => 'lock']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Feature Caps','icon' => 'lock']); ?>
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        
                        <div><dt class="text-slate-500">Databases</dt><dd class="tabular text-slate-900"><?php echo e($server->databases->count()); ?> / <?php echo e($server->database_limit > 0 ? $server->database_limit : 'None'); ?></dd></div>
                        <div><dt class="text-slate-500">Backups</dt><dd class="tabular text-slate-900"><?php echo e($server->backups->count()); ?> / <?php echo e($server->backup_limit > 0 ? $server->backup_limit : 'None'); ?></dd></div>
                        <div><dt class="text-slate-500">Allocations</dt><dd class="tabular text-slate-900"><?php echo e($server->allocations->count()); ?> / <?php echo e($server->allocation_limit > 0 ? $server->allocation_limit : 'None'); ?></dd></div>
                        <div><dt class="text-slate-500">Schedules</dt><dd class="tabular text-slate-900"><?php echo e($server->schedules->count()); ?></dd></div>
                    </dl>
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
<?php if (isset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $attributes = $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $component = $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tab-pane','data' => ['id' => 'startup']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'startup']); ?>
                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Runtime And Image','icon' => 'play']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Runtime And Image','icon' => 'play']); ?>
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div class="min-w-0">
                            <dt class="text-slate-500">Runtime</dt>
                            <dd class="mt-1"><?php if (isset($component)) { $__componentOriginal99cb7941a32bc885956a1a595193ad66 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal99cb7941a32bc885956a1a595193ad66 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.runtime-badge','data' => ['runtime' => $server->runtime]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('runtime-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['runtime' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->runtime)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal99cb7941a32bc885956a1a595193ad66)): ?>
<?php $attributes = $__attributesOriginal99cb7941a32bc885956a1a595193ad66; ?>
<?php unset($__attributesOriginal99cb7941a32bc885956a1a595193ad66); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal99cb7941a32bc885956a1a595193ad66)): ?>
<?php $component = $__componentOriginal99cb7941a32bc885956a1a595193ad66; ?>
<?php unset($__componentOriginal99cb7941a32bc885956a1a595193ad66); ?>
<?php endif; ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Image</dt>
                            <dd class="mt-1 font-mono text-xs text-slate-900 [overflow-wrap:anywhere]"><?php echo e($server->image ?: 'Not Set'); ?></dd>
                        </div>
                    </dl>
                    <div class="mt-5">
                        <?php if (isset($component)) { $__componentOriginal766da14cd9bbda5d69a52694b5aff6b7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.code-pane','data' => ['label' => 'Startup Command','code' => $server->startup,'empty' => 'This server has no startup command of its own.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('code-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Startup Command','code' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->startup),'empty' => 'This server has no startup command of its own.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7)): ?>
<?php $attributes = $__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7; ?>
<?php unset($__attributesOriginal766da14cd9bbda5d69a52694b5aff6b7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal766da14cd9bbda5d69a52694b5aff6b7)): ?>
<?php $component = $__componentOriginal766da14cd9bbda5d69a52694b5aff6b7; ?>
<?php unset($__componentOriginal766da14cd9bbda5d69a52694b5aff6b7); ?>
<?php endif; ?>
                    </div>
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

                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Variables','icon' => 'sliders','flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Variables','icon' => 'sliders','flush' => true]); ?>
                    <?php if($server->variables->isEmpty()): ?>
                        <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'settings','title' => 'No Variables','description' => 'This template exposes nothing configurable, so there is nothing stored per server.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'settings','title' => 'No Variables','description' => 'This template exposes nothing configurable, so there is nothing stored per server.']); ?>
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
                            <thead><tr><th>Name</th><th>Environment</th><th>Value</th></tr></thead>
                            <tbody>
                                <?php $__currentLoopData = $server->variables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variable): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td class="font-medium text-slate-900"><?php echo e($variable->variable?->name ?? 'Removed'); ?></td>
                                        <td class="font-mono text-xs text-slate-500"><?php echo e($variable->variable?->env_variable); ?></td>
                                        <td class="font-mono text-xs text-slate-700 [overflow-wrap:anywhere]"><?php echo e($variable->value === '' ? '(empty)' : $variable->value); ?></td>
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
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $attributes = $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $component = $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tab-pane','data' => ['id' => 'access']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'access']); ?>
                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Shared With','icon' => 'users','flush' => true,'subtitle' => 'Subusers hold a named permission list. The owner and admins are not listed here.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Shared With','icon' => 'users','flush' => true,'subtitle' => 'Subusers hold a named permission list. The owner and admins are not listed here.']); ?>
                    <?php if($server->subusers->isEmpty()): ?>
                        <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'users','title' => 'Not Shared','description' => 'Only the owner and panel administrators can reach this server.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'users','title' => 'Not Shared','description' => 'Only the owner and panel administrators can reach this server.']); ?>
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
                            <thead><tr><th>User</th><th>Email</th><th>Permissions</th></tr></thead>
                            <tbody>
                                <?php $__currentLoopData = $server->subusers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subuser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td class="font-medium text-slate-900"><?php echo e($subuser->user?->name); ?></td>
                                        <td class="text-slate-500"><?php echo e($subuser->user?->email); ?></td>
                                        <td class="tabular text-slate-500"><?php echo e(count($subuser->permissions ?? [])); ?></td>
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
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $attributes = $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $component = $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tab-pane','data' => ['id' => 'backups']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab-pane'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'backups']); ?>
                <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Backups','icon' => 'archive','flush' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Backups','icon' => 'archive','flush' => true]); ?>
                     <?php $__env->slot('actions', null, []); ?> 
                        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['href' => ''.e(route('server.backups', $server)).'','variant' => 'secondary','size' => 'sm','icon' => 'archive']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('server.backups', $server)).'','variant' => 'secondary','size' => 'sm','icon' => 'archive']); ?>Manage <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
                     <?php $__env->endSlot(); ?>
                    <?php if($server->backups->isEmpty()): ?>
                        <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'archive','title' => 'No Backups Taken','description' => 'Nothing has been captured for this server yet.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'archive','title' => 'No Backups Taken','description' => 'Nothing has been captured for this server yet.']); ?>
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
                        <ul class="divide-y divide-slate-100">
                            <?php $__currentLoopData = $server->backups->take(10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $backup): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="flex items-center justify-between gap-3 px-5 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm text-slate-800"><?php echo e($backup->name); ?></p>
                                        <p class="text-xs text-slate-400">
                                            <?php echo e(Format::bytes($backup->bytes)); ?> &middot; <?php echo e($backup->completed_at?->diffForHumans() ?? 'in progress'); ?>

                                        </p>
                                    </div>
                                    <?php if (isset($component)) { $__componentOriginale122a964aaade1f8044b1545740ce9f7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale122a964aaade1f8044b1545740ce9f7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-dot','data' => ['tone' => $backup->statusTone(),'label' => $backup->statusLabel()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-dot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($backup->statusTone()),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($backup->statusLabel())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale122a964aaade1f8044b1545740ce9f7)): ?>
<?php $attributes = $__attributesOriginale122a964aaade1f8044b1545740ce9f7; ?>
<?php unset($__attributesOriginale122a964aaade1f8044b1545740ce9f7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale122a964aaade1f8044b1545740ce9f7)): ?>
<?php $component = $__componentOriginale122a964aaade1f8044b1545740ce9f7; ?>
<?php unset($__componentOriginale122a964aaade1f8044b1545740ce9f7); ?>
<?php endif; ?>
                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
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
<?php if (isset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $attributes = $__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__attributesOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3)): ?>
<?php $component = $__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3; ?>
<?php unset($__componentOriginala2fc8c31fffe07bae4aa4430d1a6d2b3); ?>
<?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6feca5f538f5448397e0ed369c078c27)): ?>
<?php $attributes = $__attributesOriginal6feca5f538f5448397e0ed369c078c27; ?>
<?php unset($__attributesOriginal6feca5f538f5448397e0ed369c078c27); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6feca5f538f5448397e0ed369c078c27)): ?>
<?php $component = $__componentOriginal6feca5f538f5448397e0ed369c078c27; ?>
<?php unset($__componentOriginal6feca5f538f5448397e0ed369c078c27); ?>
<?php endif; ?>
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
<?php /**PATH /var/www/gamemgr/resources/views/admin/servers/show.blade.php ENDPATH**/ ?>