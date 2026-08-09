
<?php
    $control = $variable->control();
    $id = $variable->id;
    $group = $group ?? 'variables';
    $field = $group.'['.$id.']';
    $value = (string) ($value ?? old($group.'.'.$id, $variable->default_value));
    $error = $errors->first($group.'.'.$id);
    $sleeping = isset($owner) ? "templateId !== '".$owner."'" : 'false';
    $locked = $locked ?? false;

    // A locked setting is shown as the value it holds and nothing else. The
    // fixed block already does exactly that, so there is one read only
    // presentation rather than a disabled copy of every control.
    if ($locked) {
        $control['type'] = 'fixed';
    }

    $wide = in_array($control['type'], ['switch', 'choice', 'select', 'textarea', 'fixed'], true);
?>

<div class="min-w-0 <?php echo e($wide ? 'sm:col-span-2' : ''); ?>">
    <?php if($control['type'] === 'switch'): ?>
        <?php
            $on = $control['on'];
            $offValue = $control['off'];
        ?>
        <div x-data="{ v: <?php echo \Illuminate\Support\Js::from($value === $on ? $on : $offValue)->toHtml() ?> }" class="flex items-start gap-3">
            <input type="hidden" name="<?php echo e($field); ?>" x-model="v"
                   data-env="<?php echo e($variable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>">
            <button type="button" role="switch" :aria-checked="(v === <?php echo \Illuminate\Support\Js::from($on)->toHtml() ?>).toString()"
                    @click="v = v === <?php echo \Illuminate\Support\Js::from($on)->toHtml() ?> ? <?php echo \Illuminate\Support\Js::from($offValue)->toHtml() ?> : <?php echo \Illuminate\Support\Js::from($on)->toHtml() ?>"
                    :class="v === <?php echo \Illuminate\Support\Js::from($on)->toHtml() ?> ? 'bg-brand-600' : 'bg-slate-300'"
                    class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
                <span :class="v === <?php echo \Illuminate\Support\Js::from($on)->toHtml() ?> ? 'translate-x-6' : 'translate-x-1'"
                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
            </button>
            <span class="min-w-0 text-sm">
                <span class="font-medium text-slate-900"><?php echo e($variable->name); ?></span>
                <?php if($variable->description): ?>
                    <span class="block text-slate-500"><?php echo e($variable->description); ?></span>
                <?php endif; ?>
                <span class="mt-1 block font-mono text-xs text-slate-400 break-words">
                    <?php echo e($variable->env_variable); ?> = <span x-text="v"><?php echo e($value); ?></span>
                </span>
                <?php if($error): ?><span class="mt-1 block text-sm text-rose-600"><?php echo e($error); ?></span><?php endif; ?>
            </span>
        </div>

    <?php elseif($control['type'] === 'fixed'): ?>
        <div class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <input type="hidden" name="<?php echo e($field); ?>" value="<?php echo e($value); ?>"
                       data-env="<?php echo e($variable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>">
                <span class="min-w-0 text-sm text-slate-700">
                    <?php echo e($variable->name); ?>

                    <span class="ms-1 font-mono text-xs text-slate-400"><?php echo e($variable->env_variable); ?></span>
                </span>
                <span class="inline-flex items-center gap-1.5 text-sm">
                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'lock','class' => 'w-3.5 h-3.5 text-slate-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'lock','class' => 'w-3.5 h-3.5 text-slate-400']); ?>
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
                    <span class="font-mono text-slate-900 break-words"><?php echo e($value === '' ? 'not set' : $value); ?></span>
                </span>
            </div>
            <?php if($variable->description): ?>
                <p class="mt-1 text-sm text-slate-500"><?php echo e($variable->description); ?></p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        
        <div class="space-y-1.5">
            <div class="flex items-baseline justify-between gap-3">
                <label for="var-<?php echo e($id); ?>" class="min-w-0 text-sm font-medium text-slate-700">
                    <?php echo e($variable->name); ?>

                    <?php if($variable->isRequired()): ?><span class="text-rose-500">*</span><?php endif; ?>
                </label>
                <span class="shrink-0 truncate font-mono text-[11px] text-slate-400"><?php echo e($variable->env_variable); ?></span>
            </div>

            <?php if($control['type'] === 'choice'): ?>
                <div class="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1" role="group" aria-label="<?php echo e($variable->name); ?>">
                    <?php $__currentLoopData = $control['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="min-w-0 flex-1 basis-24">
                            <input type="radio" name="<?php echo e($field); ?>" value="<?php echo e($option); ?>" <?php if($value === $option): echo 'checked'; endif; ?>
                                   data-env="<?php echo e($variable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>"
                                   class="peer sr-only">
                            <span class="block cursor-pointer truncate rounded-md px-2.5 py-1.5 text-center text-sm font-medium text-slate-600 transition
                                         hover:text-slate-900 peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-sm
                                         peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/60">
                                <?php echo e(\Illuminate\Support\Str::headline($option)); ?>

                            </span>
                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

            <?php elseif($control['type'] === 'select'): ?>
                <div class="sm:max-w-sm">
                    <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['id' => 'var-'.$id,'name' => $field,'dataEnv' => $variable->env_variable,'xBind:disabled' => ''.e($sleeping).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('var-'.$id),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($field),'data-env' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($variable->env_variable),'x-bind:disabled' => ''.e($sleeping).'']); ?>
                        <?php $__currentLoopData = $control['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($option); ?>" <?php if($value === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
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

            <?php elseif($control['type'] === 'number'): ?>
                <?php
                    $min = $control['min'];
                    $max = $control['max'];
                    $step = $control['step'];
                    $slider = $min !== null && $max !== null && ($max - $min) / $step <= 10000;
                    $seed = $value === '' || ! is_numeric($value) ? '' : $value + 0;
                ?>
                <div x-data="{ v: <?php echo \Illuminate\Support\Js::from($seed)->toHtml() ?> }" class="flex items-center gap-3">
                    <?php if($slider): ?>
                        <input type="range" min="<?php echo e($min); ?>" max="<?php echo e($max); ?>" step="<?php echo e($step); ?>" x-model.number="v"
                               aria-label="<?php echo e($variable->name); ?>"
                               class="h-2 min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-slate-200 accent-brand-600">
                    <?php endif; ?>
                    
                    <input type="number" id="var-<?php echo e($id); ?>" name="<?php echo e($field); ?>" x-model.number="v" step="<?php echo e($step); ?>"
                           value="<?php echo e($value); ?>"
                           data-env="<?php echo e($variable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>"
                           <?php if($min !== null): ?> min="<?php echo e($min); ?>" <?php endif; ?>
                           <?php if($max !== null): ?> max="<?php echo e($max); ?>" <?php endif; ?>
                           <?php if($variable->isRequired()): ?> required <?php endif; ?>
                           class="<?php echo e($slider ? 'w-24 shrink-0' : 'w-full sm:max-w-40'); ?> block rounded-lg border-0 bg-white px-3 py-2 text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                </div>

            <?php elseif($control['type'] === 'textarea'): ?>
                <textarea id="var-<?php echo e($id); ?>" name="<?php echo e($field); ?>" rows="2"
                          data-env="<?php echo e($variable->env_variable); ?>" x-bind:disabled="<?php echo e($sleeping); ?>"
                          <?php if($control['maxlength']): ?> maxlength="<?php echo e($control['maxlength']); ?>" <?php endif; ?>
                          <?php if($variable->isRequired()): ?> required <?php endif; ?>
                          class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"><?php echo e($value); ?></textarea>

            <?php elseif($control['type'] === 'secret'): ?>
                <div class="flex items-center gap-2">
                    <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['id' => 'var-'.$id,'name' => $field,'value' => $value,'class' => 'font-mono','dataEnv' => $variable->env_variable,'xBind:disabled' => ''.e($sleeping).'','required' => $variable->isRequired(),'maxlength' => $control['maxlength'] ?: 64,'minlength' => $control['minlength'],'placeholder' => $variable->isRequired() ? null : 'Blank for none']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('var-'.$id),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($field),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value),'class' => 'font-mono','data-env' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($variable->env_variable),'x-bind:disabled' => ''.e($sleeping).'','required' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($variable->isRequired()),'maxlength' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($control['maxlength'] ?: 64),'minlength' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($control['minlength']),'placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($variable->isRequired() ? null : 'Blank for none')]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'button','variant' => 'secondary','size' => 'sm','class' => 'shrink-0','@click' => 'generateSecret(\''.e($field).'\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','size' => 'sm','class' => 'shrink-0','@click' => 'generateSecret(\''.e($field).'\')']); ?>Generate <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
                </div>

            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['id' => 'var-'.$id,'name' => $field,'value' => $value,'dataEnv' => $variable->env_variable,'xBind:disabled' => ''.e($sleeping).'','required' => $variable->isRequired(),'maxlength' => $control['maxlength']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('var-'.$id),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($field),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value),'data-env' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($variable->env_variable),'x-bind:disabled' => ''.e($sleeping).'','required' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($variable->isRequired()),'maxlength' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($control['maxlength'])]); ?>
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
            <?php endif; ?>

            <?php if($error): ?>
                <p class="text-sm text-rose-600"><?php echo e($error); ?></p>
            <?php elseif($variable->description): ?>
                <p class="text-sm text-slate-500"><?php echo e($variable->description); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/admin/servers/_variable.blade.php ENDPATH**/ ?>