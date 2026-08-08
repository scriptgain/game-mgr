<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Models\ServerVariable;
use App\Services\NodeClient;
use App\Support\ConfigFile;
use App\Support\ConfigSetting;
use Illuminate\Http\Request;

/**
 * The Config tab: a real form over the game's own config files.
 *
 * Everything a game actually reads lives in a file in its own format, and up
 * to now the only way to change one was to open the file manager and edit
 * text. That is what every other panel makes people do. A template declares a
 * config schema, and this turns it into the same controls the Startup tab uses:
 * a switch for a boolean, a segmented choice for a fixed list, a bounded slider
 * for a number, a secret with a Generate button for a password.
 *
 * Values are never cached in the database. The file on the node is the truth,
 * so the tab reads it on open and writes it on save, and a change made by hand
 * in the file manager shows up here immediately rather than being overwritten
 * by a stale copy.
 */
class ConfigController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'config.read');

        $server->load(['template.variables', 'node']);
        $files = $this->schema($server);

        abort_if($files === [], 404, 'This game has no configuration the panel knows how to edit.');

        $client = NodeClient::for($server->node);
        $state = [];

        foreach ($files as $file) {
            $raw = $client->readFile($server, $file->path);
            $parser = $file->parser();

            $state[$file->id] = [
                'exists' => $raw !== null,
                // A game writes its config on first boot, so before that there
                // is nothing to edit. Saying so beats showing a full form that
                // would quietly create a file the game did not write.
                'values' => $raw !== null && $parser ? $parser->parse($raw) : [],
                'supported' => $parser !== null,
            ];
        }

        return view('server.config', [
            'title' => $server->name.' Config',
            'server' => $server,
            'files' => $files,
            'state' => $state,
            'isAdmin' => auth()->user()->isAdmin(),
            'canEdit' => auth()->user()->can('check', [$server, 'config.update']),
            'canRestart' => auth()->user()->can('check', [$server, 'control.restart']),
        ]);
    }

    public function update(Request $request, Server $server)
    {
        $this->guard($server, 'config.update');

        $server->load('template.variables');
        $files = $this->schema($server);
        abort_if($files === [], 404, 'This game has no configuration the panel knows how to edit.');

        $isAdmin = auth()->user()->isAdmin();
        $submitted = (array) $request->input('settings', []);

        // Validate the whole form before a single byte is written. A save that
        // updates two files and then rejects the third leaves a server running
        // on half of what somebody asked for.
        [$rules, $names, $wanted] = $this->rulesFor($files, $submitted, $isAdmin);
        $request->validate($rules, [], $names);

        $client = NodeClient::for($server->node);
        $written = 0;
        $missing = [];
        $skipped = [];
        $envUpdates = [];

        foreach ($files as $file) {
            $changes = $wanted[$file->id] ?? [];
            if ($changes === []) {
                continue;
            }

            $parser = $file->parser();
            $raw = $parser ? $client->readFile($server, $file->path) : null;

            if ($raw === null || $parser === null) {
                // Never write a file we could not read: applying changes to an
                // empty string would replace the operator's file with four
                // lines and nothing else.
                $missing[] = $file->label;

                continue;
            }

            $current = $parser->parse($raw);
            $apply = [];

            foreach ($changes as $setting) {
                /** @var ConfigSetting $definition */
                $definition = $setting['setting'];
                $value = $setting['value'];
                $existing = $current[$definition->key()] ?? null;

                if ($existing !== null && $this->same($existing, $value)) {
                    continue;
                }

                $apply[$definition->key()] = $value;

                if ($definition->env !== null) {
                    $envUpdates[$definition->env] = $value;
                }
            }

            if ($apply === []) {
                continue;
            }

            $fileSkipped = [];
            $next = $parser->apply($raw, $apply, $fileSkipped);

            foreach ($fileSkipped as $key) {
                $skipped[] = $file->label.': '.$key;
            }

            if ($next === $raw) {
                continue;
            }

            if (! $client->writeFile($server, $file->path, $next)) {
                return back()->with('error', 'The node refused to write '.$file->label.'. Nothing was changed.');
            }

            $written++;
            $this->log($server, 'config.update', 'Edited '.$file->path, ['keys' => array_keys($apply)]);
        }

        // Several templates rewrite their own config from the environment on
        // every boot, so a setting that names a variable writes the variable
        // too. Without this the customer's change would be correct in the file
        // and then undone by the very restart that was supposed to apply it.
        $this->syncVariables($server, $envUpdates);

        if ($written > 0) {
            $server->forceFill(['config_dirty_at' => now()])->save();
        }

        $message = match (true) {
            $written === 0 && $missing === [] => 'Nothing to change: the file already says that.',
            $written === 0 => 'Nothing was written. '.implode(', ', $missing).' does not exist yet.',
            default => 'Saved. '.($server->power_state === 'offline'
                ? 'It takes effect the next time this server starts.'
                : 'This server is running, and the game only reads these files at boot, so restart it before anything here is live.'),
        };

        if ($skipped !== []) {
            $message .= ' Could not place: '.implode(', ', $skipped).'.';
        }

        return redirect()->route('server.config', $server)->with($written > 0 ? 'status' : 'error', $message);
    }

    /** @return array<int,ConfigFile> */
    private function schema(Server $server): array
    {
        return $server->template?->configFiles() ?? [];
    }

    /**
     * Rules, field names and the settings actually being changed.
     *
     * A setting this user may not edit is dropped here rather than validated
     * and then ignored, which is how people become convinced a panel is broken.
     *
     * @param  array<int,ConfigFile>  $files
     * @return array{0:array<string,array>,1:array<string,string>,2:array<string,array>}
     */
    private function rulesFor(array $files, array $submitted, bool $isAdmin): array
    {
        $rules = [];
        $names = [];
        $wanted = [];

        foreach ($files as $file) {
            foreach ($file->settings as $setting) {
                if (! $isAdmin && (! $setting->user_viewable || ! $setting->user_editable)) {
                    continue;
                }
                if (! array_key_exists($setting->id, $submitted)) {
                    continue;
                }

                $rules['settings.'.$setting->id] = $setting->ruleArray();
                $names['settings.'.$setting->id] = $setting->name;
                $wanted[$file->id][] = [
                    'setting' => $setting,
                    'value' => (string) ($submitted[$setting->id] ?? ''),
                ];
            }
        }

        return [$rules, $names, $wanted];
    }

    /**
     * Is the value in the file the same as the one posted?
     *
     * Compared as numbers when both are numbers, because Palworld writes every
     * rate as "1.000000" and a form posts "1". A plain string compare would
     * rewrite every rate on the server on every save, which is a needless diff
     * on a file where every rewrite carries risk.
     */
    private function same(string $existing, string $value): bool
    {
        if (is_numeric($existing) && is_numeric($value)) {
            return abs(($existing + 0) - ($value + 0)) < 0.0000005;
        }

        return $existing === $value;
    }

    /** @param  array<string,string>  $updates  env variable name => value */
    private function syncVariables(Server $server, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        foreach ($server->template->variables as $variable) {
            if (! array_key_exists($variable->env_variable, $updates)) {
                continue;
            }

            ServerVariable::updateOrCreate(
                ['server_id' => $server->id, 'template_variable_id' => $variable->id],
                ['value' => $updates[$variable->env_variable]],
            );
        }
    }
}
