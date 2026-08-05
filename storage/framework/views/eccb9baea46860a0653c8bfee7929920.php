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

    <div x-data="gameConsole({
            streamUrl: <?php echo \Illuminate\Support\Js::from($streamUrl)->toHtml() ?>,
            backlog: <?php echo \Illuminate\Support\Js::from($backlog)->toHtml() ?>,
            memory: <?php echo e((int) $server->memory); ?>,
            cpuLimit: <?php echo e((int) $server->cpu); ?>,
            state: <?php echo \Illuminate\Support\Js::from($server->power_state)->toHtml() ?>
         })" class="grid gap-6 lg:grid-cols-4">

        <div class="lg:col-span-3 space-y-4">
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
                <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2 text-sm">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'terminal','class' => 'w-4 h-4 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'terminal','class' => 'w-4 h-4 text-slate-400']); ?>
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
                        <span class="font-medium text-slate-900">Console</span>
                        <span class="inline-flex items-center gap-1.5 text-xs"
                              :class="connected ? 'text-emerald-600' : 'text-slate-400'">
                            <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                            <span x-text="connected ? 'Live' : 'Reconnecting'"></span>
                        </span>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-500 cursor-pointer">
                        <input type="checkbox" x-model="autoScroll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Follow Output
                    </label>
                </div>

                <div x-ref="output" @scroll="onScroll()"
                     class="console-pane vx-scroll rounded-none ring-0 h-[26rem] overflow-y-auto px-4 py-3 space-y-0.5">
                    <template x-for="(line, i) in lines" :key="i">
                        <div class="whitespace-pre-wrap break-words"
                             :class="{
                                'text-rose-300': line.includes('ERROR') || line.includes('/SEVERE'),
                                'text-amber-300': line.includes('WARN'),
                                'text-brand-300': line.startsWith('[gamemgr]'),
                             }"
                             x-text="line"></div>
                    </template>
                    <div x-show="!lines.length" class="text-slate-500">Waiting for output from the node.</div>
                </div>

                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'control.command'])): ?>
                    <form method="POST" action="<?php echo e(route('server.command', $server)); ?>"
                          @submit="remember()"
                          class="flex items-center gap-2 px-4 py-3 border-t border-slate-100">
                        <?php echo csrf_field(); ?>
                        <span class="text-slate-400 font-mono text-sm select-none">&gt;</span>
                        <input type="text" name="command" x-model="command" autocomplete="off"
                               @keydown.arrow-up.prevent="recall(1)" @keydown.arrow-down.prevent="recall(-1)"
                               placeholder="Type a command and press Enter"
                               :disabled="stats.state !== 'running'"
                               class="flex-1 min-w-0 rounded-lg border-0 bg-slate-50 px-3 py-2 text-sm font-mono text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500 disabled:opacity-60">
                        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','size' => 'sm',':disabled' => 'stats.state !== \'running\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','size' => 'sm',':disabled' => 'stats.state !== \'running\'']); ?>Send <?php echo $__env->renderComponent(); ?>
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

        <div class="space-y-4">
            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Power']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Power']); ?>
                <div class="grid grid-cols-2 gap-2">
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'control.start'])): ?>
                        <form method="POST" action="<?php echo e(route('server.power', $server)); ?>">
                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="start">
                            <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','icon' => 'play','class' => 'w-full','disabled' => ! $server->isControllable()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'play','class' => 'w-full','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $server->isControllable())]); ?>Start <?php echo $__env->renderComponent(); ?>
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
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'control.restart'])): ?>
                        <form method="POST" action="<?php echo e(route('server.power', $server)); ?>">
                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="restart">
                            <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','variant' => 'secondary','icon' => 'refresh','class' => 'w-full','disabled' => ! $server->isControllable()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'secondary','icon' => 'refresh','class' => 'w-full','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $server->isControllable())]); ?>Restart <?php echo $__env->renderComponent(); ?>
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
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('check', [$server, 'control.stop'])): ?>
                        <form method="POST" action="<?php echo e(route('server.power', $server)); ?>">
                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="stop">
                            <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','variant' => 'secondary','icon' => 'stop','class' => 'w-full','disabled' => ! $server->isControllable()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'secondary','icon' => 'stop','class' => 'w-full','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $server->isControllable())]); ?>Stop <?php echo $__env->renderComponent(); ?>
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
                        <?php if (isset($component)) { $__componentOriginalc44eb547b9799a0e4f62294149f13577 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc44eb547b9799a0e4f62294149f13577 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-action','data' => ['name' => 'kill-server','action' => route('server.power', $server),'method' => 'POST','tone' => 'danger','title' => 'Kill The Server?','message' => 'Kill pulls the plug without letting the game save. Anything since the last autosave is lost. Use Stop unless the server has stopped responding entirely.','confirm' => 'Kill It','confirmVariant' => 'danger','fields' => ['action' => 'kill'],'class' => 'w-full']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'kill-server','action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('server.power', $server)),'method' => 'POST','tone' => 'danger','title' => 'Kill The Server?','message' => 'Kill pulls the plug without letting the game save. Anything since the last autosave is lost. Use Stop unless the server has stopped responding entirely.','confirm' => 'Kill It','confirm-variant' => 'danger','fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['action' => 'kill']),'class' => 'w-full']); ?>
                            <button type="button" class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-rose-700 bg-white ring-1 ring-inset ring-rose-200 hover:bg-rose-50 hover:ring-rose-400 transition">
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'bolt-slash','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bolt-slash','class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Kill
                            </button>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Resources']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Resources']); ?>
                <div class="space-y-4">
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-slate-700">CPU</span>
                            <span class="tabular text-slate-500"><span x-text="Math.round(stats.cpu * 10) / 10"></span>% of <?php echo e($server->cpu); ?>%</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand-500 transition-all" :style="`width: ${cpuPercent()}%`"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-slate-700">Memory</span>
                            <span class="tabular text-slate-500" x-text="formatMib(stats.memory_mib) + ' / ' + formatMib(<?php echo e((int) $server->memory); ?>)"></span>
                        </div>
                        <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 :class="memoryPercent() >= 90 ? 'bg-rose-500' : (memoryPercent() >= 75 ? 'bg-amber-500' : 'bg-brand-500')"
                                 :style="`width: ${memoryPercent()}%`"></div>
                        </div>
                    </div>
                    <?php if (isset($component)) { $__componentOriginal5ec38a558c9e3dae1794d3b23f1df1be = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ec38a558c9e3dae1794d3b23f1df1be = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meter','data' => ['label' => 'Disk','value' => $server->cached_disk,'max' => $server->disk]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('meter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Disk','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->cached_disk),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->disk)]); ?>
                        <?php echo e(number_format($server->cached_disk)); ?> / <?php echo e(number_format($server->disk)); ?> MiB
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
                    <div class="flex items-center justify-between pt-1 text-sm">
                        <span class="font-medium text-slate-700">Players</span>
                        <span class="tabular text-slate-900 font-medium"><span x-text="stats.players"></span> / <span x-text="stats.max_players || '0'"></span></span>
                    </div>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Connect']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Connect']); ?>
                <?php if (isset($component)) { $__componentOriginal4689e078d981419fe3d32c3868109c4f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4689e078d981419fe3d32c3868109c4f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.copy-field','data' => ['value' => $server->address(),'label' => 'Address']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('copy-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($server->address()),'label' => 'Address']); ?>
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
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Runtime</span>
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
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Node</span>
                        <span class="text-slate-900 truncate"><?php echo e($server->node?->name); ?></span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Location</span>
                        <span class="text-slate-900 truncate"><?php echo e($server->node?->location?->flag); ?> <?php echo e($server->node?->location?->name); ?></span>
                    </div>
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
<?php /**PATH /var/www/gamemgr/resources/views/server/console.blade.php ENDPATH**/ ?>