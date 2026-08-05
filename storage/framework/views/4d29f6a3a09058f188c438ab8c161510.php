<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['server']));

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

foreach (array_filter((['server']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    use Illuminate\Support\Facades\Gate;

    $can = fn (string $permission) => Gate::allows('check', [$server, $permission]);
    $template = $server->template;

    $tabs = array_values(array_filter([
        ['Console', 'server.console', 'terminal', $can('control.console'), 'server.console'],
        ['Files', 'server.files', 'folder', $can('file.read'), 'server.files*'],
        ['Databases', 'server.databases', 'database', $can('database.read') && $server->database_limit > 0, 'server.databases*'],
        ['Backups', 'server.backups', 'archive', $can('backup.read') && $server->backup_limit > 0, 'server.backups*'],
        ['Schedules', 'server.schedules', 'clock', $can('schedule.read'), 'server.schedules*'],
        ['Players', 'server.players', 'user-group', $can('player.read') && (bool) ($template?->rcon_supported || $template?->query_protocol), 'server.players*'],
        ['Mods', 'server.mods', 'puzzle', $can('mod.read') && (bool) $template?->supportsMods(), 'server.mods*'],
        ['Worlds', 'server.worlds', 'map', $can('world.read'), 'server.worlds*'],
        ['Metrics', 'server.metrics', 'chart', $can('control.console'), 'server.metrics*'],
        ['Network', 'server.network', 'network', $can('allocation.read'), 'server.network*'],
        ['Users', 'server.users', 'users', $can('user.read'), 'server.users*'],
        ['Startup', 'server.startup', 'bolt', $can('startup.read'), 'server.startup*'],
        ['Activity', 'server.activity', 'book', $can('activity.read'), 'server.activity*'],
        ['Settings', 'server.settings', 'settings', true, 'server.settings*'],
    ], fn ($t) => $t[3]));
?>

<style>
    /* Plain CSS, not Tailwind: these classes are toggled by JS and a purged
       build has no way to know they were ever used. */
    .gm-tabs { display: flex; align-items: center; gap: .25rem; flex-wrap: nowrap; min-width: 0; }
    .gm-tab { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border-radius: .5rem;
              font-size: .875rem; font-weight: 500; color: #475569; white-space: nowrap; text-decoration: none;
              border: 1px solid transparent; transition: background .15s, color .15s, border-color .15s; }
    .gm-tab:hover { background: #f1f5f9; color: #0f172a; border-color: #e2e8f0; }
    .gm-tab.is-active { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; font-weight: 600; }
    .gm-tab svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
    .gm-tab[hidden] { display: none; }
    .gm-more { position: relative; }
    .gm-more-menu { position: absolute; right: 0; top: calc(100% + .5rem); z-index: 40; min-width: 13rem;
                    background: #fff; border-radius: .5rem; border: 1px solid #e2e8f0;
                    box-shadow: 0 10px 30px rgba(2,6,23,.12); padding: .25rem; }
    .gm-more-menu a { display: flex; align-items: center; gap: .625rem; padding: .5rem .625rem; border-radius: .375rem;
                      font-size: .875rem; color: #334155; text-decoration: none; white-space: nowrap; }
    .gm-more-menu a:hover { background: #f1f5f9; color: #0f172a; }
    .gm-more-menu a.is-active { background: #ede9fe; color: #5b21b6; font-weight: 600; }
</style>

<div class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm px-2 py-1.5 mb-6"
     x-data="serverTabs()" x-init="init()">
    <div class="flex items-center gap-2 min-w-0">
        <nav class="gm-tabs flex-1 min-w-0" x-ref="strip" aria-label="Server sections">
            <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$label, $route, $icon, $_, $pattern]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(route($route, $server)); ?>"
                   data-tab-index="<?php echo e($i); ?>"
                   class="gm-tab <?php echo e(request()->routeIs($pattern) ? 'is-active' : ''); ?>">
                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
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
        </nav>

        <div class="gm-more shrink-0" x-show="overflow.length" x-cloak
             @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                    class="gm-tab" :class="overflowHasActive && 'is-active'">
                More
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'chevron-down']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-down']); ?>
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
            <div class="gm-more-menu" x-show="open" x-cloak x-transition>
                <template x-for="tab in overflow" :key="tab.href">
                    <a :href="tab.href" :class="tab.active && 'is-active'" x-text="tab.label"></a>
                </template>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/server-tabs.blade.php ENDPATH**/ ?>