/* Keeps the install card current while a server is installing.
 *
 * The card is server rendered and nothing refreshed it, so watching an install
 * meant reloading by hand. That was merely tedious until the Steam Guard prompt
 * existed: the node gives up after ten minutes, and a prompt that only appears
 * when somebody happens to press F5 is a prompt that expires unanswered.
 *
 * A reload rather than a fetch and patch. The card carries a progress bar, a
 * phase, a log tail and now a form, all rendered by Blade, and re-rendering
 * them here would be a second copy of that markup to keep in step.
 */
(function () {
    var root = document.querySelector('[data-install-watch]');
    if (!root) return;

    var every = Math.max(2, parseInt(root.getAttribute('data-install-watch'), 10) || 5) * 1000;

    /* Show the END of the log, which is the only part anyone is watching.
     *
     * The pane is a capped scroll box and a reload starts it at the top, so
     * polling produced a log that visibly refreshed and never moved: the newest
     * lines were always just off the bottom. Done on every load rather than only
     * while installing, because a failed install is read the same way, from the
     * error backwards. */
    function pinToBottom() {
        var pane = root.querySelector('.gm-code-pre');
        if (pane) pane.scrollTop = pane.scrollHeight;
    }

    pinToBottom();

    function typing() {
        var field = document.getElementById('guard-code');
        if (!field) return false;

        // Never reload out from under somebody mid answer. The code is five
        // characters typed off a phone against a thirty second clock, and
        // losing it to a refresh would be maddening in a way that is hard to
        // report and easy to blame on the panel.
        return document.activeElement === field || field.value.trim() !== '';
    }

    setInterval(function () {
        if (typing()) return;
        // A hidden tab is not being watched, and reloading it wakes a queue of
        // requests the moment somebody switches back.
        if (document.hidden) return;

        window.location.reload();
    }, every);
})();
