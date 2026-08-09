/* GameMGR front-end behaviour.
 *
 * ORDERING HAZARD: this file MUST load before the Alpine CDN script, which the
 * x-tailwind-cdn component pulls in. Alpine fires alpine:init the moment it
 * starts, so anything registered after that point silently never exists, and
 * inline x-data keeps working either way, which is what makes it easy to miss.
 */
document.addEventListener('alpine:init', () => {

    /* --------------------------------------------------------------- wizards
     * One implementation of "a long form broken into steps", shared by the
     * node form, the server create form and the template form.
     *
     * It was written twice before this and shared zero times, which meant every
     * fix to how steps validate or how the rail unlocks had to be made in two
     * places and, predictably, sometimes was not. The two copies had already
     * drifted: one looked its panels up by $refs and the other by a data-step
     * attribute, one honoured prefers-reduced-motion and the other did not.
     *
     * Spread it into a factory and add whatever that form actually needs:
     *
     *   Alpine.data('thingWizard', (seed) => ({
     *       ...wizardCore({ total: 6, step: seed.step, editing: seed.editing }),
     *       ...
     *   }))
     */
    function wizardCore(options = {}) {
        const total = options.total || 1;

        return {
            total,
            // The node markup says `last`; kept as an alias rather than
            // rewriting every x-show in a form that already works.
            last: total,
            step: options.step || 1,
            editing: !! options.editing,
            /* Create walks forward and later steps stay locked. Edit unlocks the
             * lot, because changing one field must not cost five screens. */
            furthest: options.editing ? total : (options.step || 1),

            /* Used by the rail to disable what cannot be reached yet. Forward
             * movement is gated by validation in go() rather than by this, so a
             * step becomes reachable exactly when the ones before it are valid. */
            unlocked(n) {
                return this.editing || n <= this.furthest;
            },

            /* Panels are found by $refs first and by data-step second, because
             * the two original wizards each used one of those and neither is
             * worth rewriting to match the other. */
            panelFor(n) {
                if (this.$refs && this.$refs['step' + n]) return this.$refs['step' + n];

                return document.querySelector('[data-step="' + n + '"]');
            },

            /* The browser is the authority on whether a field is valid; this
             * just asks it, and can do so safely because every control it looks
             * at is visible in an open step. The form carries novalidate for
             * exactly that reason: the browser cannot scroll to, or complain
             * about, a control inside a step that is currently hidden. */
            validateStep(n) {
                const panel = this.panelFor(n);
                if (! panel) return true;

                for (const el of panel.querySelectorAll('input, select, textarea')) {
                    if (el.disabled || el.type === 'hidden' || el.offsetParent === null) continue;
                    if (! el.checkValidity()) {
                        el.focus();
                        el.reportValidity();

                        return false;
                    }
                }

                return true;
            },

            /* Override to react to a step opening, for instance to build a
             * summary on the last one. */
            onStep() {},

            go(n) {
                if (n === this.step) return;

                /* Moving forward validates everything being skipped over and
                 * stops at the first step that is not happy, which is what makes
                 * clicking straight to Review from step two safe. Going back is
                 * always allowed: nobody should have to fix a later step to
                 * return to an earlier one. */
                if (n > this.step) {
                    for (let i = this.step; i < n; i++) {
                        if (! this.validateStep(i)) {
                            this.step = i;

                            return;
                        }
                        this.furthest = Math.max(this.furthest, i + 1);
                    }
                } else if (! this.unlocked(n)) {
                    return;
                }

                this.step = n;
                if (n > this.furthest) this.furthest = n;
                this.onStep(n);
                window.scrollTo({ top: 0, behavior: this.reduced() ? 'auto' : 'smooth' });
            },

            next() {
                if (this.step < this.total) this.go(this.step + 1);
            },

            back() {
                if (this.step > 1) this.go(this.step - 1);
            },

            /* Enter anywhere but the last step advances rather than submitting a
             * half-filled form. Textareas keep their newlines. */
            onEnter(event) {
                if (this.step >= this.total) return;
                if (event.target && event.target.tagName === 'TEXTAREA') return;
                event.preventDefault();
                this.next();
            },

            reduced() {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            },

            progress() {
                return { width: Math.round(this.step / this.total * 100) + '%' };
            },
        };
    }

    /* Registered so a form with no state of its own can use the wizard
     * directly: x-data="formWizard({ total: 7, step: 1, editing: true })". */
    Alpine.data('formWizard', (options = {}) => wizardCore(options));

    /* ------------------------------------------------------------------ tabs
     * A server has more tabs than fit on any real screen. Nothing in this panel
     * is allowed to scroll sideways, so the strip measures itself against the
     * space it actually has and folds whatever will not fit into a dropdown.
     */
    Alpine.data('serverTabs', () => ({
        open: false,
        overflow: [],
        overflowHasActive: false,

        init() {
            // Measure immediately so the layout is right, but do NOT reveal the
            // strip yet. Fonts landing late changes every label's width and can
            // change the answer: measured with the fallback font, Network fits;
            // measured with the real one it does not. Revealing after the first
            // pass therefore showed Network for about 50ms and then took it
            // away, which is exactly the flash this was meant to stop.
            this.measure();

            const reveal = () => {
                this.measure();
                this.$refs.strip?.classList.add('is-measured');
            };

            if (document.fonts && document.fonts.status !== 'loaded') {
                document.fonts.ready.then(reveal);
                // A font that never resolves must not leave the navigation
                // invisible forever.
                setTimeout(reveal, 500);
            } else {
                reveal();
            }
            let t;
            window.addEventListener('resize', () => {
                clearTimeout(t);
                t = setTimeout(() => this.measure(), 120);
            });
        },

        measure() {
            const strip = this.$refs.strip;
            if (!strip) return;

            const tabs = Array.from(strip.querySelectorAll('[data-tab-index]'));
            tabs.forEach((el) => { el.hidden = false; });

            // Reserve room for the More button itself, or the last tab that
            // "fits" pushes the button off the edge.
            const RESERVE = 96;
            const available = strip.clientWidth - RESERVE;

            let used = 0;
            const hidden = [];

            tabs.forEach((el) => {
                const w = el.offsetWidth + 4; // + gap
                if (used + w <= available) {
                    used += w;
                    return;
                }
                el.hidden = true;
                hidden.push({
                    // innerHTML, not textContent: it already holds the icon svg
                    // the strip rendered, so the dropdown shows the same icon
                    // without the component having to know how to draw one.
                    html: el.innerHTML,
                    label: el.textContent.trim(),
                    href: el.getAttribute('href'),
                    active: el.classList.contains('is-active'),
                });
            });

            // An active tab must never be the one that got hidden: if it is,
            // the user cannot see where they are.
            const activeHidden = hidden.find((t) => t.active);
            if (activeHidden && hidden.length) {
                const activeEl = tabs.find((el) => el.classList.contains('is-active'));
                if (activeEl) {
                    activeEl.hidden = false;
                    // Hide the last visible tab instead to make room for it.
                    const visible = tabs.filter((el) => !el.hidden);
                    const victim = visible[visible.length - 2];
                    if (victim && victim !== activeEl) {
                        victim.hidden = true;
                        hidden.push({
                            html: victim.innerHTML,
                            label: victim.textContent.trim(),
                            href: victim.getAttribute('href'),
                            active: false,
                        });
                    }
                    const i = hidden.indexOf(activeHidden);
                    if (i > -1) hidden.splice(i, 1);
                }
            }

            this.overflow = hidden;
            this.overflowHasActive = hidden.some((t) => t.active);
        },
    }));

    /* -------------------------------------------------------- in-page tabs
     * serverTabs above is a strip of links to other pages. This drives
     * x-tab-set, which switches panes on the page you are already on: a detail
     * screen with seven stacked cards becomes five short ones you choose
     * between, and the reader never scrolls past what they did not want.
     *
     * Panes are shown by toggling a plain CSS class, never x-show, so the
     * default pane is visible in the server-rendered HTML and there is no blank
     * frame while the Alpine CDN script is still in flight.
     */
    Alpine.data('tabSet', (initial, ids) => ({
        tab: ids.includes(initial) ? initial : ids[0],

        init() {
            // A link can address a pane: /admin/templates/9#startup.
            const hash = decodeURIComponent(String(window.location.hash || '').slice(1));
            if (ids.includes(hash)) this.tab = hash;
        },

        select(id) {
            if (!ids.includes(id)) return;
            this.tab = id;
            // replaceState, not location.hash: assigning the hash makes the
            // browser jump to the pane and the reader loses their place.
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + id);
            }
        },

        // role="tablist" promises arrow keys work, so they do.
        onKey(e) {
            const step = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
            if (!step) return;
            e.preventDefault();
            const next = ids[(ids.indexOf(this.tab) + step + ids.length) % ids.length];
            this.select(next);
            this.$nextTick(() => {
                const el = this.$root.querySelector('[data-tab-id="' + next + '"]');
                if (el) el.focus();
            });
        },
    }));

    /* ------------------------------------------------------------ copy pane
     * The copy button on an x-code-pane. Reads the pane's own text rather than
     * a value duplicated into an attribute, so a two kilobyte startup script is
     * not sent to the browser twice.
     */
    Alpine.data('copyPane', () => ({
        copied: false,
        timer: null,

        async copy() {
            const pane = this.$refs.pane;
            if (!pane) return;
            const text = pane.innerText;

            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                // The clipboard API needs a secure context. A panel reached over
                // plain http on a LAN address is not one, so fall back.
                const sink = document.createElement('textarea');
                sink.value = text;
                sink.setAttribute('readonly', '');
                sink.style.position = 'fixed';
                sink.style.top = '-1000px';
                sink.style.opacity = '0';
                document.body.appendChild(sink);
                sink.select();
                try { document.execCommand('copy'); } catch (err) { /* nothing left to try */ }
                document.body.removeChild(sink);
            }

            this.copied = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.copied = false; }, 1600);
        },
    }));

    /* --------------------------------------------------------------- console
     * Live console output and stats over Server-Sent Events straight from the
     * node daemon. SSE rather than a websocket because the feed is one-way, it
     * survives every proxy that already speaks HTTP, and it keeps the daemon on
     * the Go standard library with no vendored dependency.
     *
     * One component, two call sites: the client console and the admin server
     * page. Everything that differs between them is a config key, so there is
     * never a second console implementation to keep in step.
     *
     *   streamUrl  SSE endpoint on the node (optional)
     *   pollUrl    panel-side stats+backlog endpoint used when SSE never opens
     *   backlog    server-rendered starting lines
     *   memory     memory limit in MiB, for the gauge
     *   cpuLimit   cpu limit in percent, for the gauge
     *   state      power state at render time
     *   status     lifecycle status at render time (installing, suspended, ...)
     */
    Alpine.data('gameConsole', (config) => ({
        lines: config.backlog || [],
        connected: false,
        polled: false,
        unreachable: false,
        autoScroll: true,
        command: '',
        history: [],
        historyIndex: -1,
        stats: { cpu: 0, memory_mib: 0, memory_cap_mib: config.memory || 0, players: 0, state: config.state || 'offline' },
        status: config.status || null,
        source: null,
        poller: null,
        watchdog: null,
        MAX_LINES: 2000,

        init() {
            this.$nextTick(() => this.scroll());
            this.connect();
            // The stream can fail without ever raising an error the browser
            // hands back (a proxy that buffers, a node that answers 401). If it
            // has not opened by now, take the panel-side route instead.
            this.watchdog = setTimeout(() => { if (!this.connected) this.startPolling(); }, 4000);
            // A tab left open for a day must not accumulate a day of DOM.
            this.$watch('lines', () => {
                if (this.lines.length > this.MAX_LINES) {
                    this.lines.splice(0, this.lines.length - this.MAX_LINES);
                }
            });
        },

        destroy() {
            clearTimeout(this.watchdog);
            this.stopPolling();
            if (this.source) this.source.close();
        },

        connect() {
            if (!config.streamUrl || typeof EventSource === 'undefined') return;

            try {
                this.source = new EventSource(config.streamUrl);
            } catch (e) {
                return;
            }

            this.source.addEventListener('open', () => {
                this.connected = true;
                this.stopPolling();
            });

            this.source.addEventListener('console', (e) => {
                this.lines.push(e.data);
                this.$nextTick(() => this.scroll());
            });

            this.source.addEventListener('stats', (e) => {
                try { this.stats = JSON.parse(e.data); } catch (err) { /* ignore a partial frame */ }
            });

            this.source.addEventListener('error', () => {
                this.connected = false;
                // EventSource reconnects on its own; closing here would stop it.
                // Polling runs alongside so the gauges keep moving meanwhile.
                this.startPolling();
            });
        },

        /* ------------------------------------------------------------ polling
         * The panel reaches the node server side, so this path works whenever
         * the browser cannot hold the stream open itself. It is deliberately
         * slower than SSE: this is the degraded mode, not the normal one.
         */
        startPolling() {
            if (!config.pollUrl || this.poller) return;
            this.poll();
            this.poller = setInterval(() => this.poll(), 5000);
        },

        stopPolling() {
            if (this.poller) clearInterval(this.poller);
            this.poller = null;
            this.polled = false;
        },

        async poll() {
            const url = config.pollUrl + (config.pollUrl.indexOf('?') === -1 ? '?' : '&') + 'tail=200';

            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('poll failed');
                const data = await res.json();
                if (Array.isArray(data.lines)) this.absorb(data.lines);
                delete data.lines;

                // Nothing in an unreachable sample was measured. Taking its
                // state would turn "we could not ask" into "it is off".
                this.unreachable = !!data.unreachable;
                if (this.unreachable) {
                    delete data.state;
                    delete data.cpu;
                    delete data.memory_mib;
                    delete data.disk_mib;
                }

                this.stats = Object.assign({}, this.stats, data);
                this.polled = true;
            } catch (e) {
                this.polled = false;
            }
        },

        /**
         * The poll returns the last N lines every time. Only the part that is
         * not already on screen is appended, so a five second poll does not
         * paste the whole tail again on every tick.
         */
        absorb(incoming) {
            if (!incoming.length) return;

            let overlap = Math.min(this.lines.length, incoming.length);
            while (overlap > 0) {
                let same = true;
                for (let i = 0; i < overlap; i++) {
                    if (this.lines[this.lines.length - overlap + i] !== incoming[i]) { same = false; break; }
                }
                if (same) break;
                overlap--;
            }

            const fresh = incoming.slice(overlap);
            if (!fresh.length) return;

            fresh.forEach((line) => this.lines.push(line));
            this.$nextTick(() => this.scroll());
        },

        /** How the feed is arriving, in the words shown next to the dot. */
        feedLabel() {
            if (this.connected) return 'Live';
            if (this.unreachable) return 'Node Unreachable';
            if (this.polled) return 'Polling';
            return 'Reconnecting';
        },

        /* -------------------------------------------------------- live state
         * Mirrors Server::statusLabel() and Server::statusTone() so a header
         * rendered by PHP and updated by JS never disagrees about the words.
         */
        stateLabel() {
            if (this.status) {
                return {
                    installing: 'Installing',
                    install_failed: 'Install Failed',
                    suspended: 'Suspended',
                    restoring: 'Restoring',
                    transferring: 'Transferring',
                }[this.status] || 'Offline';
            }
            return {
                running: 'Running',
                starting: 'Starting',
                stopping: 'Stopping',
            }[this.stats.state] || 'Offline';
        },

        stateTone() {
            const label = this.stateLabel();
            if (label === 'Running') return 'emerald';
            if (['Starting', 'Stopping', 'Installing', 'Restoring', 'Transferring'].indexOf(label) !== -1) return 'amber';
            if (['Install Failed', 'Suspended'].indexOf(label) !== -1) return 'rose';
            return 'slate';
        },

        /** Can a power action be sent right now? Lifecycle status wins. */
        controllable() {
            return !this.status;
        },

        clear() {
            this.lines = [];
        },

        scroll() {
            if (!this.autoScroll) return;
            const el = this.$refs.output;
            if (el) el.scrollTop = el.scrollHeight;
        },

        onScroll() {
            const el = this.$refs.output;
            if (!el) return;
            // Once the user scrolls up they are reading something; do not yank
            // them back to the bottom on the next line.
            this.autoScroll = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
        },

        recall(direction) {
            if (!this.history.length) return;
            this.historyIndex = Math.min(
                this.history.length - 1,
                Math.max(-1, this.historyIndex + direction)
            );
            this.command = this.historyIndex < 0 ? '' : this.history[this.historyIndex];
        },

        remember() {
            if (this.command.trim()) {
                this.history.unshift(this.command);
                this.history = this.history.slice(0, 50);
            }
            this.historyIndex = -1;
        },

        memoryPercent() {
            const cap = this.stats.memory_cap_mib || config.memory || 1;
            return Math.min(100, Math.round((this.stats.memory_mib / cap) * 100));
        },

        diskPercent() {
            const cap = config.disk || 1;
            // Disk is sampled far less often than cpu and memory, so a frame
            // without it falls back to the figure the page was rendered with
            // rather than dropping the bar to zero under a non-zero number.
            const used = this.stats.disk_mib || config.diskUsed || 0;
            return Math.min(100, Math.round((used / cap) * 100));
        },

        cpuPercent() {
            const cap = config.cpuLimit || 100;
            return Math.min(100, Math.round((this.stats.cpu / cap) * 100));
        },

        // Always GiB, so a used-of-capacity pair never mixes units. Showing
        // "4,958 / 10,240 MiB" made a reader do the division themselves, and
        // showing "4.8 GiB / 10,240 MiB" would be worse. Mirrors
        // App\Support\Format::mibPair, which drops the decimal past 10 where
        // it is noise.
        formatMib(mib) {
            const gib = (mib || 0) / 1024;

            return (gib >= 10 ? Math.round(gib).toLocaleString() : gib.toFixed(1)) + ' GiB';
        },
    }));

    /* ---------------------------------------------------------------- charts
     * A small canvas line chart. No chart library: the fleet builds nothing, so
     * a dependency would have to come off a CDN, and this is 60 lines.
     */
    Alpine.data('metricChart', (config) => ({
        loading: true,
        error: null,
        data: null,
        metric: config.metric || 'cpu',

        async init() {
            await this.load();
            this.$watch('metric', () => this.draw());
            window.addEventListener('resize', () => this.draw());
        },

        async load() {
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch(config.url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Request failed');
                this.data = await res.json();
                this.$nextTick(() => this.draw());
            } catch (e) {
                this.error = 'Could not load the history for this range.';
            } finally {
                this.loading = false;
            }
        },

        draw() {
            const canvas = this.$refs.canvas;
            if (!canvas || !this.data) return;

            const series = this.data[this.metric] || [];
            const dpr = window.devicePixelRatio || 1;
            const w = canvas.clientWidth;
            const h = canvas.clientHeight;
            canvas.width = w * dpr;
            canvas.height = h * dpr;

            const ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);

            if (!series.length) return;

            const pad = { top: 12, right: 12, bottom: 22, left: 44 };
            const plotW = w - pad.left - pad.right;
            const plotH = h - pad.top - pad.bottom;

            let max = Math.max(...series, 1);
            // Round the ceiling up so the axis labels are readable numbers
            // rather than 43.7183.
            const mag = Math.pow(10, Math.floor(Math.log10(max)));
            max = Math.ceil(max / mag) * mag;

            // Gridlines and y labels.
            ctx.strokeStyle = '#e2e8f0';
            ctx.fillStyle = '#94a3b8';
            ctx.font = '11px ui-sans-serif, system-ui, sans-serif';
            ctx.textAlign = 'right';
            ctx.lineWidth = 1;
            for (let i = 0; i <= 4; i++) {
                const y = pad.top + (plotH / 4) * i;
                ctx.beginPath();
                ctx.moveTo(pad.left, y + 0.5);
                ctx.lineTo(w - pad.right, y + 0.5);
                ctx.stroke();
                ctx.fillText(this.axisLabel(max - (max / 4) * i), pad.left - 8, y + 4);
            }

            const stepX = series.length > 1 ? plotW / (series.length - 1) : 0;
            const pointY = (v) => pad.top + plotH - (v / max) * plotH;

            // Filled area, then the line on top.
            const gradient = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
            gradient.addColorStop(0, 'rgba(109, 40, 217, 0.22)');
            gradient.addColorStop(1, 'rgba(109, 40, 217, 0.02)');

            ctx.beginPath();
            ctx.moveTo(pad.left, pad.top + plotH);
            series.forEach((v, i) => ctx.lineTo(pad.left + stepX * i, pointY(v)));
            ctx.lineTo(pad.left + stepX * (series.length - 1), pad.top + plotH);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();

            ctx.beginPath();
            series.forEach((v, i) => {
                const x = pad.left + stepX * i;
                const y = pointY(v);
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            });
            ctx.strokeStyle = '#6d28d9';
            ctx.lineWidth = 2;
            ctx.lineJoin = 'round';
            ctx.stroke();

            // First and last x labels only. More than that is unreadable at
            // this size and adds nothing.
            const labels = this.data.labels || [];
            if (labels.length) {
                ctx.fillStyle = '#94a3b8';
                ctx.textAlign = 'left';
                ctx.fillText(this.shortTime(labels[0]), pad.left, h - 6);
                ctx.textAlign = 'right';
                ctx.fillText(this.shortTime(labels[labels.length - 1]), w - pad.right, h - 6);
            }
        },

        axisLabel(v) {
            if (this.metric === 'memory' || this.metric === 'disk') {
                return v >= 1024 ? (v / 1024).toFixed(0) + 'G' : Math.round(v) + 'M';
            }
            return String(Math.round(v * 10) / 10);
        },

        shortTime(stamp) {
            return String(stamp).slice(5, 16).replace('T', ' ');
        },
    }));


    /* ----------------------------------------------------------------- files
     * Multi-select for the file table. Shift-click ranges, because deleting
     * forty files one checkbox at a time is nobody's idea of a file manager.
     */
    /* -------------------------------------------------------- file browser
     * Selection (including shift-click ranges and the select-all switch) plus
     * uploads. Both live here because the drop target is the same element the
     * table sits in.
     *
     * config comes from Blade: { path, uploadUrl, maxBytes, csrf }. uploadUrl
     * is null when this user has no file.create permission, and every upload
     * entry point checks it, so the UI cannot start something the controller
     * will only 403.
     */
    Alpine.data('fileBrowser', (config = {}) => ({
        selected: [],
        lastIndex: null,

        config,
        uploads: [],
        /* Depth, not a boolean. dragleave fires every time the pointer crosses
         * into a child element, so a single flag flickers off the moment the
         * cursor moves over a table row and the drop zone disappears under the
         * file being dragged. Counting enters against leaves does not. */
        dragDepth: 0,
        sending: false,
        nextId: 1,

        toggle(path, index, shiftKey) {
            if (shiftKey && this.lastIndex !== null) {
                const rows = Array.from(this.$root.querySelectorAll('[data-file-path]'));
                const [from, to] = [this.lastIndex, index].sort((a, b) => a - b);
                for (let i = from; i <= to; i++) {
                    const p = rows[i]?.dataset.filePath;
                    if (p && !this.selected.includes(p)) this.selected.push(p);
                }
            } else if (this.selected.includes(path)) {
                this.selected = this.selected.filter((p) => p !== path);
            } else {
                this.selected.push(path);
            }
            this.lastIndex = index;
        },

        isSelected(path) {
            return this.selected.includes(path);
        },

        /** Every path in the table, in the order it is rendered. */
        allPaths() {
            return Array.from(this.$root.querySelectorAll('[data-file-path]'))
                .map((row) => row.dataset.filePath)
                .filter(Boolean);
        },

        allSelected() {
            const all = this.allPaths();

            return all.length > 0 && all.every((p) => this.selected.includes(p));
        },

        /**
         * Select or clear the whole page. Deliberately replaces the selection
         * rather than merging: a header switch that only ever adds leaves the
         * operator unable to undo it with the same control.
         */
        toggleAll(on) {
            this.selected = on ? this.allPaths() : [];
            this.lastIndex = null;
        },

        clear() {
            this.selected = [];
            this.lastIndex = null;
        },

        /* ------------------------------------------------------- uploading */

        dragIn() {
            if (this.config.uploadUrl) this.dragDepth++;
        },

        dragOut() {
            if (this.dragDepth > 0) this.dragDepth--;
        },

        dropped(event) {
            this.dragDepth = 0;
            const files = event.dataTransfer?.files;
            if (files?.length) this.queue(files);
        },

        picked(event) {
            this.queue(event.target.files);
            /* Cleared so picking the same file again still fires change.
             * Without this, a failed upload cannot be retried from the button. */
            event.target.value = '';
        },

        queue(files) {
            if (!this.config.uploadUrl) return;

            for (const file of Array.from(files)) {
                const item = {
                    id: this.nextId++,
                    file,
                    name: file.name,
                    percent: 0,
                    state: 'waiting',
                    detail: 'Waiting',
                    error: '',
                };

                /* Refused here rather than after a progress bar has crawled to
                 * the end. The panel and the node both check again; this one
                 * only exists to save somebody's time and bandwidth. */
                if (this.config.maxBytes > 0 && file.size > this.config.maxBytes) {
                    item.state = 'failed';
                    item.percent = 100;
                    item.detail = 'Too large';
                    item.error = `${this.bytes(file.size)} is over the ${this.bytes(this.config.maxBytes)} limit for this node.`;
                }

                this.uploads.push(item);
            }

            this.drain();
        },

        /* One at a time. A dropped folder of forty files opening forty sockets
         * gets every one of them throttled and makes each individual progress
         * bar meaningless. */
        async drain() {
            if (this.sending) return;
            this.sending = true;

            let sent = 0;
            let failed = 0;
            for (const item of this.uploads) {
                if (item.state !== 'waiting') {
                    if (item.state === 'failed') failed++;
                    continue;
                }
                const ok = await this.send(item);
                ok ? sent++ : failed++;
            }
            this.sending = false;

            /* The table is rendered server side, so the only honest way to show
             * what landed is to ask for it again. Not on a partial failure:
             * reloading would take the error message with it. */
            if (sent > 0 && failed === 0) {
                setTimeout(() => window.location.reload(), 700);
            }
        },

        send(item) {
            return new Promise((resolve) => {
                const body = new FormData();
                body.append('_token', this.config.csrf);
                body.append('path', this.config.path);
                body.append('file', item.file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.config.uploadUrl);
                xhr.setRequestHeader('Accept', 'application/json');

                /* fetch() has no upload progress at all, which is the entire
                 * reason this is an XMLHttpRequest in 2026. */
                xhr.upload.addEventListener('progress', (e) => {
                    item.state = 'sending';
                    if (e.lengthComputable) {
                        item.percent = Math.round((e.loaded / e.total) * 100);
                        item.detail = `${this.bytes(e.loaded)} of ${this.bytes(e.total)}`;
                    } else {
                        item.detail = 'Sending';
                    }
                });

                const fail = (message) => {
                    item.state = 'failed';
                    item.percent = 100;
                    item.detail = 'Failed';
                    item.error = message;
                    resolve(false);
                };

                xhr.addEventListener('load', () => {
                    let payload = {};
                    try {
                        payload = JSON.parse(xhr.responseText || '{}');
                    } catch (e) {
                        /* An HTML body here is a PHP error page or a login
                         * redirect, neither of which is worth showing raw. */
                        return fail(`The panel returned an unexpected response (HTTP ${xhr.status}).`);
                    }
                    if (xhr.status >= 200 && xhr.status < 300 && payload.ok) {
                        item.state = 'done';
                        item.percent = 100;
                        item.detail = this.bytes(payload.bytes ?? item.file.size);
                        return resolve(true);
                    }
                    /* Laravel's validation shape, then ours, then a fallback. */
                    const validation = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                    fail(payload.error || validation || payload.message || `The upload was refused (HTTP ${xhr.status}).`);
                });

                xhr.addEventListener('error', () => fail('The connection dropped during the upload.'));
                xhr.addEventListener('abort', () => fail('The upload was cancelled.'));

                item.state = 'sending';
                item.detail = 'Sending';
                xhr.send(body);
            });
        },

        /* True once nothing is in flight, which is when a Clear button is safe
         * to offer. */
        idle() {
            return !this.sending && this.uploads.every((u) => u.state === 'done' || u.state === 'failed');
        },

        bytes(n) {
            n = Number(n) || 0;
            const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
            let power = 0;
            while (n >= 1024 && power < units.length - 1) {
                n /= 1024;
                power++;
            }

            return (power === 0 ? n : Math.round(n * 10) / 10) + ' ' + units[power];
        },
    }));

    /* --------------------------------------------------------- server wizard
     * The admin "New Server" screen. Creating a server is a handful of short
     * decisions, so it is presented as six steps rather than one page of forty
     * fields. There is still exactly ONE form and ONE POST: the steps are shown
     * and hidden here, every field stays in the DOM, and the controller sees the
     * same payload it always did.
     *
     * Markup lives in Blade, including every card, every node and every
     * template variable input. This file owns state and arithmetic only: which
     * step is open, what has been chosen, and whether the chosen size will
     * actually fit on a machine. Answering "will it fit" here, live, is the
     * point: nobody should press Create Server to find out.
     *
     * The server remains the authority on what is valid. Everything checked
     * here is a courtesy; when the POST does come back with errors the
     * controller tells us which step to open on.
     *
     * Reads its data from a <script type="application/json"> island rather than
     * a giant x-data attribute, which keeps the Blade file readable and the
     * escaping simple.
     */
    Alpine.data('serverWizard', (dataId) => ({
        data: {
            users: [], templates: [], blueprints: [], nodes: [], locations: [],
            selected: {}, values: {}, step: 1,
        },

        // Steps, validation and the rail come from the shared wizard.
        ...wizardCore({ total: 6 }),

        /* The summary is only meaningful once every earlier step has been
         * answered, so it is built on arriving at the last one rather than kept
         * continuously up to date. */
        onStep(n) {
            if (n === this.total) this.buildSummary();
        },

        // step one: what to run
        templateId: '',
        blueprintId: '',
        query: '',
        showAdvanced: false,
        image: '',
        startup: '',

        // step two: where it runs
        placement: 'auto',
        locationId: '',
        nodeId: '',
        allocationId: '',

        // step three: who it belongs to
        name: '',
        description: '',
        ownerId: '',

        // step four: how big it is
        res: {
            memory: 2048, disk: 10240, cpu: 200, swap: 0, io: 500,
            database_limit: 2, allocation_limit: 3, backup_limit: 5,
        },
        showFineTuning: false,

        // step five: the template's own locked settings
        showLocked: false,

        // step six: the flattened summary, rebuilt on arrival
        sum: {},

        /* Sizes people actually pick. A linear slider from 512 MiB to 64 GiB
         * spends most of its travel on values nobody wants; these stops put the
         * common sizes an even distance apart. The number box beside each
         * slider still accepts anything the server will. */
        memStops: [512, 1024, 1536, 2048, 3072, 4096, 6144, 8192, 10240, 12288, 16384, 20480, 24576, 32768, 40960, 49152, 65536],
        diskStops: [2048, 5120, 10240, 20480, 30720, 40960, 51200, 81920, 102400, 153600, 204800, 307200, 409600],
        cpuStops: [25, 50, 100, 150, 200, 300, 400, 600, 800, 1000, 1200, 1600, 2400, 3200],

        init() {
            const island = document.getElementById(dataId);
            if (island) {
                try { this.data = JSON.parse(island.textContent); } catch (e) { /* keep the empty shape */ }
            }

            const chosen = this.data.selected || {};
            const values = this.data.values || {};

            // Counted rather than declared: the step labels live in Blade, and
            // two lists of six that must agree is one list too many.
            this.total = document.querySelectorAll('[data-step]').length || 6;
            this.step = this.data.step || 1;
            this.furthest = this.step;

            this.templateId = String(chosen.template_id || (this.data.templates[0] || {}).id || '');
            this.nodeId = chosen.node_id ? String(chosen.node_id) : '';
            this.allocationId = chosen.allocation_id ? String(chosen.allocation_id) : '';
            this.locationId = chosen.location_id ? String(chosen.location_id) : '';
            this.ownerId = chosen.owner_id ? String(chosen.owner_id) : '';
            // A node in the posted data means the operator placed it by hand.
            this.placement = this.nodeId ? 'manual' : 'auto';

            this.name = values.name || '';
            this.description = values.description || '';
            this.image = values.image || '';
            this.startup = values.startup || '';

            Object.keys(this.res).forEach((key) => {
                if (values[key] !== undefined && values[key] !== null) this.res[key] = Number(values[key]);
            });

            // A rejected POST must not hide the message it came back with, so a
            // disclosure whose contents failed reopens with them.
            const failed = this.data.errors || [];
            this.showAdvanced = !!(this.image || this.startup)
                || failed.indexOf('image') > -1 || failed.indexOf('startup') > -1;
            this.showFineTuning = this.res.swap !== 0 || this.res.io !== 500
                || failed.indexOf('swap') > -1 || failed.indexOf('io') > -1;
            this.showLocked = !!this.data.open_locked;

            // The port options are built by x-for, which runs after x-model has
            // already tried to apply its value, so the select would otherwise
            // come back empty when a rejected POST is redisplayed.
            this.$nextTick(() => {
                if (this.allocationId) this.set('allocation_id', this.allocationId);
            });

            // Watchers are registered after seeding on purpose: reopening on a
            // failed step must not wipe what the operator already chose.
            this.$watch('templateId', () => this.onTemplateChange());
            this.$watch('nodeId', () => { this.allocationId = ''; });
            this.$watch('placement', (mode) => {
                // Leaving a stale node_id behind would silently override the
                // auto placement the operator just asked for.
                if (mode === 'auto') {
                    this.nodeId = '';
                    this.allocationId = '';
                } else {
                    this.locationId = '';
                }
            });

            if (this.step === this.total) this.buildSummary();
        },

        // ------------------------------------------------------------ lookups

        get template() {
            return this.data.templates.find((t) => String(t.id) === String(this.templateId)) || null;
        },

        /** Can a node with these runtimes run the chosen template? */
        canRun(runtimes) {
            const t = this.template;

            return !t || (runtimes || []).indexOf(t.runtime) > -1;
        },

        /** Only nodes that can actually run this template's runtime. */
        get nodeChoices() {
            return this.data.nodes.filter((n) => this.canRun(n.runtimes));
        },

        get hiddenNodeCount() {
            return this.data.nodes.length - this.nodeChoices.length;
        },

        get node() {
            return this.nodeById(this.nodeId);
        },

        /** Blade renders one card per node, so the card looks its own data up. */
        nodeById(id) {
            return this.data.nodes.find((n) => String(n.id) === String(id)) || null;
        },

        get allocationChoices() {
            return this.node ? (this.node.allocations || []) : [];
        },

        get variables() {
            return this.template ? (this.template.variables || []) : [];
        },

        get owner() {
            return this.data.users.find((u) => String(u.id) === String(this.ownerId)) || null;
        },

        get location() {
            return this.data.locations.find((l) => String(l.id) === String(this.locationId)) || null;
        },

        // ------------------------------------------------------- game picking

        templateHay(t) {
            return [t.game, t.name, t.runtime_label, t.description].join(' ').toLowerCase();
        },

        matchesTemplate(id) {
            const q = this.query.trim().toLowerCase();
            if (!q) return true;
            const t = this.data.templates.find((x) => String(x.id) === String(id));

            return !!t && this.templateHay(t).indexOf(q) > -1;
        },

        /** Hide a game's heading once every template under it is filtered out. */
        gameHasMatch(ids) {
            return (ids || []).some((id) => this.matchesTemplate(id));
        },

        get matchCount() {
            return this.data.templates.filter((t) => this.matchesTemplate(t.id)).length;
        },

        // --------------------------------------------------------- blueprints

        /** Saved sizes for the chosen template, smallest first. */
        get blueprintChoices() {
            return this.data.blueprints
                .filter((b) => String(b.template_id) === String(this.templateId))
                .slice()
                .sort((a, b) => Number((a.limits || {}).memory || 0) - Number((b.limits || {}).memory || 0));
        },

        blueprintById(id) {
            return this.data.blueprints.find((b) => String(b.id) === String(id)) || null;
        },

        blueprintNodeCount(id) {
            const b = this.blueprintById(id);
            if (!b) return 0;
            const limits = b.limits || {};

            return this.nodesThatFit(Number(limits.memory || 0), Number(limits.disk || 0)).length;
        },

        /** Blade renders one card per blueprint, so the card asks about itself. */
        blueprintFitLabel(id) {
            const count = this.blueprintNodeCount(id);
            if (count === 0) return 'No machine has room for this';

            return 'Fits on ' + count + ' of ' + this.nodeChoices.length + ' machines';
        },

        /**
         * The smallest saved size that will actually fit somewhere. Cheapest
         * thing that works beats biggest thing available: over-provisioning is
         * how a host runs out of node before it runs out of customers.
         */
        get recommendedBlueprintId() {
            const fits = this.blueprintChoices.filter((b) => this.blueprintNodeCount(b.id) > 0);

            return fits.length ? String(fits[0].id) : '';
        },

        applyBlueprint(id) {
            const b = this.data.blueprints.find((x) => String(x.id) === String(id));
            if (!b) return;

            this.blueprintId = String(b.id);
            if (b.template_id) this.templateId = String(b.template_id);

            const limits = b.limits || {};
            const features = b.features || {};
            [['memory', limits.memory], ['disk', limits.disk], ['cpu', limits.cpu],
                ['swap', limits.swap], ['io', limits.io],
                ['database_limit', features.databases], ['allocation_limit', features.allocations],
                ['backup_limit', features.backups]].forEach(([key, value]) => {
                if (value !== undefined && value !== null) this.res[key] = Number(value);
            });

            // The template swap reveals a different block of variable inputs, so
            // blueprint environment values are applied once that has settled.
            this.$nextTick(() => {
                const env = b.environment || {};
                Object.keys(env).forEach((key) => this.setVariable(this.templateId, key, String(env[key])));
            });
        },

        clearBlueprint() {
            this.blueprintId = '';
        },

        // ----------------------------------------------------------- capacity

        pct(used, capacity) {
            if (!capacity || capacity <= 0) return 0;

            return Math.max(0, Math.min(100, Math.round((used / capacity) * 1000) / 10));
        },

        /** Would a server of this size fit on this node right now? */
        fitsOn(n, memory, disk) {
            if (n.maintenance) return false;

            return (n.memory_used + memory) <= n.memory_capacity
                && (n.disk_used + disk) <= n.disk_capacity;
        },

        /** Mirrors the controller's auto placement: public, awake, right runtime, has room. */
        nodesThatFit(memory, disk) {
            return this.nodeChoices.filter((n) => n.public
                && this.fitsOn(n, memory, disk)
                && (!this.locationId || String(n.location_id) === String(this.locationId)));
        },

        get autoCandidates() {
            return this.nodesThatFit(this.res.memory, this.res.disk);
        },

        /** The node auto placement would land on: emptiest one with room. */
        get autoPick() {
            const candidates = this.autoCandidates.slice()
                .sort((a, b) => this.pct(a.memory_used, a.memory_capacity) - this.pct(b.memory_used, b.memory_capacity));

            return candidates.length ? candidates[0] : null;
        },

        /** ok, maintenance, runtime, memory or disk: why this node will not do. */
        verdict(n) {
            if (n.maintenance) return 'maintenance';
            if (!this.canRun(n.runtimes)) return 'runtime';
            if (n.memory_used + this.res.memory > n.memory_capacity) return 'memory';
            if (n.disk_used + this.res.disk > n.disk_capacity) return 'disk';

            return 'ok';
        },

        verdictLabel(n) {
            return {
                ok: 'Room For It',
                maintenance: 'In Maintenance',
                runtime: 'Wrong Runtime',
                memory: 'Not Enough Memory',
                disk: 'Not Enough Disk',
            }[this.verdict(n)] || '';
        },

        fits(n) {
            return this.verdict(n) === 'ok';
        },

        /** Percent of a node already promised to other servers. */
        usedPct(n, kind) {
            return kind === 'disk'
                ? this.pct(n.disk_used, n.disk_capacity)
                : this.pct(n.memory_used, n.memory_capacity);
        },

        /** Percent this server would add on top, clipped at what is left. */
        askPct(n, kind) {
            const used = kind === 'disk' ? n.disk_used : n.memory_used;
            const capacity = kind === 'disk' ? n.disk_capacity : n.memory_capacity;
            const ask = kind === 'disk' ? this.res.disk : this.res.memory;

            return this.pct(Math.max(0, Math.min(capacity - used, ask)), capacity);
        },

        /** How much of the ask does not fit, as a percent of the whole node. */
        overPct(n, kind) {
            const used = kind === 'disk' ? n.disk_used : n.memory_used;
            const capacity = kind === 'disk' ? n.disk_capacity : n.memory_capacity;
            const ask = kind === 'disk' ? this.res.disk : this.res.memory;

            return this.pct(Math.max(0, used + ask - capacity), capacity);
        },

        /**
         * How much of the machine is left once this server has its share, or
         * how far past the end it goes. Never runs through mib() alone: a
         * negative number there reads as "Unlimited", which is the opposite of
         * what an over-allocation means.
         */
        freeLabel(n, kind) {
            const left = this.freeAfter(n, kind);

            return left < 0
                ? this.mib(Math.abs(left)) + ' too much'
                : this.mib(left) + ' left after';
        },

        freeAfter(n, kind) {
            const used = kind === 'disk' ? n.disk_used : n.memory_used;
            const capacity = kind === 'disk' ? n.disk_capacity : n.memory_capacity;
            const ask = kind === 'disk' ? this.res.disk : this.res.memory;

            return capacity - used - ask;
        },

        // ------------------------------------------------------------ sliders

        /** The nearest stop to a typed value, so the slider never lies about it. */
        stopIndex(stops, value) {
            let best = 0;
            let distance = Infinity;
            stops.forEach((stop, i) => {
                const d = Math.abs(stop - Number(value));
                if (d < distance) { distance = d; best = i; }
            });

            return best;
        },

        setStop(key, stops, index) {
            const i = Math.max(0, Math.min(stops.length - 1, Number(index)));
            this.res[key] = stops[i];
        },

        nudge(key, delta, min, max) {
            const next = Number(this.res[key] || 0) + delta;
            this.res[key] = Math.max(min, Math.min(max, next));
        },

        // ------------------------------------------------------------ fields

        /** The live form control with this name, or null. */
        field(name) {
            const form = this.$refs.form;

            return form ? form.elements[name] || null : null;
        },

        val(name) {
            const el = this.field(name);

            return el ? String(el.value || '') : '';
        },

        set(name, value) {
            const el = this.field(name);
            if (el) el.value = value === undefined || value === null ? '' : value;
        },

        onOff(name) {
            return this.val(name) === '0' || this.val(name) === '' ? 'Off' : 'On';
        },

        /**
         * Template variable inputs are rendered by Blade, one hidden block per
         * template, so their values are read straight off the form. A radio
         * group answers with whichever option is checked.
         */
        variableValue(id) {
            const el = this.field('variables[' + id + ']');

            return el ? String(el.value || '') : '';
        },

        /** Used when a blueprint carries environment values of its own. */
        setVariable(templateId, env, value) {
            const form = this.$refs.form;
            if (!form) return;

            const scope = form.querySelector('[data-vars="' + templateId + '"]');
            if (!scope) return;

            scope.querySelectorAll('[data-env="' + env + '"]').forEach((el) => {
                if (el.type === 'radio') {
                    el.checked = el.value === value;
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    el.value = value;
                    // x-model listens for input, so a silent assignment would
                    // leave the switch showing the old state.
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        },

        /** A password nobody has to invent. alpha_dash safe, so every rule passes. */
        generateSecret(name) {
            const alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            const bytes = new Uint32Array(20);
            (window.crypto || window.msCrypto).getRandomValues(bytes);
            let out = '';
            for (let i = 0; i < bytes.length; i++) out += alphabet[bytes[i] % alphabet.length];

            const el = this.field(name);
            if (el) {
                el.value = out;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.focus();
            }
        },

        // ------------------------------------------------------------ changes

        onTemplateChange() {
            // A node that cannot run the new template must not stay selected.
            if (this.nodeId && !this.nodeChoices.some((n) => String(n.id) === String(this.nodeId))) {
                this.nodeId = '';
            }
            // Nor may a blueprint keep its badge once it no longer applies.
            const b = this.data.blueprints.find((x) => String(x.id) === String(this.blueprintId));
            if (b && String(b.template_id) !== String(this.templateId)) this.blueprintId = '';
        },

        // --------------------------------------------------------- navigation

        /**
         * Native constraint validation, applied only to the controls the
         * operator can actually see. A control inside a hidden branch has no
         * offsetParent and cannot be focused, so reporting on it would throw the
         * browser's un-focusable-control error and show nothing.
         */
        /**
         * Last line of defence. If anything invalid slipped through, open the
         * step that owns it rather than letting the browser refuse to submit
         * with nothing on screen to explain why.
         */
        onSubmit(event) {
            const form = this.$refs.form;
            if (!form || form.checkValidity()) return;

            event.preventDefault();

            // A control inside a closed disclosure cannot be focused, and
            // reportValidity on it shows nothing at all. Open everything first.
            this.showAdvanced = true;
            this.showFineTuning = true;
            this.showLocked = true;

            const bad = form.querySelector(':invalid');
            if (!bad) return;

            const panel = bad.closest('[data-step]');
            if (panel) this.step = parseInt(panel.getAttribute('data-step'), 10) || this.step;
            this.$nextTick(() => { bad.focus(); bad.reportValidity(); });
        },

        // ------------------------------------------------------------ summary

        mib(value) {
            const n = parseInt(value, 10);
            if (isNaN(n)) return '0 MiB';
            if (n < 0) return 'Unlimited';
            if (n === 0) return 'None';

            return n >= 1024 ? (Math.round((n / 1024) * 10) / 10) + ' GiB' : n + ' MiB';
        },

        cores(value) {
            const n = parseInt(value, 10);
            if (isNaN(n) || n <= 0) return 'Unlimited';

            return (Math.round((n / 100) * 100) / 100) + (n === 100 ? ' Core' : ' Cores');
        },

        /** One line per step for the rail, so progress reads as decisions made. */
        stepSummary(n) {
            const t = this.template;

            if (n === 1) return t ? (t.game ? t.game + ' : ' + t.name : t.name) : 'Nothing chosen';
            if (n === 2) {
                if (this.placement === 'manual') return this.node ? this.node.name : 'No machine chosen';

                return this.location ? 'Automatic, ' + this.location.name : 'Automatic';
            }
            if (n === 3) return this.name || 'Unnamed';
            if (n === 4) return this.mib(this.res.memory) + ', ' + this.mib(this.res.disk) + ', ' + this.cores(this.res.cpu);
            if (n === 5) {
                const count = this.variables.length;

                return count ? count + (count === 1 ? ' setting' : ' settings') : 'Nothing to set';
            }

            return '';
        },

        buildSummary() {
            const t = this.template;
            const alloc = this.allocationChoices.find((a) => String(a.id) === String(this.allocationId));
            const manual = this.placement === 'manual';
            const pick = this.autoPick;

            this.sum = {
                game: t ? (t.game || '') : '',
                template: t ? t.name : 'Not chosen',
                runtime: t ? t.runtime_label : '',
                // Only an override is worth printing. A template's own startup
                // script runs to twenty lines, and reprinting it here would bury
                // everything a review is actually for.
                image: this.image || (t && t.default_image ? t.default_image : 'Template default'),
                startup: this.startup || 'Template default',

                placement: manual ? 'Chosen By Hand' : 'Automatic',
                node: manual
                    ? (this.node ? this.node.name : 'Not chosen')
                    : (pick ? pick.name + ' (picked at create time)' : 'No machine has room'),
                nodeLocation: manual
                    ? (this.node && this.node.location ? this.node.location : 'No location')
                    : (this.location ? this.location.name : 'Anywhere'),
                port: manual
                    ? (alloc ? alloc.label : 'First free port on the machine')
                    : 'First free port on the machine',
                fits: manual ? (this.node ? this.fits(this.node) : true) : !!pick,

                name: this.name,
                description: this.description || 'None',
                owner: this.owner ? this.owner.name : '',
                ownerEmail: this.owner ? this.owner.email : '',

                memory: this.mib(this.res.memory),
                disk: this.mib(this.res.disk),
                cpu: this.cores(this.res.cpu) + ' (' + this.res.cpu + '%)',
                swap: this.mib(this.res.swap),
                io: String(this.res.io),
                databases: String(this.res.database_limit),
                ports: String(this.res.allocation_limit),
                backups: String(this.res.backup_limit),
                autoRestart: this.onOff('auto_restart'),
                autoUpdate: this.onOff('auto_update'),

                variables: this.variables.map((v) => ({
                    name: v.name,
                    env: v.env,
                    value: this.variableValue(v.id) === '' ? 'Empty' : this.variableValue(v.id),
                })),
            };
        },
    }));

    /* ------------------------------------------------------------- node form
     * Adding a node is two acts. Act one is this form, which describes a
     * machine. Act two happens on the machine itself, where one command turns
     * a row in the database into something that actually answers. The wizard
     * carries every field so the last step can hand over to act two knowing
     * what it just created, rather than the panel silently redirecting.
     *
     * It also holds the capacity numbers, which is the whole reason for
     * holding them: "65536" and "20" mean nothing, "64 GiB on the machine,
     * promised out as 76.8 GiB" is the sentence an operator needs to read
     * before pressing save.
     *
     * Every value is seeded from PHP (old() first, then the model), because
     * x-model writes state into the field on init: an unseeded binding would
     * quietly blank a field the moment somebody opened an existing node.
     */
    Alpine.data('nodeWizard', (seed) => ({
        // Steps, validation and the rail come from the shared wizard; what
        // follows is only what a node is.
        ...wizardCore({ total: 6, step: seed.step, editing: seed.editing }),

        locations: seed.locations,

        name: seed.name,
        description: seed.description,
        locationId: seed.locationId,

        mode: seed.mode,
        scheme: seed.scheme,
        fqdn: seed.fqdn,
        daemonPort: seed.daemonPort,
        sftpPort: seed.sftpPort,
        behindProxy: seed.behindProxy,

        runtimes: seed.runtimes,
        runtimeNames: seed.runtimeNames,

        memory: seed.memory,
        memoryOver: seed.memoryOver,
        disk: seed.disk,
        diskOver: seed.diskOver,
        cpu: seed.cpu,
        cpuOver: seed.cpuOver,
        uploadSize: seed.uploadSize,

        isPublic: seed.isPublic,
        maintenance: seed.maintenance,
        daemonBase: seed.daemonBase,

        // -------------------------------------------------------- connection

        daemonUrl() {
            if (this.mode === 'reverse') return 'Outbound only, no address needed';
            return this.scheme + '://' + (this.fqdn || 'hostname.not.set') + ':' + (this.daemonPort || '?');
        },

        // ---------------------------------------------------------- runtimes

        runtimeList() {
            return Object.keys(this.runtimes).filter((k) => this.runtimes[k]);
        },

        runtimeSummary() {
            const on = this.runtimeList().map((k) => this.runtimeNames[k] || k);
            if (on.length === 0) return 'Nothing. This node could not run a single game.';
            if (on.length === 1) return on[0] + ' only.';
            return on.slice(0, -1).join(', ') + ' and ' + on[on.length - 1] + '.';
        },

        // ---------------------------------------------------------- capacity
        /* kind is 'mib' for memory and disk, 'cpu' for the core percentage. */

        num(v) {
            const n = Number(v);
            return Number.isFinite(n) && n > 0 ? n : 0;
        },

        tidy(v) {
            return (Math.round(v * 10) / 10).toLocaleString('en-US');
        },

        fmt(kind, v) {
            const n = this.num(v);
            if (kind === 'cpu') {
                const cores = n / 100;
                return this.tidy(cores) + ' ' + (cores === 1 ? 'core' : 'cores');
            }
            if (n >= 1048576) return this.tidy(n / 1048576) + ' TiB';
            if (n >= 1024) return this.tidy(n / 1024) + ' GiB';
            return this.tidy(n) + ' MiB';
        },

        promised(base, pct) {
            return this.num(base) * (1 + this.num(pct) / 100);
        },

        /* Two segments in one track: what the machine has, then what it has
         * been told to pretend it has. Widths are arithmetic, so they are a
         * bound style rather than a utility class, the same way x-meter does
         * it. Colour never comes from here. */
        barBase(pct) {
            return { width: (100 / (100 + this.num(pct)) * 100) + '%' };
        },

        barOver(pct) {
            return { width: (this.num(pct) / (100 + this.num(pct)) * 100) + '%' };
        },

        overTone(pct) {
            const n = this.num(pct);
            if (n === 0) return 'safe';
            return n >= 100 ? 'risk' : 'watch';
        },

        overChip(pct) {
            return {
                safe: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                watch: 'bg-amber-50 text-amber-700 ring-amber-200',
                risk: 'bg-rose-50 text-rose-700 ring-rose-200',
            }[this.overTone(pct)];
        },

        overBar(pct) {
            return this.overTone(pct) === 'risk' ? 'bg-rose-500' : 'bg-amber-400';
        },

        capacityHeadline(kind, base, pct) {
            return this.fmt(kind, this.promised(base, pct));
        },

        capacityLine(kind, base, pct) {
            const over = this.num(pct);
            if (this.num(base) === 0) return 'Nothing set yet, so nothing can be placed here.';

            const physical = this.fmt(kind, base);
            const unit = kind === 'cpu' ? 'cycle' : 'byte';
            if (over === 0) return physical + ' on the machine, and not a ' + unit + ' more promised than that.';

            const extra = this.fmt(kind, this.promised(base, pct) - this.num(base));
            const tail = over >= 100
                ? ' Every server here would have to sit idle at once for that to hold.'
                : '';
            return physical + ' on the machine, promised out as ' + this.fmt(kind, this.promised(base, pct))
                + '. That is ' + extra + ' it does not have.' + tail;
        },

        // --------------------------------------------------------- placement

        volumePath() {
            return (this.daemonBase || '/var/lib/gamemgr/volumes').replace(/\/+$/, '') + '/<server-uuid>';
        },

        // ------------------------------------------------------------ review

        locationLabel() {
            const hit = this.locations.find((l) => String(l.id) === String(this.locationId));
            return hit ? hit.label : 'Not set';
        },

        placementSummary() {
            const bits = [this.isPublic ? 'Open to automatic placement' : 'Hand picked only'];
            if (this.maintenance) bits.push('in maintenance mode');
            return bits.join(', ') + '.';
        },
    }));
});

/* Copy each column header onto its cells as data-label, so the stacked mobile
 * layout can show "Owner: Dana" without every view having to repeat the label
 * in its markup. Runs outside alpine:init because it touches no Alpine state
 * and should not wait on it.
 *
 * The selection and action columns are left unlabelled: a switch and a row of
 * icon buttons say what they are already.
 */
(function labelTableCells() {
    function apply(root) {
        (root || document).querySelectorAll('.vx-table').forEach(function (table) {
            var headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
                if (th.classList.contains('w-10') || th.classList.contains('text-right')) return '';
                return th.textContent.trim();
            });
            if (! headers.length) return;
            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.from(row.children).forEach(function (cell, i) {
                    if (cell.hasAttribute('data-label')) return;
                    cell.setAttribute('data-label', headers[i] || '');
                });
            });
        });
    }

    if (document.readyState !== 'loading') apply();
    else document.addEventListener('DOMContentLoaded', function () { apply(); });
})();

/* Registered from a second alpine:init listener rather than inside the block
 * above. Alpine fires the event once and every listener runs, so appending
 * here works exactly as well as extending that block, and this file can grow
 * from the bottom without anyone reformatting what is already in it.
 */
document.addEventListener('alpine:init', () => {

    /* ------------------------------------------------- blueprint designer
     * A blueprint is a product decision wearing a form's clothes: "the
     * Palworld Test size, 8 GiB, enough to prove an install and connect a few
     * players". Eight number boxes cannot say that, so this component does
     * three things the boxes could not:
     *
     *   - reads every raw MiB back as GiB and every CPU percent as cores,
     *   - draws the card the operator will later pick, live, from the same
     *     values that are about to be posted,
     *   - ranks the draft against the other blueprints on the same template,
     *     because the real question is never "how many MiB", it is "is this
     *     our small one or our big one".
     *
     * The inputs keep their names, so the controller sees the payload it has
     * always seen whether this ever runs or not.
     */
    Alpine.data('blueprintDesigner', (dataId) => ({
        data: { templates: [], siblings: [], values: {} },

        name: '',
        description: '',
        templateId: '',
        res: {
            memory: 2048, disk: 10240, cpu: 200, swap: 0, io: 500,
            databases: 1, allocations: 2, backups: 5,
        },

        // Two of swap's three useful values are magic numbers, so it is chosen
        // by intent here and only ever typed when the intent is "a fixed amount".
        swapMode: 'off',
        showFineTuning: false,

        /* Sizes people actually pick. A linear slider from 512 MiB to 64 GiB
         * spends most of its travel on values nobody wants; these stops put the
         * common sizes an even distance apart, and the number box beside each
         * slider still accepts anything the server will. */
        memStops: [0, 512, 1024, 1536, 2048, 3072, 4096, 6144, 8192, 10240, 12288, 16384, 20480, 24576, 32768, 40960, 49152, 65536],
        diskStops: [0, 2048, 5120, 10240, 20480, 30720, 40960, 51200, 81920, 102400, 153600, 204800, 307200, 409600],
        cpuStops: [0, 25, 50, 100, 150, 200, 300, 400, 600, 800, 1000, 1200, 1600, 2400, 3200],

        memPresets: [2048, 4096, 8192, 16384, 32768],
        diskPresets: [10240, 25600, 51200, 102400],
        cpuPresets: [100, 200, 400, 800],
        ioPresets: [
            { value: 250, label: 'Yields' },
            { value: 500, label: 'Normal' },
            { value: 750, label: 'Wins' },
        ],

        init() {
            const island = document.getElementById(dataId);
            if (island) {
                try { this.data = JSON.parse(island.textContent); } catch (e) { /* keep the empty shape */ }
            }

            const values = this.data.values || {};
            this.name = values.name || '';
            this.description = values.description || '';
            this.templateId = String(values.template_id || (this.data.templates[0] || {}).id || '');

            Object.keys(this.res).forEach((key) => {
                if (values[key] !== undefined && values[key] !== null) this.res[key] = Number(values[key]);
            });

            this.swapMode = this.res.swap < 0 ? 'unlimited' : (this.res.swap === 0 ? 'off' : 'custom');
            // Opened only when there is something in it worth seeing, and always
            // when it is hiding a rejected field: an error nobody can see is an
            // error nobody can fix.
            const tuning = document.getElementById('bp-fine-tuning');
            this.showFineTuning = this.res.swap !== 0 || this.res.io !== 500
                || !!(tuning && tuning.querySelector('.text-rose-600'));
        },

        // ----------------------------------------------------------- lookups

        get template() {
            return this.data.templates.find((t) => String(t.id) === String(this.templateId)) || null;
        },

        get templateLabel() {
            const t = this.template;
            if (! t) return 'No Template';

            return t.game ? t.game + ' : ' + t.name : t.name;
        },

        /** Other blueprints built on the same template. */
        get siblings() {
            return (this.data.siblings || []).filter((b) => String(b.template_id) === String(this.templateId));
        },

        get siblingLabel() {
            const n = this.siblings.length;
            if (n === 0) return 'First Size For This Template';

            return n === 1 ? '1 Other Size' : n + ' Other Sizes';
        },

        /** The draft slotted in among its siblings, smallest first. */
        get ladder() {
            const rows = this.siblings.map((b) => ({
                key: 'blueprint-' + b.id, name: b.name, memory: b.memory, draft: false,
            }));

            rows.push({ key: 'draft', name: this.name || 'This Blueprint', memory: Number(this.res.memory) || 0, draft: true });
            rows.sort((a, b) => a.memory - b.memory);

            const max = Math.max(1, ...rows.map((r) => r.memory));

            return rows.map((r) => ({
                key: r.key,
                name: r.name,
                draft: r.draft,
                label: this.size(r.memory),
                // A floor of 2 percent so a 0 MiB row is still a row.
                pct: Math.max(2, Math.round(r.memory / max * 100)),
            }));
        },

        /** An existing blueprint with this exact shape, which makes the draft pointless. */
        get twin() {
            return this.siblings.find((b) => b.memory === Number(this.res.memory)
                && b.disk === Number(this.res.disk)
                && b.cpu === Number(this.res.cpu)) || null;
        },

        get nameTaken() {
            const wanted = String(this.name || '').trim().toLowerCase();
            if (! wanted) return false;

            return (this.data.siblings || []).some((b) => String(b.name).trim().toLowerCase() === wanted);
        },

        get ioNote() {
            const io = Number(this.res.io) || 0;
            if (io === 500) return 'The normal share. Every server on the node competes for disk time equally.';

            return io < 500
                ? 'Below normal. This server yields disk time to the others when the node is busy.'
                : 'Above normal. This server wins disk time from the others when the node is busy.';
        },

        get swapText() {
            const n = Number(this.res.swap);
            if (n < 0) return 'Unlimited';
            if (n === 0) return 'Off';

            return this.size(n);
        },

        // -------------------------------------------------------- formatting

        /** MiB as the number a person would say. 0 is no cap, not nothing. */
        size(value) {
            const n = parseInt(value, 10);
            if (isNaN(n)) return '0 MiB';
            if (n < 0) return 'Unlimited';
            if (n === 0) return 'No Limit';

            return n >= 1024 ? (Math.round((n / 1024) * 10) / 10) + ' GiB' : n + ' MiB';
        },

        /** CPU percent as cores. 100 is one full core, and 0 is no quota at all. */
        cores(value) {
            const n = parseInt(value, 10);
            if (isNaN(n) || n <= 0) return 'No Limit';

            return (Math.round((n / 100) * 100) / 100) + (n === 100 ? ' Core' : ' Cores');
        },

        plural(count, one, many) {
            const n = Number(count) || 0;

            return n + ' ' + (n === 1 ? one : many);
        },

        // ------------------------------------------------------------ inputs

        clamp(value, min, max) {
            const n = Number(value);

            return isNaN(n) ? min : Math.max(min, Math.min(max, Math.round(n)));
        },

        /** Nearest slider stop to a value the number box may have typed freehand. */
        stopIndex(stops, value) {
            const n = Number(value) || 0;
            let best = 0;
            let closest = Infinity;

            stops.forEach((stop, i) => {
                const gap = Math.abs(stop - n);
                if (gap < closest) { closest = gap; best = i; }
            });

            return best;
        },

        bump(key, by, min, max) {
            this.res[key] = this.clamp((Number(this.res[key]) || 0) + by, min, max);
        },

        setSwap(mode) {
            this.swapMode = mode;
            if (mode === 'off') this.res.swap = 0;
            else if (mode === 'unlimited') this.res.swap = -1;
            else if (Number(this.res.swap) <= 0) this.res.swap = 512;
        },

        /**
         * Open the tab pane holding a field the browser just rejected.
         *
         * A pane that is not the active one is display:none, and a hidden
         * required input cannot be focused, so the browser abandons the submit
         * and says nothing: the operator presses Create and the page sits there.
         *
         * The classes are flipped by hand as well as through the tab button,
         * because validation carries on in this same tick and Alpine's own
         * update lands a microtask too late to make the field focusable.
         */
        openPaneFor(el) {
            const pane = el && el.closest ? el.closest('[role="tabpanel"]') : null;
            if (! pane || pane.classList.contains('is-active')) return;

            const parent = pane.parentElement;
            if (parent) parent.querySelectorAll('.gm-pane').forEach((p) => p.classList.remove('is-active'));
            pane.classList.add('is-active');

            // Click the tab rather than reach into its component: the tab set
            // owns its own state, and this keeps the strip in step.
            const button = document.getElementById('tab-' + pane.id.replace(/^pane-/, ''));
            if (button) button.click();
        },
    }));

    /* ---------------------------------------------------------- config editor
     * The Config tab renders the same controls the server create wizard does,
     * including the secret field with a Generate button beside it, so it needs
     * the same generateSecret(). The wizard's version reaches for $refs.form
     * because it lives inside one; this one walks up from the button, which is
     * the only difference between them.
     */
    Alpine.data('configEditor', (dirty) => ({
        dirty: !! dirty,
        touched: false,

        init() {
            // A game reads its config once, at boot. Typing in this form does
            // not reach a running server, so the moment somebody starts typing
            // the page says so rather than waiting for the save to explain it.
            this.$el.addEventListener('input', () => { this.touched = true; });
            this.$el.addEventListener('change', () => { this.touched = true; });
        },

        generateSecret(name) {
            const el = this.$el.querySelector('[name="' + name + '"]');
            if (! el) return;

            const alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            const bytes = new Uint32Array(20);
            (window.crypto || window.msCrypto).getRandomValues(bytes);

            let out = '';
            for (let i = 0; i < bytes.length; i++) out += alphabet[bytes[i] % alphabet.length];

            el.value = out;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.focus();
        },
    }));
});

/* -------------------------------------------------------------------------
 * The Minecraft server type, version and build picker.
 *
 * Markup is resources/views/admin/servers/_minecraft.blade.php, which renders
 * on the create wizard and on the Startup tab for any template carrying an
 * `mcjars` document. The lists come from MCJars through two cached panel
 * endpoints; nothing here talks to a third party directly.
 *
 * The rule this component is built around: a list that does not arrive must
 * never cost anybody a server. Every failure path lands on the same free text
 * box the panel used before there was a picker, and the hidden inputs that
 * actually post are bound to state that the operator can always reach.
 * ------------------------------------------------------------------------- */
document.addEventListener('alpine:init', () => {
    Alpine.data('minecraftPicker', (dataId) => ({
        mc: { types: [], versions: [], builds: {}, endpoints: {} },

        type: '',
        version: '',
        build: '',

        /** Current value of every build variable, keyed by template variable id. */
        buildValues: {},

        /** The build list for the type and version on screen. */
        buildList: [],

        snapshots: false,
        loadingVersions: false,
        loadingBuilds: false,
        versionsFailed: false,

        /* Version lists already fetched this page load, keyed by type. Flicking
         * between Paper and Purpur to compare is a normal thing to do, and it
         * should not be four round trips. */
        versionCache: {},
        versions: [],

        /* Builds are fetched lazily, on the first look at the build select,
         * rather than on page load. The create wizard renders one of these per
         * Minecraft template, all hidden but one, and a panel with a dozen of
         * them should not open a dozen connections to draw one dropdown. */
        buildsLoadedFor: '',

        init() {
            const island = document.getElementById(dataId);
            if (island) {
                try { this.mc = JSON.parse(island.textContent); } catch (e) { /* keep the empty shape */ }
            }

            this.type = String(this.mc.type || (this.mc.types[0] || {}).code || '');
            this.version = String(this.mc.version || '');
            this.buildValues = Object.assign({}, this.mc.builds || {});
            this.versions = Array.isArray(this.mc.versions) ? this.mc.versions : [];
            this.versionCache[this.type] = this.versions;
            this.build = this.currentBuildValue();

            // A stored version outside the release list is almost always a
            // snapshot somebody pinned on purpose, so the toggle opens showing
            // it rather than silently hiding what is actually configured.
            this.snapshots = this.versionIsSnapshot(this.version);

            this.$watch('type', () => this.onTypeChange());
            this.$watch('version', () => this.onVersionChange());
            this.$watch('build', (value) => this.writeBuild(value));

            // The version and build options are built by x-for and x-if, which
            // run after x-model has already tried to apply its value, so both
            // selects would otherwise open blank or showing the first option
            // while the hidden input posted something else entirely.
            this.$nextTick(() => this.syncSelects());
        },

        /**
         * Push the state back onto the selects once their options exist.
         *
         * Twice, and the second pass is the one that matters. x-for rebuilds
         * the whole option list on Alpine's own scheduler, which runs after
         * this microtask, and a select whose options are replaced loses its
         * value. Without the second pass a server pinned to Purpur build 2497
         * opened its form showing 2497 and then flipped to "Newest Build" the
         * instant the build list arrived, while the hidden input still posted
         * 2497. The frame callback runs after that rebuild and before paint, so
         * nothing flickers and the control agrees with what will be saved.
         */
        syncSelects() {
            const apply = () => {
                const set = (prefix, value) => {
                    const el = this.$el.querySelector('select[id^="' + prefix + '"]');
                    if (el && el.value !== value) el.value = value;
                };

                set('mc-version-', this.version);
                set('mc-build-', this.build);
            };

            apply();
            requestAnimationFrame(apply);
        },

        /**
         * The build dropdown's options after the static "newest" one: whatever
         * MCJars listed, with the value currently held spliced in front when
         * the list does not contain it.
         *
         * That happens before the list has been fetched at all, and for a build
         * older than the page MCJars returns, and in both cases an option that
         * is missing is a value silently discarded the moment somebody saves.
         *
         * The empty "newest" choice is deliberately NOT in here. An option
         * whose value is bound to an empty string keeps no value attribute at
         * all, and an option without one answers with its own text, so that
         * entry would have posted the literal string "Newest Build".
         */
        buildChoices() {
            const rows = this.buildList.slice();

            if (this.build && ! rows.some((row) => String(row.value) === String(this.build))) {
                rows.unshift({ value: this.build, label: 'Build ' + this.build, experimental: false });
            }

            return rows;
        },

        // ----------------------------------------------------------- lookups

        activeType() {
            return (this.mc.types || []).find((t) => t.code === this.type) || null;
        },

        hasBuild() {
            const t = this.activeType();

            return !!(t && t.build_variable);
        },

        buildLabel() {
            const t = this.activeType();

            return (t && t.build_label) || 'Build';
        },

        buildEnv() {
            const t = this.activeType();

            return (t && t.build_env) || '';
        },

        /** The value currently stored for the active type's build variable. */
        currentBuildValue() {
            const t = this.activeType();
            if (! t || ! t.build_variable) return '';

            return String(this.buildValues[t.build_variable] || '');
        },

        /** Write the chosen build back to the hidden input that posts it. */
        writeBuild(value) {
            const t = this.activeType();
            if (! t || ! t.build_variable) return;

            this.buildValues[t.build_variable] = value === undefined || value === null ? '' : String(value);
        },

        // ---------------------------------------------------------- versions

        versionsUsable() {
            return ! this.versionsFailed && this.versions.length > 0;
        },

        hasSnapshots() {
            return this.versionsUsable() && this.versions.some((row) => row.channel !== 'RELEASE');
        },

        visibleVersions() {
            const base = this.snapshots ? this.versions : this.versions.filter((row) => row.channel === 'RELEASE');

            // Never hide what is actually selected. Two things land here: a
            // pinned snapshot while the toggle is off, and a value MCJars has
            // no opinion about at all. "LATEST" is the second kind, and it is
            // the default half these templates ship with, so a picker that
            // could not show it would silently pin every new server to whatever
            // version happened to be newest the day it was created.
            if (this.version && ! base.some((row) => row.id === this.version)) {
                const known = this.versions.find((row) => row.id === this.version);

                return [known || { id: this.version, channel: 'RELEASE', java: null }].concat(base);
            }

            return base;
        },

        versionIsSnapshot(id) {
            const row = this.versions.find((v) => v.id === id);

            return !!row && row.channel !== 'RELEASE';
        },

        versionLabel(row) {
            let label = row.id;
            if (row.channel && row.channel !== 'RELEASE') label += '  ' + this.titleCase(row.channel);
            if (row.java) label += '  Java ' + row.java;

            return label;
        },

        buildOptionLabel(row) {
            let label = row.label || row.value;
            if (row.experimental) label += '  Experimental';

            return label;
        },

        // ----------------------------------------------------------- notices

        typeNote() {
            const t = this.activeType();
            if (! t) return '';

            const bits = [];
            if (t.deprecated) bits.push('No longer maintained.');
            if (t.experimental) bits.push('Experimental.');
            if (t.description) bits.push(t.description);

            return bits.join(' ').slice(0, 140);
        },

        versionNote() {
            if (this.loadingVersions) return 'Loading versions...';
            if (this.versionsFailed) return 'MCJars did not answer. Type a version instead.';

            const total = this.versions.length;
            if (! total) return 'No versions listed for this type.';

            return total + ' version' + (total === 1 ? '' : 's') + ' available.';
        },

        buildNote() {
            if (this.loadingBuilds) return 'Loading builds...';
            if (! this.build) return 'Newest build at each start.';

            return 'Pinned. The server will not move off this build.';
        },

        // ----------------------------------------------------------- changes

        async onTypeChange() {
            // Was the version on screen one this type's list actually offered?
            // Only then may it be replaced. A sentinel the image understands,
            // "LATEST", or anything an operator typed by hand is theirs, and
            // moving it because they looked at a different type is not a
            // convenience, it is the panel changing an answer behind them.
            const wasListed = this.versions.some((row) => row.id === this.version);

            this.build = this.currentBuildValue();
            this.buildList = [];
            this.buildsLoadedFor = '';

            await this.loadVersions(wasListed);

            if (this.hasBuild()) this.loadBuilds();
        },

        onVersionChange() {
            this.buildList = [];
            this.buildsLoadedFor = '';

            if (this.hasBuild()) this.loadBuilds();
        },

        async loadVersions(reconcile = false) {
            const type = this.type;
            if (! type) return;

            if (this.versionCache[type]) {
                this.versions = this.versionCache[type];
                this.versionsFailed = false;
                if (reconcile) this.reconcileVersion();

                return;
            }

            this.loadingVersions = true;
            const rows = await this.fetchList(this.mc.endpoints.versions, { type: type }, 'versions');
            this.loadingVersions = false;

            // A different type may have been picked while this was in flight.
            if (this.type !== type) return;

            this.versionsFailed = rows === null;
            this.versions = rows || [];
            if (rows) this.versionCache[type] = rows;
            if (reconcile) this.reconcileVersion();
            this.$nextTick(() => this.syncSelects());
        },

        async loadBuilds() {
            if (! this.hasBuild() || ! this.version) return;

            const key = this.type + '/' + this.version;
            if (this.buildsLoadedFor === key || this.loadingBuilds) return;

            this.loadingBuilds = true;
            const rows = await this.fetchList(this.mc.endpoints.builds, { type: this.type, version: this.version }, 'builds');
            this.loadingBuilds = false;

            if (this.type + '/' + this.version !== key) return;

            this.buildList = rows || [];
            this.buildsLoadedFor = key;
            this.$nextTick(() => this.syncSelects());
        },

        /* A version that no longer exists for the newly chosen type has to
         * become one that does, or the form posts a pair the image cannot
         * resolve. The newest release wins, which is what somebody switching
         * from Paper to Purpur almost always wanted anyway. */
        reconcileVersion() {
            if (! this.versions.length) return;
            if (this.versions.some((row) => row.id === this.version)) return;

            const release = this.versions.find((row) => row.channel === 'RELEASE');
            this.version = (release || this.versions[0]).id;
            this.snapshots = this.versionIsSnapshot(this.version);
        },

        // ------------------------------------------------------------- wire

        /**
         * One lookup. Answers an array, or null when the panel could not get a
         * usable list, which is the only signal the rest of the component needs
         * to fall back to a text box.
         */
        async fetchList(url, params, key) {
            if (! url) return null;

            try {
                const query = new URLSearchParams(params).toString();
                const response = await fetch(url + '?' + query, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (! response.ok) return null;

                const body = await response.json();

                return body && body.ok && Array.isArray(body[key]) ? body[key] : null;
            } catch (e) {
                return null;
            }
        },

        titleCase(word) {
            const lower = String(word || '').toLowerCase();

            return lower.charAt(0).toUpperCase() + lower.slice(1);
        },
    }));
});

/* A switch that is its own submit button.
 *
 * The Mods tab turns a plugin on and off with a toggle switch, per house style,
 * and a switch that needs a Save button beside it is not a switch. So the form
 * it sits in carries data-autosubmit and posts the moment the switch changes.
 *
 * Delegated from the document rather than bound per form: the listener is
 * registered once, works for rows added later, and costs nothing on a page that
 * has no such form. Outside alpine:init because it touches no Alpine state.
 *
 * The switch is disabled straight after firing. A real POST takes long enough
 * to click twice, and two posts of the same toggle land as on, then off.
 */
(function autoSubmitSwitches() {
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (! input || input.type !== 'checkbox') return;

        var form = input.form;
        if (! form || ! form.hasAttribute('data-autosubmit')) return;

        form.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
            box.disabled = true;
        });

        // A disabled checkbox is not submitted at all, and "absent" is how the
        // controller reads "off". So the state is carried by a hidden field
        // that is immune to the disabling above.
        if (input.checked) {
            var carry = document.createElement('input');
            carry.type = 'hidden';
            carry.name = input.name;
            carry.value = input.value || '1';
            form.appendChild(carry);
        }

        form.submit();
    });
})();
