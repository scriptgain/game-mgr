<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Mods\ModSourceRegistry;
use Illuminate\Http\Request;

/**
 * The two keys that unlock the two catalogues that need one.
 *
 * Modrinth, Hangar and SpigotMC need nothing at all and are simply on. This
 * page exists for CurseForge and the Steam Workshop, both of which issue keys
 * per application, which a self-hosted panel cannot ship: a key baked into a
 * public release is a key that gets revoked, and it puts every install's
 * traffic under one quota.
 *
 * Both are stored with Setting::putSecret, the same treatment as the Cloudflare
 * token: encrypted at rest, never written to a config file, and never echoed
 * back into the form. A blank field means "keep what is stored", so saving the
 * page for an unrelated reason cannot silently wipe a key.
 */
class ModSettingsController extends Controller
{
    public function __construct(private readonly ModSourceRegistry $registry) {}

    public function edit()
    {
        return view('settings.mods', [
            'sources' => $this->registry->all(),
            'hasCurseForge' => filled(Setting::secret('mods_curseforge_key')),
            'hasSteam' => filled(Setting::secret('mods_steam_key')),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mods_curseforge_key' => ['nullable', 'string', 'max:255'],
            'mods_steam_key' => ['nullable', 'string', 'max:255'],
        ]);

        // Blank keeps the stored value. Only a real string replaces it.
        foreach (['mods_curseforge_key', 'mods_steam_key'] as $key) {
            if (filled($data[$key] ?? null)) {
                Setting::putSecret($key, $data[$key]);
            }
        }

        return redirect()->route('settings.mods.edit')->with('status', 'Catalogue settings saved.');
    }

    public function clear(Request $request, string $which)
    {
        $key = match ($which) {
            'curseforge' => 'mods_curseforge_key',
            'steam' => 'mods_steam_key',
            default => abort(404),
        };

        Setting::putSecret($key, null);

        return redirect()->route('settings.mods.edit')->with('status', 'Key deleted. That catalogue is unavailable until a new one is saved.');
    }
}
