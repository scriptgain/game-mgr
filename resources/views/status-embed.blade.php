{{-- A status card for an iframe on somebody else's website.

     It lands in a page whose CSS is none of our business, so it inherits
     nothing and brings its own: no Tailwind, no panel chrome, no nav, and no
     link back into the install. Plain CSS in one <style> because an embed that
     depends on a CDN is an embed that is blank when the CDN is slow.

     Sized to fit a 320x160 iframe without scrolling, and it degrades to a
     narrower box on its own rather than clipping. --}}
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->headline ?: $server->name }}</title>
    <style>
        :root {
            --bg: #ffffff; --fg: #0f172a; --muted: #64748b; --line: #e2e8f0;
            --chip: #f1f5f9; --ok: #059669; --okbg: #ecfdf5; --okline: #a7f3d0;
            --off: #64748b; --offbg: #f1f5f9; --offline: #e2e8f0;
        }
        @media (prefers-color-scheme: dark) {
            html[data-theme="auto"] {
                --bg: #0f172a; --fg: #f8fafc; --muted: #94a3b8; --line: #1e293b;
                --chip: #1e293b; --ok: #34d399; --okbg: #052e2b; --okline: #065f46;
                --off: #94a3b8; --offbg: #1e293b; --offline: #334155;
            }
        }
        html[data-theme="dark"] {
            --bg: #0f172a; --fg: #f8fafc; --muted: #94a3b8; --line: #1e293b;
            --chip: #1e293b; --ok: #34d399; --okbg: #052e2b; --okline: #065f46;
            --off: #94a3b8; --offbg: #1e293b; --offline: #334155;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: transparent; }
        body {
            font: 14px/1.45 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: var(--fg);
            /* overflow-wrap, not just wrapping: an address is one unbroken run
               with nowhere to break, and one of those sets the width floor for
               the whole card. */
            overflow-wrap: anywhere;
        }
        .card {
            background: var(--bg); border: 1px solid var(--line); border-radius: 12px;
            padding: 14px 16px; max-width: 420px;
        }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .name { font-weight: 600; font-size: 15px; margin: 0; min-width: 0; }
        .game { color: var(--muted); font-size: 12px; margin: 2px 0 0; }
        .pill {
            display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto;
            border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 600;
            background: var(--offbg); color: var(--off); border: 1px solid var(--offline);
        }
        .pill.on { background: var(--okbg); color: var(--ok); border-color: var(--okline); }
        .dot { width: 7px; height: 7px; border-radius: 999px; background: currentColor; }
        .rows { margin: 12px 0 0; padding: 10px 0 0; border-top: 1px solid var(--line);
                display: grid; gap: 6px; }
        .row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; font-size: 13px; }
        .k { color: var(--muted); flex: 0 0 auto; }
        .v { font-weight: 500; text-align: right; min-width: 0; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
    </style>
</head>
<body>
<div class="card">
    <div class="top">
        <div style="min-width:0">
            <p class="name">{{ $page->headline ?: $server->name }}</p>
            @if ($server->template?->game?->name)
                <p class="game">{{ $server->template->game->name }}</p>
            @endif
        </div>
        <span class="pill {{ $server->power_state === 'running' ? 'on' : '' }}">
            <span class="dot"></span>{{ $server->power_state === 'running' ? 'Online' : 'Offline' }}
        </span>
    </div>

    <div class="rows">
        @if ($page->show_address)
            <div class="row">
                <span class="k">Connect</span>
                <span class="v mono">{{ $server->connectAddress() ?: $server->address() }}</span>
            </div>
        @endif
        @if ($page->show_players)
            <div class="row">
                <span class="k">Players</span>
                <span class="v">{{ (int) $server->cached_players }}@if ($server->cached_max_players) / {{ (int) $server->cached_max_players }}@endif</span>
            </div>
        @endif
        @if ($page->show_version && $server->template?->name)
            <div class="row">
                <span class="k">Running</span>
                <span class="v">{{ $server->template->name }}</span>
            </div>
        @endif
    </div>
</div>
</body>
</html>
