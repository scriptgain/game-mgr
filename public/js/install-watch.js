/* Keeps the install card current while a server is installing.
 *
 * The first version of this reloaded the whole page every five seconds. It
 * worked, and it was horrible to sit in front of: the view jumped, the log
 * pane lost its scroll position on every tick so following the output was
 * impossible, and any half-typed Steam Guard code was at the mercy of the
 * timer. Watching a forty gigabyte install that way is genuinely unpleasant.
 *
 * Now it polls a small JSON endpoint and patches the three things that change:
 * the phase, the bar, and the log. Nothing else on the page moves.
 *
 * One reload survives, and only one: when the install finishes or fails, the
 * card becomes a different card, with different actions and a different tone.
 * Rebuilding that here would be a second copy of the Blade, so the page is
 * reloaded exactly once at that moment and never again.
 */
(function () {
    var root = document.querySelector('[data-install-watch]');
    if (!root) return;

    var url = root.getAttribute('data-progress-url');
    if (!url) return;

    var every = Math.max(2, parseInt(root.getAttribute('data-install-watch'), 10) || 3) * 1000;
    var timer = null;

    var els = {
        phase: root.querySelector('[data-install-phase]'),
        percent: root.querySelector('[data-install-percent]'),
        bar: root.querySelector('[data-install-bar]'),
        log: root.querySelector('.gm-code-pre'),
    };

    /* Following means staying at the bottom, but only while the reader is
     * already there. Yanking the pane back down while somebody has scrolled up
     * to read an error is the same rudeness as the reload, in miniature. */
    function atBottom(pane) {
        return pane.scrollHeight - pane.scrollTop - pane.clientHeight < 40;
    }

    function paint(data) {
        if (els.phase && data.phase) els.phase.textContent = data.phase;

        if (els.percent) {
            els.percent.textContent = data.progress === null || data.progress === undefined
                ? 'In Progress'
                : data.progress + '%';
        }
        if (els.bar && data.progress !== null && data.progress !== undefined) {
            els.bar.style.width = Math.max(0, Math.min(100, data.progress)) + '%';
        }

        if (els.log && typeof data.log === 'string' && data.log !== els.log.textContent) {
            var follow = atBottom(els.log);
            els.log.textContent = data.log;
            if (follow) els.log.scrollTop = els.log.scrollHeight;
        }
    }

    function tick() {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
            .then(function (data) {
                // A Guard prompt appearing, or the install ending, both change
                // the shape of the card rather than its contents. That is the
                // one case worth a reload.
                if (!data.installing || (data.awaiting_guard && !root.querySelector('#guard-code'))) {
                    clearInterval(timer);
                    window.location.reload();

                    return;
                }
                paint(data);
            })
            .catch(function () {
                /* A blip mid-install is not worth reporting: the next tick
                 * either recovers or the node is genuinely gone, and the panel
                 * says that elsewhere. */
            });
    }

    if (els.log) els.log.scrollTop = els.log.scrollHeight;
    timer = setInterval(function () {
        if (document.hidden) return;
        tick();
    }, every);
})();
