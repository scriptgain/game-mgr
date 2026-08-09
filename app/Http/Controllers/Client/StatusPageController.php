<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\StatusPage;
use Illuminate\Http\Request;

/**
 * The public, opt-in status page for one server. No auth: this is the link a
 * community owner puts in their Discord so nobody has to ask "is it down".
 *
 * Three shapes of the same facts, because a hosted page is only one of the
 * things people want:
 *
 *   show()   the full page, for a link somebody clicks.
 *   json()   the same data as JSON, so anybody can build their own widget.
 *   embed()  a bare card with no chrome, sized for an iframe on their own site.
 *
 * All three obey the SAME per-page toggles. It would be pointless to let
 * somebody hide the player count on the page and then hand it out in JSON, and
 * the JSON is the easier one to forget.
 *
 * The JSON and the embed are deliberately CORS-open and cached for a short
 * while. This is public data by definition, the page it comes from is opt-in,
 * and a widget on somebody's homepage should not be able to hammer the panel:
 * the cache header is what stops one popular Discord turning into a load test.
 */
class StatusPageController extends Controller
{
    /** Long enough to blunt a busy page, short enough that "online" means it. */
    private const CACHE_SECONDS = 30;

    public function show(string $slug)
    {
        [$page, $server] = $this->find($slug);

        return view('status', [
            'title' => $page->headline ?: $server->name,
            'page' => $page,
            'server' => $server,
            'peak' => $this->peak($server),
        ]);
    }

    /**
     * The same facts as JSON, for somebody building their own widget.
     *
     * The shape is a contract: fields are added, never renamed or removed, and
     * a hidden field is absent rather than null so "not shown" and "nothing to
     * show" cannot be confused.
     */
    public function json(string $slug)
    {
        [$page, $server] = $this->find($slug);

        $online = $server->power_state === 'running';

        $data = [
            'name' => $page->headline ?: $server->name,
            'game' => $server->template?->game?->name,
            'online' => $online,
            'status' => $online ? 'online' : 'offline',
            'region' => $server->node?->location?->name,
            'updated_at' => optional($server->cached_at)->toIso8601String(),
        ];

        if ($page->show_address) {
            $data['address'] = $server->address();
            // Only when there is one. A null here reads as "the name is broken"
            // rather than "this install does not use names".
            if ($connect = $server->connectAddress()) {
                $data['connect'] = $connect;
            }
        }

        if ($page->show_players) {
            $data['players'] = [
                'online' => (int) $server->cached_players,
                'max' => $server->cached_max_players ? (int) $server->cached_max_players : null,
                'peak_today' => (int) $this->peak($server),
            ];
        }

        if ($page->show_uptime && $server->last_started_at) {
            $data['started_at'] = $server->last_started_at->toIso8601String();
            $data['uptime_seconds'] = max(0, $server->last_started_at->diffInSeconds(now()));
        }

        if ($page->show_version) {
            $data['running'] = $server->template?->name;
        }

        return response()->json($data)->withHeaders($this->publicHeaders());
    }

    /**
     * A bare card for an iframe on somebody else's site.
     *
     * No panel chrome, no nav, no link back into the install, and it inherits
     * nothing: an embed lands in a page whose CSS is none of our business, so
     * this one carries its own and sets its own box.
     */
    public function embed(Request $request, string $slug)
    {
        [$page, $server] = $this->find($slug);

        $theme = $request->query('theme');

        return response()
            ->view('status-embed', [
                'page' => $page,
                'server' => $server,
                // auto follows the visitor's own OS setting, which is the right
                // default for a card sitting inside a stranger's page.
                'theme' => in_array($theme, ['light', 'dark'], true) ? $theme : 'auto',
            ])
            ->withHeaders($this->publicHeaders());
    }

    /**
     * A page that exists but is switched off must 404 exactly like one that
     * never existed, or the URL confirms the server is real.
     *
     * @return array{0:StatusPage,1:Server}
     */
    private function find(string $slug): array
    {
        $page = StatusPage::where('slug', $slug)->where('is_public', true)->first();
        abort_unless($page, 404);

        $server = $page->server()->with('template.game', 'allocation', 'node.location')->first();
        abort_unless($server, 404);

        return [$page, $server];
    }

    private function peak(Server $server): int
    {
        return (int) $server->metrics()
            ->where('sampled_at', '>=', now()->subDay())
            ->max('players');
    }

    /** @return array<string,string> */
    private function publicHeaders(): array
    {
        return [
            // Public by definition and opt-in by the owner, so any origin may
            // read it. Without this a widget on their own site cannot.
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
            // Framing is the whole point of an embed, so it is granted
            // explicitly. frame-ancestors is used rather than clearing
            // X-Frame-Options because a browser that sees both obeys the CSP,
            // which means a blanket SAMEORIGIN added by somebody's nginx does
            // not silently break every embed on the internet.
            'Content-Security-Policy' => 'frame-ancestors *',
        ];
    }
}
