/* GameMGR front-end behaviour.
 *
 * ORDERING HAZARD: this file MUST load before the Alpine CDN script, which the
 * x-tailwind-cdn component pulls in. Alpine fires alpine:init the moment it
 * starts, so anything registered after that point silently never exists, and
 * inline x-data keeps working either way, which is what makes it easy to miss.
 */
document.addEventListener('alpine:init', () => {

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
            this.measure();
            // Fonts landing late changes every label's width, so measure again.
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(() => this.measure());
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

    /* --------------------------------------------------------------- console
     * Live console output and stats over Server-Sent Events straight from the
     * node daemon. SSE rather than a websocket because the feed is one-way, it
     * survives every proxy that already speaks HTTP, and it keeps the daemon on
     * the Go standard library with no vendored dependency.
     */
    Alpine.data('gameConsole', (config) => ({
        lines: config.backlog || [],
        connected: false,
        autoScroll: true,
        command: '',
        history: [],
        historyIndex: -1,
        stats: { cpu: 0, memory_mib: 0, memory_cap_mib: config.memory || 0, players: 0, state: config.state || 'offline' },
        source: null,
        MAX_LINES: 2000,

        init() {
            this.$nextTick(() => this.scroll());
            this.connect();
            // A tab left open for a day must not accumulate a day of DOM.
            this.$watch('lines', () => {
                if (this.lines.length > this.MAX_LINES) {
                    this.lines.splice(0, this.lines.length - this.MAX_LINES);
                }
            });
        },

        connect() {
            if (!config.streamUrl || typeof EventSource === 'undefined') return;

            try {
                this.source = new EventSource(config.streamUrl);
            } catch (e) {
                return;
            }

            this.source.addEventListener('open', () => { this.connected = true; });

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
            });
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

        cpuPercent() {
            const cap = config.cpuLimit || 100;
            return Math.min(100, Math.round((this.stats.cpu / cap) * 100));
        },

        formatMib(mib) {
            if (mib >= 1024) return (mib / 1024).toFixed(1) + ' GiB';
            return Math.round(mib) + ' MiB';
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
    Alpine.data('fileBrowser', () => ({
        selected: [],
        lastIndex: null,

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

        clear() {
            this.selected = [];
            this.lastIndex = null;
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
