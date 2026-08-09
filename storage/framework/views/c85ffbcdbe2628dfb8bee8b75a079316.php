
<?php
    $group = $group ?? 'variables';
    $owner = $owner ?? null;
    $sleeping = $owner !== null ? "templateId !== '".$owner."'" : 'false';
    $island = 'mcjars-data-'.$picker->template->id.'-'.$group;
?>

<?php if(! $mc['available']): ?>
    <div class="min-w-0 sm:col-span-2">
        <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'info','title' => 'Live Version List Unavailable']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'info','title' => 'Live Version List Unavailable']); ?>
            The MCJars catalogue did not answer, so the server type and version stay plain text boxes for now.
            Whatever is typed is handed straight to the container, which resolves it itself, so nothing is blocked.
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
<?php else: ?>
    <div class="min-w-0 sm:col-span-2" x-data="minecraftPicker('<?php echo e($island); ?>')">
        
        <input type="hidden" name="<?php echo e($group); ?>[<?php echo e($picker->typeVariable->id); ?>]" x-model="type"
               value="<?php echo e($mc['type']); ?>"
               data-env="<?php echo e($picker->typeVariable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>">
        <input type="hidden" name="<?php echo e($group); ?>[<?php echo e($picker->versionVariable->id); ?>]" x-model="version"
               value="<?php echo e($mc['version']); ?>"
               data-env="<?php echo e($picker->versionVariable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>">
        
        <?php $__currentLoopData = $picker->buildVariables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $buildVariable): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($buildVariable): ?>
                <input type="hidden" name="<?php echo e($group); ?>[<?php echo e($buildVariable->id); ?>]"
                       x-model="buildValues['<?php echo e($buildVariable->id); ?>']"
                       value="<?php echo e($mc['builds'][$buildVariable->id] ?? ''); ?>"
                       data-env="<?php echo e($buildVariable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>">
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:ring-slate-300">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h4 class="text-sm font-semibold text-slate-900">Server Software</h4>
                <p class="text-xs text-slate-500">
                    Live from <span class="font-medium text-slate-600">MCJars</span>
                </p>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                The container downloads whatever is chosen here on its next start. Nothing is fetched by the panel.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                
                <div class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="mc-type-<?php echo e($island); ?>" class="min-w-0 text-sm font-medium text-slate-700">Server Type</label>
                        <span class="shrink-0 truncate font-mono text-[11px] text-slate-400"><?php echo e($picker->typeVariable->env_variable); ?></span>
                    </div>
                    <div class="mt-1.5">
                        <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['id' => 'mc-type-'.$island,'xModel' => 'type']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('mc-type-'.$island),'x-model' => 'type']); ?>
                            <?php $__currentLoopData = $mc['types']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option['code']); ?>"><?php echo e($option['name']); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $attributes = $__attributesOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__attributesOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $component = $__componentOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__componentOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500" x-text="typeNote()"></p>
                </div>

                
                <div class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="mc-version-<?php echo e($island); ?>" class="min-w-0 text-sm font-medium text-slate-700">Minecraft Version</label>
                        <span class="shrink-0 truncate font-mono text-[11px] text-slate-400"><?php echo e($picker->versionVariable->env_variable); ?></span>
                    </div>
                    <div class="mt-1.5">
                        
                        <div x-show="versionsUsable()">
                            <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['id' => 'mc-version-'.$island,'xModel' => 'version','xBind:disabled' => 'loadingVersions']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('mc-version-'.$island),'x-model' => 'version','x-bind:disabled' => 'loadingVersions']); ?>
                                <template x-for="row in visibleVersions()" :key="row.id">
                                    <option :value="row.id" x-text="versionLabel(row)"></option>
                                </template>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $attributes = $__attributesOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__attributesOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $component = $__componentOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__componentOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
                        </div>
                        <div x-show="!versionsUsable()" x-cloak>
                            <input type="text" x-model="version" maxlength="40"
                                   placeholder="LATEST"
                                   class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs" :class="versionsFailed ? 'text-amber-600' : 'text-slate-500'"
                       x-text="versionNote()"></p>
                </div>

                
                <div class="min-w-0" x-show="hasBuild()" x-cloak>
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="mc-build-<?php echo e($island); ?>" class="min-w-0 text-sm font-medium text-slate-700"
                               x-text="buildLabel()">Build</label>
                        <span class="shrink-0 truncate font-mono text-[11px] text-slate-400" x-text="buildEnv()"></span>
                    </div>
                    <div class="mt-1.5">
                        
                        <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['id' => 'mc-build-'.$island,'xModel' => 'build','@focus' => 'loadBuilds()','@mousedown' => 'loadBuilds()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('mc-build-'.$island),'x-model' => 'build','@focus' => 'loadBuilds()','@mousedown' => 'loadBuilds()']); ?>
                            <option value="">Newest Build</option>
                            <template x-for="row in buildChoices()" :key="row.value">
                                <option :value="row.value" x-text="buildOptionLabel(row)"></option>
                            </template>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $attributes = $__attributesOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__attributesOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $component = $__componentOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__componentOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500" x-text="buildNote()"></p>
                </div>
            </div>

            
            <div class="section-divider mt-4 pt-3" x-show="hasSnapshots()" x-cloak>
                <div class="flex items-start gap-3">
                    <button type="button" role="switch" :aria-checked="snapshots.toString()"
                            @click="snapshots = !snapshots"
                            :class="snapshots ? 'bg-brand-600' : 'bg-slate-300'"
                            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
                        <span :class="snapshots ? 'translate-x-6' : 'translate-x-1'"
                              class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                    </button>
                    <span class="min-w-0 text-sm">
                        <span class="font-medium text-slate-900">Show Snapshots And Pre-Releases</span>
                        <span class="block text-slate-500">
                            Off by default. Snapshot builds are for testing and are not expected to survive an upgrade.
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    
    <script type="application/json" id="<?php echo e($island); ?>"><?php echo json_encode($mc, 15, 512) ?></script>
<?php endif; ?>
<?php /**PATH /var/www/gamemgr/resources/views/admin/servers/_minecraft.blade.php ENDPATH**/ ?>