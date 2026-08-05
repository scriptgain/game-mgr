<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\StatusPage;

/**
 * The public, opt-in status page for one server. No auth: this is the link a
 * community owner puts in their Discord so nobody has to ask "is it down".
 */
class StatusPageController extends Controller
{
    public function show(string $slug)
    {
        $page = StatusPage::where('slug', $slug)->where('is_public', true)->first();

        // A page that exists but is switched off must 404 exactly like one that
        // never existed, or the URL confirms the server is real.
        abort_unless($page, 404);

        $server = $page->server()->with('template.game', 'allocation', 'node')->first();
        abort_unless($server, 404);

        return view('status', [
            'title' => $page->headline ?: $server->name,
            'page' => $page,
            'server' => $server,
            'peak' => $server->metrics()
                ->where('sampled_at', '>=', now()->subDay())
                ->max('players'),
        ]);
    }
}
