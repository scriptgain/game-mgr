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

    /* --------------------------------------------------------- server wizard
     * The admin "New Server" screen. Creating a server is a handful of short
     * decisions, so it is presented as six steps rather than one page of forty
     * fields. There is still exactly ONE form and ONE POST: the steps are shown
     * and hidden here, every field stays in the DOM, and the controller sees the
     * same payload it always did.
     *
     * The server remains the authority on what is valid. Everything checked here
     * is a courtesy so nobody reaches the review step to be told step two was
     * wrong; when the POST does come back with errors the controller tells us
     * which step to open on.
     *
     * Reads its data from a <script type="application/json"> island rather than
     * a giant x-data attribute, which keeps the Blade file readable and the
     * escaping simple.
     */
    Alpine.data('serverWizard', (dataId) => ({
        data: { users: [], templates: [], blueprints: [], nodes: [], selected: {}, variable_errors: {}, step: 1 },
        steps: [
            { n: 1, label: 'What To Run' },
            { n: 2, label: 'Where It Runs' },
            { n: 3, label: 'Owner And Name' },
            { n: 4, label: 'Resources' },
            { n: 5, label: 'Variables' },
            { n: 6, label: 'Review' },
        ],
        step: 1,
        furthest: 1,
        placement: 'auto',
        templateId: '',
        blueprintId: '',
        nodeId: '',
        allocationId: '',
        vars: {},
        seeded: {},
        showAdvanced: false,
        review: [],

        init() {
            const island = document.getElementById(dataId);
            if (island) {
                try { this.data = JSON.parse(island.textContent); } catch (e) { /* keep the empty shape */ }
            }

            const chosen = this.data.selected || {};
            this.step = this.data.step || 1;
            this.furthest = this.step;
            this.seeded = chosen.variables || {};

            this.templateId = String(chosen.template_id || (this.data.templates[0] || {}).id || '');
            this.nodeId = chosen.node_id ? String(chosen.node_id) : '';
            this.allocationId = chosen.allocation_id ? String(chosen.allocation_id) : '';
            // A node in the posted data means the operator placed it by hand.
            this.placement = this.nodeId ? 'manual' : 'auto';
            this.showAdvanced = !!(this.val('image') || this.val('startup'));

            this.syncVariables();

            // The node and port options are built by x-for, which runs after
            // x-model has already tried to apply its value, so the select would
            // otherwise come back empty when a rejected POST is redisplayed.
            this.$nextTick(() => {
                if (this.nodeId) this.set('node_id', this.nodeId);
                if (this.allocationId) this.set('allocation_id', this.allocationId);
            });

            // Watchers are registered after seeding on purpose: reopening on a
            // failed step must not wipe what the operator already typed.
            this.$watch('templateId', () => this.onTemplateChange());
            this.$watch('nodeId', () => { this.allocationId = ''; });
            this.$watch('placement', (mode) => {
                // Leaving a stale node_id behind would silently override the
                // auto placement the operator just asked for.
                if (mode === 'auto') {
                    this.nodeId = '';
                    this.allocationId = '';
                } else {
                    this.set('location_id', '');
                }
            });

            if (this.step === this.steps.length) this.buildReview();
        },

        // ------------------------------------------------------------ lookups

        get template() {
            return this.data.templates.find((t) => String(t.id) === String(this.templateId)) || null;
        },

        /** Only nodes that can actually run this template's runtime. */
        get nodeChoices() {
            const t = this.template;
            if (!t) return this.data.nodes;

            return this.data.nodes.filter((n) => (n.runtimes || []).indexOf(t.runtime) > -1);
        },

        get hiddenNodeCount() {
            return this.data.nodes.length - this.nodeChoices.length;
        },

        get node() {
            return this.data.nodes.find((n) => String(n.id) === String(this.nodeId)) || null;
        },

        get allocationChoices() {
            return this.node ? (this.node.allocations || []) : [];
        },

        get variables() {
            return this.template ? (this.template.variables || []) : [];
        },

        variableError(id) {
            return (this.data.variable_errors || {})[String(id)] || '';
        },

        // ------------------------------------------------------------- fields

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
            if (el && value !== undefined && value !== null && value !== '') el.value = value;
            else if (el && value === '') el.value = '';
        },

        /** The visible text of the chosen option, for the review step. */
        optionText(name) {
            const el = this.field(name);
            if (!el || el.selectedIndex < 0) return '';

            return el.options[el.selectedIndex].textContent.trim();
        },

        // ------------------------------------------------------------ changes

        onTemplateChange() {
            // A node that cannot run the new template must not stay selected.
            if (this.nodeId && !this.nodeChoices.some((n) => String(n.id) === String(this.nodeId))) {
                this.nodeId = '';
            }
            this.syncVariables();
        },

        /**
         * Reset the variable values to the chosen template's defaults, keeping
         * anything a rejected POST sent back.
         *
         * Laravel turns an empty posted string into null on the way in, so a
         * field the operator deliberately cleared comes back as null rather than
         * "". Stringifying that blindly would write the word "null" into the box.
         */
        syncVariables() {
            const next = {};
            this.variables.forEach((v) => {
                const seed = this.seeded[v.id];
                if (seed === undefined) next[v.id] = v.default;
                else next[v.id] = seed === null ? '' : String(seed);
            });
            this.vars = next;
        },

        applyBlueprint() {
            const b = this.data.blueprints.find((x) => String(x.id) === String(this.blueprintId));
            if (!b) return;

            if (b.template_id) this.templateId = String(b.template_id);

            const limits = b.limits || {};
            const features = b.features || {};
            [['memory', limits.memory], ['disk', limits.disk], ['cpu', limits.cpu],
                ['swap', limits.swap], ['io', limits.io],
                ['database_limit', features.databases], ['allocation_limit', features.allocations],
                ['backup_limit', features.backups]].forEach(([name, value]) => {
                if (value !== undefined && value !== null) this.set(name, value);
            });

            // The template swap resets the variables, so blueprint environment
            // values are applied once that has settled.
            this.$nextTick(() => {
                const env = b.environment || {};
                Object.keys(env).forEach((key) => {
                    const match = this.variables.find((v) => v.env === key);
                    if (match) this.vars[match.id] = String(env[key]);
                });
            });
        },

        // --------------------------------------------------------- navigation

        /**
         * Native constraint validation, applied only to the controls the
         * operator can actually see. A control inside a hidden branch has no
         * offsetParent and cannot be focused, so reporting on it would throw the
         * browser's un-focusable-control error and show nothing.
         */
        validateStep(n) {
            const panel = document.querySelector('[data-step="' + n + '"]');
            if (!panel) return true;

            const controls = panel.querySelectorAll('input, select, textarea');

            for (let i = 0; i < controls.length; i++) {
                const el = controls[i];
                if (el.disabled || el.type === 'hidden' || el.offsetParent === null) continue;
                if (!el.checkValidity()) {
                    el.focus();
                    el.reportValidity();

                    return false;
                }
            }

            return true;
        },

        go(n) {
            if (n > this.step) {
                for (let i = this.step; i < n; i++) {
                    if (!this.validateStep(i)) {
                        this.step = i;

                        return;
                    }
                }
            }

            this.step = n;
            if (n > this.furthest) this.furthest = n;
            if (n === this.steps.length) this.buildReview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        next() { this.go(Math.min(this.step + 1, this.steps.length)); },
        back() { this.go(Math.max(this.step - 1, 1)); },

        /** Enter anywhere but the last step advances rather than submits. */
        onEnter(event) {
            if (this.step >= this.steps.length) return;
            if (event.target && event.target.tagName === 'TEXTAREA') return;
            event.preventDefault();
            this.next();
        },

        /**
         * Last line of defence. If anything invalid slipped through, open the
         * step that owns it rather than letting the browser refuse to submit
         * with nothing on screen to explain why.
         */
        onSubmit(event) {
            const form = this.$refs.form;
            if (!form || form.checkValidity()) return;

            event.preventDefault();
            const bad = form.querySelector(':invalid');
            if (!bad) return;

            const panel = bad.closest('[data-step]');
            if (panel) this.step = parseInt(panel.getAttribute('data-step'), 10) || this.step;
            this.$nextTick(() => { bad.focus(); bad.reportValidity(); });
        },

        // ------------------------------------------------------------- review

        mib(value) {
            const n = parseInt(value, 10);
            if (isNaN(n)) return '0 MiB';
            if (n < 0) return 'Unlimited';

            return n >= 1024 ? (Math.round((n / 1024) * 10) / 10) + ' GiB' : n + ' MiB';
        },

        onOff(name) {
            return this.val(name) === '0' || this.val(name) === '' ? 'Off' : 'On';
        },

        buildReview() {
            const t = this.template;
            const alloc = this.allocationChoices.find((a) => String(a.id) === String(this.allocationId));
            const manual = this.placement === 'manual';

            const groups = [
                {
                    step: 1,
                    title: 'What To Run',
                    rows: [
                        ['Template', t ? ((t.game ? t.game + ' : ' : '') + t.name) : 'Not chosen'],
                        ['Runtime', t ? t.runtime_label : ''],
                        ['Docker Image', this.val('image') || (t && t.default_image ? t.default_image + ' (template default)' : 'Template default')],
                        ['Startup Command', this.val('startup') || 'Template default'],
                    ],
                },
                {
                    step: 2,
                    title: 'Where It Runs',
                    rows: manual
                        ? [
                            ['Placement', 'Chosen By Hand'],
                            ['Node', this.node ? this.node.name + (this.node.location ? ' (' + this.node.location + ')' : '') : 'Not chosen'],
                            ['Port', alloc ? alloc.label : 'First free port on the node'],
                        ]
                        : [
                            ['Placement', 'Automatic'],
                            ['Preferred Location', this.val('location_id') ? this.optionText('location_id') : 'Anywhere'],
                            ['Node', 'Picked at create time, emptiest node with room'],
                        ],
                },
                {
                    step: 3,
                    title: 'Owner And Name',
                    rows: [
                        ['Name', this.val('name')],
                        ['Description', this.val('description') || 'None'],
                        ['Owner', this.optionText('owner_id')],
                    ],
                },
                {
                    step: 4,
                    title: 'Resources',
                    rows: [
                        ['Memory', this.mib(this.val('memory'))],
                        ['Disk', this.mib(this.val('disk'))],
                        ['CPU', this.val('cpu') + '%'],
                        ['Swap', this.mib(this.val('swap'))],
                        ['Block IO Weight', this.val('io')],
                        ['Databases', this.val('database_limit')],
                        ['Extra Ports', this.val('allocation_limit')],
                        ['Backups', this.val('backup_limit')],
                        ['Restart After A Crash', this.onOff('auto_restart')],
                        ['Update Game Files Automatically', this.onOff('auto_update')],
                    ],
                },
                {
                    step: 5,
                    title: 'Startup Variables',
                    rows: this.variables.length
                        ? this.variables.map((v) => [v.name, this.vars[v.id] === '' ? 'Empty' : this.vars[v.id]])
                        : [['Variables', 'This template exposes none']],
                },
            ];

            this.review = groups;
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
