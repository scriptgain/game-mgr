<?php

namespace App\Http\Controllers\Client;

use App\Models\Mod;
use App\Models\Server;
use App\Services\Mods\ModInstaller;
use App\Services\Mods\ModTarget;
use App\Services\Mods\Modrinth;
use Illuminate\Http\Request;

/**
 * Mods and plugins.
 *
 * The workflow Pterodactyl leaves to the file manager: find a jar on a website,
 * download it, drag it into /plugins, restart, hope. Here it is a managed list
 * with a source, a version, and a visible answer to "is there an update".
 *
 * The catalogue is Modrinth, live, through App\Services\Mods\Modrinth. Search
 * is narrowed to the loader and Minecraft version this server actually runs,
 * install downloads the file and verifies the checksum Modrinth published
 * before it reaches the node, disable renames to .disabled rather than
 * deleting, and remove deletes both the file and the row.
 *
 * CurseForge and SpigotMC are still declared on templates and still listed as
 * sources, but neither has a client yet: CurseForge needs an API key per
 * install and SpigotMC has no official API at all. The browse screen says so
 * rather than pretending to search them.
 *
 * DEGRADING. Modrinth is a third party and will one day be slow, rate limiting
 * or down. None of that may break this page. Every catalogue call answers null
 * instead of throwing, the installed list renders from the database and never
 * from the network, and an unavailable catalogue becomes a note at the top of
 * the screen. Nothing in this controller can 500 because Modrinth had a bad
 * afternoon.
 */
class ModController extends ServerController
{
    public function __construct(
        private readonly Modrinth $modrinth,
        private readonly ModInstaller $installer,
    ) {}

    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'mod.read');

        $server->load('node', 'template.game', 'template.variables');
        $target = ModTarget::for($server);

        // Checking every installed mod against the API is a handful of requests
        // and belongs behind a deliberate click, not on every page load. It is
        // a GET because it changes nothing a customer owns: it only refreshes
        // what the panel believes the newest version is.
        if ($request->boolean('refresh')) {
            $this->guard($server, 'mod.update');

            $waiting = $this->installer->refresh($server, $target);

            return redirect()->route('server.mods', $server)->with(
                'status',
                $this->modrinth->degraded()
                    ? 'Modrinth did not answer, so the version list was left as it was.'
                    : ($waiting === 0
                        ? 'Every mod is on the newest version this server can run.'
                        : $waiting.' '.str('mod')->plural($waiting).' can be updated.'),
            );
        }

        $mods = $server->mods()->orderBy('name')->get();

        return view('server.mods', [
            'title' => $server->name.' Mods',
            'server' => $server,
            'mods' => $mods,
            'updatable' => $mods->filter->hasUpdate(),
            'sources' => $target->sources,
            'target' => $target,
            'catalogue' => $this->catalogueState($target),
        ]);
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
        $catalogue = $this->catalogueState($target);

        // null and [] mean different things and the view shows different
        // screens for them: null is "Modrinth did not answer", [] is "Modrinth
        // answered and there is nothing".
        $results = $query === '' || ! $catalogue['ok']
            ? []
            : $this->modrinth->search($query, $target);

        return view('server.mod-browse', [
            'title' => 'Browse Mods',
            'server' => $server,
            'sources' => $target->sources,
            'target' => $target,
            'query' => $query,
            'results' => $results,
            'catalogue' => $catalogue,
            'installed' => $server->mods()->pluck('remote_id')->filter()->all(),
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'mod.install');

        $data = $request->validate([
            // A Modrinth project id or slug. Nothing else is accepted: the name,
            // author and summary all come back from the API, so a forged form
            // cannot invent a mod that is not really there.
            'project' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9!@$()`.+,_"\-]+$/'],
        ], [
            'project.regex' => 'That is not a Modrinth project id.',
        ]);

        $server->load('node', 'template.game', 'template.variables');
        $result = $this->installer->install($server, ModTarget::for($server), $data['project']);

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
     * What the screen should say about the catalogue before anyone searches.
     *
     * Four honest answers, and the difference between them matters to whoever
     * is looking at it: this template has no Modrinth, we cannot tell what this
     * server runs, Modrinth is not answering, or everything is fine.
     *
     * @return array{ok:bool,note:?string,tone:string,title:?string}
     */
    private function catalogueState(ModTarget $target): array
    {
        if (! $target->usesModrinth()) {
            return [
                'ok' => false,
                'tone' => 'info',
                'title' => 'No Searchable Catalogue',
                'note' => 'This template does not list Modrinth as a mod source. Installed mods are still managed here, but there is nothing to search.',
            ];
        }

        if (! $this->modrinth->enabled()) {
            return [
                'ok' => false,
                'tone' => 'info',
                'title' => 'Catalogue Switched Off',
                'note' => 'The Modrinth catalogue is switched off on this panel, so mods have to be uploaded through the file manager.',
            ];
        }

        if ($target->loader === null) {
            return [
                'ok' => false,
                'tone' => 'warn',
                'title' => 'Mod Loader Unknown',
                'note' => 'GameMGR cannot tell which mod loader this server runs, so it will not offer files it cannot place. Set the server type on the Startup tab and this screen starts working.',
            ];
        }

        if ($this->modrinth->degraded()) {
            return [
                'ok' => false,
                'tone' => 'warn',
                'title' => 'Catalogue Unavailable',
                'note' => 'Modrinth is not answering right now, so the catalogue cannot be searched. Everything already installed is listed below and still works.',
            ];
        }

        return ['ok' => true, 'tone' => 'info', 'title' => null, 'note' => null];
    }
}
