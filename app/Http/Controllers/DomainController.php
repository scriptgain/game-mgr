<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\Setting;
use App\Services\Dns\DnsConfig;
use App\Services\Dns\WildcardManager;
use Illuminate\Http\Request;

/**
 * The Domains card in Settings: which zone the panel owns, which provider
 * writes to it, and the token it writes with.
 *
 * Nothing here can break connectivity. Turning the feature off puts every
 * screen back to the direct ip:port it has always shown; turning it on adds a
 * second address beside it.
 */
class DomainController extends Controller
{
    public function edit()
    {
        return view('settings.domains', [
            'title' => 'Domains',
            'nodes' => Node::query()->orderBy('name')->get(),
            'hasToken' => filled(Setting::secret('domains_api_token')),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'domains_provider' => ['required', 'in:'.implode(',', array_keys(DnsConfig::PROVIDERS))],
            // A hostname, not a URL. Validated loosely on purpose: a zone can
            // legitimately be a deep suffix like play.eu.example.com.
            'domains_zone' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
            'domains_api_token' => ['nullable', 'string', 'max:255'],
        ], [
            'domains_zone.regex' => 'That is not a domain name. Enter something like play.example.com, with no scheme and no trailing dot.',
        ]);

        $enabled = $request->boolean('domains_enabled');

        if ($enabled && blank($data['domains_zone'] ?? null)) {
            return back()
                ->withErrors(['domains_zone' => 'Connection names need a zone to live under before they can be turned on.'])
                ->withInput();
        }

        Setting::put('domains_enabled', $enabled ? '1' : '0');
        Setting::put('domains_provider', $data['domains_provider']);
        Setting::put('domains_zone', mb_strtolower(trim((string) ($data['domains_zone'] ?? ''), " \t\n\r\0\x0B.")));

        // Same rule the Telegram token follows: a blank box means "keep what is
        // stored", never "delete it". Clearing is its own button.
        if (filled($data['domains_api_token'] ?? null)) {
            Setting::putSecret('domains_api_token', $data['domains_api_token']);
        }

        return redirect()->route('settings.domains.edit')->with('status', 'Domain settings saved.');
    }

    public function clearToken()
    {
        Setting::putSecret('domains_api_token', null);

        return redirect()->route('settings.domains.edit')->with('status', 'The stored API token was deleted.');
    }

    /**
     * Reconcile every node now rather than waiting for the hourly run.
     *
     * Slow by nature, one provider round trip per node, but it is a button an
     * operator pressed and it cannot throw: WildcardManager records failures
     * instead of raising them.
     */
    public function sync(WildcardManager $wildcards)
    {
        if (! DnsConfig::active()) {
            return back()->with('error', 'Turn connection names on and set a zone first.');
        }

        $results = $wildcards->syncAll();
        $ok = count(array_filter($results, fn ($s) => $s === WildcardManager::STATUS_ACTIVE));

        return back()->with('status', $ok.' of '.count($results).' nodes confirmed. Any that did not are listed below with the reason.');
    }
}
