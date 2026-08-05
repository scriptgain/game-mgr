<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Models\StatusPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingsController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'control.console');

        return view('server.settings', [
            'title' => $server->name.' Settings',
            'server' => $server->load('node.location', 'template.game', 'allocation', 'statusPage'),
            'statusPage' => $server->statusPage ?? new StatusPage(['slug' => Str::slug($server->name), 'show_players' => true, 'show_address' => true, 'show_uptime' => true, 'show_version' => true]),
        ]);
    }

    public function update(Request $request, Server $server)
    {
        $this->guard($server, 'settings.rename');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'auto_restart' => ['nullable', 'boolean'],
            'auto_update' => ['nullable', 'boolean'],
        ]);

        $server->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'auto_restart' => (bool) ($data['auto_restart'] ?? false),
            'auto_update' => (bool) ($data['auto_update'] ?? false),
        ]);

        $this->log($server, 'settings.update', 'Updated server settings');

        return back()->with('status', 'Settings saved.');
    }

    public function reinstall(Server $server)
    {
        $this->guard($server, 'settings.reinstall');

        if ($server->power_state === 'running') {
            return back()->with('error', 'Stop the server before reinstalling it.');
        }

        $server->update(['status' => 'installing', 'installed_at' => null]);
        $this->log($server, 'server.reinstall', 'Started a reinstall');

        return back()->with('status', 'Reinstall started. Server files are replaced; your world and configuration are kept.');
    }

    public function statusPage(Request $request, Server $server)
    {
        $this->guard($server, 'settings.rename');

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique:status_pages,slug,'.($server->statusPage?->id ?? 'NULL')],
            'headline' => ['nullable', 'string', 'max:120'],
            'is_public' => ['nullable', 'boolean'],
            'show_players' => ['nullable', 'boolean'],
            'show_address' => ['nullable', 'boolean'],
            'show_uptime' => ['nullable', 'boolean'],
            'show_version' => ['nullable', 'boolean'],
        ]);

        foreach (['is_public', 'show_players', 'show_address', 'show_uptime', 'show_version'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        StatusPage::updateOrCreate(['server_id' => $server->id], $data);

        $this->log($server, 'status-page.update', 'Updated the public status page');

        return back()->with('status', 'Status page saved.');
    }
}
