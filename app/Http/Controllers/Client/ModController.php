<?php

namespace App\Http\Controllers\Client;

use App\Models\Mod;
use App\Models\Server;
use App\Services\Mods\ModInstaller;
use App\Services\Mods\ModTarget;
use App\Services\Mods\Contracts\ModSource;
use App\Services\Mods\ModSourceRegistry;
use Illuminate\Http\Request;

/**
 * Mods and plugins.
 *
 * The workflow Pterodactyl leaves to the file manager: find a jar on a website,
 * download it, drag it into /plugins, restart, hope. Here it is a managed list
 * with a source, a version, and a visible answer to "is there an update".
 *
 * The catalogues come from ModSourceRegistry, which knows which of them this
 * template declares, which this panel has a client for, and which suit the
 * loader this server actually runs. Search is narrowed to that loader and to
 * the Minecraft version, install verifies the checksum the source published
 * before the file reaches the node, disable renames to .disabled rather than
 * deleting, and remove deletes both the file and the row.
 *
 * A source a template declares but this panel cannot serve is NAMED, with the
 * reason, rather than dropped. Silently hiding it is how a finished feature
 * comes to look like a missing one.
 *
 * DEGRADING. Every catalogue is a third party and will one day be slow, rate
 * limiting or down. None of that may break this page. Every call answers null
 * instead of throwing, the installed list renders from the database and never
 * from the network, and an unavailable source becomes a note rather than an
 * exception. Nothing in this controller can 500 because a catalogue had a bad
 * afternoon.
 */
class ModController extends ServerController
{
    public function __construct(
        private readonly ModSourceRegistry $registry,
        private readonly ModInstaller $installer,
    ) {}

    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'mod.read');

        $server->load('node', 'template.game', 'template.variables');
        $target = ModTarget::for($server);


        $mods = $server->mods()->orderBy('name')->get();

        return view('server.mods', [
            'title' => $server->name.' Mods',
            'server' => $server,
            'mods' => $mods,
            'updatable' => $mods->filter->hasUpdate(),
            'sources' => $target->sources,
            'target' => $target,
            'catalogue' => $this->catalogueState($target, $this->registry->for($target)[0] ?? null),
            'unusable' => $this->registry->unusable($target),
        ]);
    }

    /**
     * Re-check every installed mod against the API.
     *
     * A POST, because it writes: it rewrites what the panel believes the newest
     * version is. It was a GET so that it could be a plain link, which meant a
     * prefetch or a crawler could trigger a handful of outbound API calls.
     */
    public function refresh(Request $request, Server $server)
    {
        $this->guard($server, 'mod.update');

        $server->load('node', 'template.game', 'template.variables');
        $waiting = $this->installer->refresh($server, ModTarget::for($server));

        return redirect()->route('server.mods', $server)->with(
            'status',
            $this->anyDegraded($target)
                ? 'A catalogue did not answer, so part of the version list was left as it was.'
                : ($waiting === 0
                    ? 'Every mod is on the newest version this server can run.'
                    : $waiting.' '.str('mod')->plural($waiting).' can be updated.'),
        );
    }

    public function browse(Request $request, Server $server)
    {
        $this->guard($server, 'mod.read');

        $server->load('node', 'template.game', 'template.variables');
        $target = ModTarget::for($server);
        $query = trim((string) $request->query('q'));

        // Read before the search runs, not after. A search that fails trips the
        // "Modrinth is down" marker, and reading the state afterwards would
        // replace the specific "that search did not answer" screen with the
        // general banner on the very request that learned it.
        $usable = $this->registry->for($target);
        $source = $this->pickSource($request, $usable);
        $catalogue = $this->catalogueState($target, $source);

        // null and [] mean different things and the view shows different
        // screens for them: null is "Modrinth did not answer", [] is "Modrinth
        // answered and there is nothing".
        $results = $query === '' || ! $catalogue['ok'] || $source === null
            ? []
            : $source->search($query, $target);

        return view('server.mod-browse', [
            'title' => 'Browse Mods',
            'server' => $server,
            'sources' => $target->sources,
            'target' => $target,
            'query' => $query,
            'results' => $results,
            'catalogue' => $catalogue,
            'usable' => $usable,
            'source' => $source,
            'unusable' => $this->registry->unusable($target),
            'installed' => $server->mods()->pluck('remote_id')->filter()->all(),
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'mod.install');

        $data = $request->validate([
            // A project id or slug, and which catalogue it belongs to. Nothing
            // else is accepted: the name, author and summary all come back from
            // the API, so a forged form cannot invent a mod that is not really
            // there, and the source is checked against the template rather than
            // trusted, so it cannot reach a catalogue this server may not use.
            'source' => ['required', 'string', 'max:32', 'alpha_dash'],
            'project' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9!@$()`.+,_"\-]+$/'],
        ], [
            'project.regex' => 'That is not a catalogue project id.',
        ]);

        $server->load('node', 'template.game', 'template.variables');
        $result = $this->installer->install($server, ModTarget::for($server), $data['source'], $data['project']);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        $this->log($server, 'mod.install', 'Installed '.$result['mod']->name.' '.$result['mod']->version.' to '.$result['mod']->path);

        return redirect()->route('server.mods', $server)->with('status', $result['message']);
    }

    public function update(Server $server, Mod $mod)
    {
        $this->guard($server, 'mod.update');
        abort_unless($mod->server_id === $server->id, 404);

        $server->load('node', 'template.game', 'template.variables');
        $from = $mod->version;
        $result = $this->installer->update($server, ModTarget::for($server), $mod);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        if ($mod->fresh()?->version !== $from) {
            $this->log($server, 'mod.update', 'Updated '.$mod->name.' from '.$from.' to '.$mod->version);
        }

        return back()->with('status', $result['message']);
    }

    public function toggle(Request $request, Server $server, Mod $mod)
    {
        $this->guard($server, 'mod.update');
        abort_unless($mod->server_id === $server->id, 404);

        // The switch posts its state. A switch that is off posts nothing at
        // all, which is exactly what boolean() reads as false, so the request
        // says what the customer chose rather than what the panel assumed.
        $enabled = $request->has('enabled') ? $request->boolean('enabled') : ! $mod->enabled;

        $server->load('node');
        $result = $this->installer->setEnabled($server, $mod, $enabled);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        $this->log($server, 'mod.toggle', ($enabled ? 'Enabled ' : 'Disabled ').$mod->name);

        return back()->with('status', $result['message']);
    }

    public function destroy(Server $server, Mod $mod)
    {
        $this->guard($server, 'mod.delete');
        abort_unless($mod->server_id === $server->id, 404);

        $server->load('node');
        $name = $mod->name;
        $result = $this->installer->remove($server, $mod);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        $this->log($server, 'mod.delete', 'Removed '.$name);

        return back()->with('status', $result['message']);
    }

    // ---------------------------------------------------------------- inside

    /**
     * What the screen should say before anyone searches.
     *
     * The answers are different in kind and a customer can act on only some of
     * them: this template names no catalogue anyone can search, we cannot tell
     * what this server runs, or the catalogue picked is not answering.
     *
     * @return array{ok:bool,note:?string,tone:string,title:?string}
     */
    private function catalogueState(ModTarget $target, ?ModSource $source): array
    {
        if ($target->loader === null) {
            return [
                'ok' => false,
                'tone' => 'warn',
                'title' => 'Mod Loader Unknown',
                'note' => 'GameMGR cannot tell which mod loader this server runs, so it will not offer files it cannot place. Set the server type on the Startup tab and this screen starts working.',
            ];
        }

        if ($source === null) {
            return [
                'ok' => false,
                'tone' => 'info',
                'title' => 'No Searchable Catalogue',
                'note' => 'Nothing this template lists can be searched from here, so mods have to be uploaded through the file manager. Installed mods are still managed on this page.',
            ];
        }

        if ($source->degraded()) {
            return [
                'ok' => false,
                'tone' => 'warn',
                'title' => $source->label().' Unavailable',
                'note' => $source->label().' is not answering right now, so it cannot be searched. Everything already installed is listed below and still works.',
            ];
        }

        return ['ok' => true, 'tone' => 'info', 'title' => null, 'note' => null];
    }

    /**
     * Which catalogue the browse screen is showing.
     *
     * A tab the server cannot use is not selectable, so an unknown or unusable
     * key falls back to the first one that works rather than erroring: a stale
     * bookmark should show a working screen, not a refusal.
     *
     * @param  array<int,ModSource>  $usable
     */
    private function pickSource(Request $request, array $usable): ?ModSource
    {
        $wanted = (string) $request->query('source', '');

        foreach ($usable as $source) {
            if ($source->key() === $wanted) {
                return $source;
            }
        }

        return $usable[0] ?? null;
    }

    /** Did any catalogue this server uses fail recently? */
    private function anyDegraded(ModTarget $target): bool
    {
        foreach ($this->registry->for($target) as $source) {
            if ($source->degraded()) {
                return true;
            }
        }

        return false;
    }
}
