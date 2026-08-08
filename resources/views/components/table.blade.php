@props(['flush' => false])
{{-- Styled table. Consumers write plain <thead>/<tbody>/<th>/<td>; cell styling
     is applied automatically. The table always fits its container (fixed layout):
     long text cells truncate with an ellipsis and get a hover tooltip, so the
     table never scrolls sideways. Plain CSS so the purged build can't strip it. --}}
<style>
    /* Nothing in this panel scrolls sideways. A fixed layout plus width:100%
       means the table can never be wider than its box no matter how many
       columns it has: long values truncate with an ellipsis and get a hover
       tooltip instead. The wrapper clips as a belt-and-braces measure, because
       one stray min-width on a control inside a cell would otherwise be enough
       to reintroduce a horizontal scrollbar. */
    .vx-table-wrap { max-width: 100%; overflow: hidden; }
    .vx-table { width: 100%; max-width: 100%; table-layout: fixed; }
    .vx-table td, .vx-table th { white-space: nowrap; }
    /* Truncate text cells to their column; leave cells holding controls alone. */
    .vx-table td:not(:has(button, form, input, select, .vx-badge)),
    .vx-table th:not(:has(button, form, input, select, .vx-badge)) { overflow: hidden; text-overflow: ellipsis; }
    /* Selection and other narrow utility columns size to their control rather
       than taking an equal share of a fixed-layout table. */
    .vx-table th.w-10, .vx-table td.w-10 { width: 3.75rem; }

    /* The trailing column is almost always actions. Every action is a 2.25rem
       icon button with a 0.25rem gap, so the reservation is stated in terms of
       how many there are instead of a guessed pixel value. Text buttons used to
       live here and got clipped, which is precisely why they are gone. */
    .vx-table th.text-right:last-child, .vx-table td.text-right:last-child { width: 8.5rem; }
    /* These reservations size the column for its BUTTONS, but the header word
       has to fit too. "Actions" is about 4.6rem at this size, so a 3.75rem
       column clipped the label and it read as though buttons were being cut
       off. Each width is now the greater of the buttons and the word. */
    .vx-table th.vx-act-1:last-child, .vx-table td.vx-act-1:last-child { width: 6rem; }
    .vx-table th.vx-act-2:last-child, .vx-table td.vx-act-2:last-child { width: 6.5rem; }
    .vx-table th.vx-act-3:last-child, .vx-table td.vx-act-3:last-child { width: 9rem; }
    .vx-table th.vx-act-4:last-child, .vx-table td.vx-act-4:last-child { width: 11.5rem; }
    /* Kept so existing markup does not break; same size as two actions. */
    .vx-table th.text-right.vx-col-sm:last-child, .vx-table td.text-right.vx-col-sm:last-child { width: 6.25rem; }

    /* Action cells never truncate: an icon button that is half visible is worse
       than a narrower reading column. */
    .vx-table td.text-right:last-child { overflow: visible; }
    /* A cell whose whole value has to be readable wraps instead of truncating.
       Nothing here scrolls sideways, so the only alternative is more rows. */
    .vx-table th.vx-cell-wrap, .vx-table td.vx-cell-wrap { white-space: normal; overflow: visible; text-overflow: clip; }
    .vx-table td > *, .vx-table th > * { min-width: 0; max-width: 100%; }
    .vx-table input, .vx-table select, .vx-table textarea { min-width: 0; max-width: 100%; }
</style>
<div class="vx-table-wrap {{ $flush ? '' : 'rounded-xl ring-1 ring-slate-200 bg-white shadow-sm' }}">
    <table {{ $attributes->merge(['class' =>
        'vx-table w-full text-sm text-left tabular '
        . '[&_thead]:bg-slate-50 [&_thead_th]:px-4 [&_thead_th]:py-3 [&_thead_th]:font-medium [&_thead_th]:text-xs [&_thead_th]:uppercase [&_thead_th]:tracking-wide [&_thead_th]:text-slate-500 '
        . '[&_tbody_tr]:border-t [&_tbody_tr]:border-slate-100 [&_tbody_tr:hover]:bg-slate-50/60 '
        . '[&_tbody_td]:px-4 [&_tbody_td]:py-3 [&_tbody_td]:text-slate-700 [&_tbody_td]:align-middle']) }}>
        {{ $slot }}
    </table>
</div>
<script>
    // Truncated cells get the app's styled [data-tip] tooltip (handled by the
    // global delegated listener in the layout), never the browser's native
    // title bubble, which is slow, unstyled, and can't be positioned.
    (function () {
        // Cells holding controls are never truncated by the CSS above, so a tooltip
        // on one is always wrong: it would repeat every button label in the cell as
        // one run-on string. Mirror the CSS exclusion list exactly.
        // Links stay eligible: a truncated URL still wants its tooltip.
        var CONTROLS = 'button, form, input, select, .vx-badge';

        function tag() {
            document.querySelectorAll('.vx-table td').forEach(function (td) {
                // Skip cells that already carry a rich [data-tip] tooltip.
                if (td.querySelector('[data-tip]') || td.hasAttribute('data-tip')) return;
                if (td.matches(CONTROLS) || td.querySelector(CONTROLS)) return;
                if (td.scrollWidth > td.clientWidth + 1) {
                    td.removeAttribute('title');
                    td.setAttribute('data-tip', td.textContent.trim());
                }
            });
        }
        if (document.readyState !== 'loading') tag();
        else document.addEventListener('DOMContentLoaded', tag);
        // Column widths change with the viewport: re-evaluate so cells that stop
        // being truncated lose the tip and newly-truncated ones gain it.
        var t;
        window.addEventListener('resize', function () {
            clearTimeout(t);
            t = setTimeout(function () {
                document.querySelectorAll('.vx-table td[data-tip]').forEach(function (td) {
                    if (td.scrollWidth <= td.clientWidth + 1) td.removeAttribute('data-tip');
                });
                tag();
            }, 150);
        });
    })();
</script>
