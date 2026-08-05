<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['action', 'label' => 'item']));

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

foreach (array_filter((['action', 'label' => 'item']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{
        count: 0,
        boxes() { return Array.from($el.querySelectorAll('input[data-select-row]')); },
        recount() { this.count = this.boxes().filter((b) => b.checked).length; },
        get everything() { const b = this.boxes(); return b.length > 0 && b.every((x) => x.checked); },
        toggleAll(on) { this.boxes().forEach((b) => { b.checked = on; }); this.recount(); },
        clear() { this.toggleAll(false); if ($refs.selectAll) $refs.selectAll.checked = false; },

        submitAs(action) {
            const form = $refs.form;
            form.querySelectorAll('input[data-bulk-id]').forEach((n) => n.remove());
            this.boxes().filter((b) => b.checked).forEach((b) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids[]';
                hidden.value = b.value;
                hidden.setAttribute('data-bulk-id', '');
                form.appendChild(hidden);
            });
            $refs.action.value = action;
            form.submit();
        },

        /* A single row's action goes through the same endpoint as the bulk one:
           select only that row, then submit. One authorised code path rather
           than two that can drift apart. */
        actOn(action, event) {
            this.toggleAll(false);
            const box = event.target.closest('tr')?.querySelector('input[data-select-row]');
            if (! box) return;
            box.checked = true;
            this.recount();
            this.submitAs(action);
        },
     }"
     @change="recount()"
     x-init="recount()">

    <form method="POST" action="<?php echo e($action); ?>" x-ref="form" class="hidden">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" x-ref="action" value="">
    </form>

    <?php echo e($table); ?>


    
    <div x-show="count > 0" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed inset-x-0 bottom-6 z-40 flex justify-center px-4 pointer-events-none">
        <div class="pointer-events-auto flex flex-wrap items-center gap-3 rounded-xl bg-chrome px-4 py-3 shadow-2xl ring-1 ring-white/10 max-w-full">
            <span class="text-sm font-medium text-white whitespace-nowrap">
                <span x-text="count"></span>
                <span x-text="count === 1 ? <?php echo \Illuminate\Support\Js::from($label)->toHtml() ?> : <?php echo \Illuminate\Support\Js::from(\Illuminate\Support\Str::plural($label))->toHtml() ?>"></span>
                selected
            </span>
            <span class="h-5 w-px bg-white/15"></span>
            <div class="flex flex-wrap items-center gap-2">
                <?php echo e($slot); ?>

            </div>
            <button type="button" @click="clear()"
                    class="ml-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-300 hover:text-white hover:bg-white/10 transition whitespace-nowrap">
                Clear
            </button>
        </div>
    </div>
</div>
<?php /**PATH /var/www/gamemgr/resources/views/components/mass-actions.blade.php ENDPATH**/ ?>