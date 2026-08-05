@props(['action', 'label' => 'item'])
{{-- Wraps a table so its rows can be selected and acted on together.

     The table deliberately sits OUTSIDE the form rather than inside it. Row
     actions use x-delete-button, which carries its own form, and a form inside
     a form is invalid HTML that browsers resolve by silently dropping one of
     them. So the bulk form holds only the action and the ids, and the selected
     ids are copied into it at submit time.

     That also means the row switches need no name and no form attribute: the
     checked boxes in the DOM are the single source of truth for what is
     selected, rather than a JS array that can drift out of step with them. --}}
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

    <form method="POST" action="{{ $action }}" x-ref="form" class="hidden">
        @csrf
        <input type="hidden" name="action" x-ref="action" value="">
    </form>

    {{ $table }}

    {{-- Sticky bar. Stays reachable however far down the list you selected,
         which is the whole point on a page with two hundred rows. --}}
    <div x-show="count > 0" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed inset-x-0 bottom-6 z-40 flex justify-center px-4 pointer-events-none">
        <div class="pointer-events-auto flex flex-wrap items-center gap-3 rounded-xl bg-chrome px-4 py-3 shadow-2xl ring-1 ring-white/10 max-w-full">
            <span class="text-sm font-medium text-white whitespace-nowrap">
                <span x-text="count"></span>
                <span x-text="count === 1 ? @js($label) : @js(\Illuminate\Support\Str::plural($label))"></span>
                selected
            </span>
            <span class="h-5 w-px bg-white/15"></span>
            <div class="flex flex-wrap items-center gap-2">
                {{ $slot }}
            </div>
            <button type="button" @click="clear()"
                    class="ml-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-300 hover:text-white hover:bg-white/10 transition whitespace-nowrap">
                Clear
            </button>
        </div>
    </div>
</div>
