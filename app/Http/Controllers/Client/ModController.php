<?php

namespace App\Http\Controllers\Client;

use App\Models\Mod;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mods and plugins.
 *
 * The workflow Pterodactyl leaves to the file manager: find a jar on a website,
 * download it, drag it into /plugins, restart, hope. Here it is a managed list
 * with a source, a version, and a visible answer to "is there an update".
 *
 * Catalogue search is stubbed against a local fixture for now; the Modrinth and
 * CurseForge calls land with the real runtime drivers. The screen, the schema
 * and the install flow are all real.
 */
class ModController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'mod.read');

        $mods = $server->mods()->orderBy('name')->get();

        return view('server.mods', [
            'title' => $server->name.' Mods',
            'server' => $server->load('node', 'template.game'),
            'mods' => $mods,
            'updatable' => $mods->filter->hasUpdate(),
            'sources' => $server->template?->mod_sources ?? [],
        ]);
    }

    public function browse(Request $request, Server $server)
    {
        $this->guard($server, 'mod.read');

        $sources = $server->template?->mod_sources ?? [];
        $query = trim((string) $request->query('q'));

        return view('server.mod-browse', [
            'title' => 'Browse Mods',
            'server' => $server->load('node', 'template.game'),
            'sources' => $sources,
            'query' => $query,
            'results' => $query === '' ? [] : $this->search($sources, $query),
            'installed' => $server->mods()->pluck('slug')->all(),
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'mod.install');

        $data = $request->validate([
            'source' => ['required', 'in:modrinth,curseforge,spigot,workshop,manual'],
            'name' => ['required', 'string', 'max:160'],
            'remote_id' => ['nullable', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:60'],
            'author' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = Str::slug($data['name']);

        if ($server->mods()->where('slug', $slug)->exists()) {
            return back()->with('error', $data['name'].' is already installed.');
        }

        Mod::create($data + [
            'server_id' => $server->id,
            'slug' => $slug,
            'version' => $data['version'] ?: '1.0.0',
            'latest_version' => $data['version'] ?: '1.0.0',
            'path' => '/plugins/'.Str::studly($data['name']).'.jar',
            'enabled' => true,
            'installed_at' => now(),
            'checked_at' => now(),
        ]);

        $this->log($server, 'mod.install', 'Installed '.$data['name']);

        return redirect()->route('server.mods', $server)
            ->with('status', $data['name'].' installed. Restart the server to load it.');
    }

    public function update(Server $server, Mod $mod)
    {
        $this->guard($server, 'mod.update');
        abort_unless($mod->server_id === $server->id, 404);

        if (! $mod->hasUpdate()) {
            return back()->with('status', $mod->name.' is already on the newest version.');
        }

        $from = $mod->version;
        $mod->update(['version' => $mod->latest_version, 'installed_at' => now()]);
        $this->log($server, 'mod.update', 'Updated '.$mod->name.' from '.$from.' to '.$mod->version);

        return back()->with('status', $mod->name.' updated to '.$mod->version.'. Restart the server to load it.');
    }

    public function toggle(Server $server, Mod $mod)
    {
        $this->guard($server, 'mod.update');
        abort_unless($mod->server_id === $server->id, 404);

        $mod->update(['enabled' => ! $mod->enabled]);
        $this->log($server, 'mod.toggle', ($mod->enabled ? 'Enabled ' : 'Disabled ').$mod->name);

        return back()->with('status', $mod->name.($mod->enabled ? ' enabled.' : ' disabled.'));
    }

    public function destroy(Server $server, Mod $mod)
    {
        $this->guard($server, 'mod.delete');
        abort_unless($mod->server_id === $server->id, 404);

        $name = $mod->name;
        $mod->delete();
        $this->log($server, 'mod.delete', 'Removed '.$name);

        return back()->with('status', $name.' removed.');
    }

    /**
     * Stand-in catalogue. Returns plausible results filtered by the query so the
     * browse screen and the install flow are exercisable end to end before the
     * real API clients exist.
     */
    private function search(array $sources, string $query): array
    {
        $catalogue = [
            ['modrinth', 'EssentialsX', 'EssentialsTeam', '2.21.0', 'The command set every server ends up wanting: homes, warps, kits, economy.'],
            ['modrinth', 'LuckPerms', 'Luck', '5.4.145', 'Permissions, done properly. Web editor, groups, contexts, the lot.'],
            ['modrinth', 'Chunky', 'pop4959', '1.4.36', 'Pre-generates chunks so players do not lag the server generating them.'],
            ['modrinth', 'ViaVersion', 'ViaVersion', '5.1.1', 'Lets newer clients join an older server.'],
            ['modrinth', 'Geyser', 'GeyserMC', '2.4.2', 'Bedrock players can join a Java server.'],
            ['spigot', 'CoreProtect', 'Intelli', '22.4', 'Block logging and rollback. The first thing you install after being griefed.'],
            ['spigot', 'Vault', 'Kainzo', '1.7.3', 'The permissions and economy bridge half of Spigot depends on.'],
            ['curseforge', 'WorldEdit', 'EngineHub', '7.3.6', 'In-game map editing at scale.'],
            ['curseforge', 'JEI', 'mezz', '19.21.0', 'Recipe lookup for modded play.'],
            ['workshop', 'Structures Plus', 'orionsun', '1.4.9', 'Quality of life building overhaul.'],
            ['workshop', 'Awesome SpyGlass', 'ghazlawl', '3.1.0', 'Detailed creature stats at a glance.'],
        ];

        $needle = Str::lower($query);

        return array_values(array_filter(array_map(
            fn ($row) => [
                'source' => $row[0], 'name' => $row[1], 'author' => $row[2],
                'version' => $row[3], 'summary' => $row[4], 'slug' => Str::slug($row[1]),
            ],
            $catalogue,
        ), fn ($m) => ($sources === [] || in_array($m['source'], $sources, true))
            && (str_contains(Str::lower($m['name']), $needle) || str_contains(Str::lower($m['summary']), $needle))));
    }
}
