<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Models\ServerVariable;
use Illuminate\Http\Request;

/**
 * The Startup tab: the template variables a client is allowed to change, plus
 * the docker image and startup line for admins.
 */
class StartupController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'startup.read');

        $server->load(['template.variables', 'variables.variable', 'node']);

        // Only variables the template marks user_viewable reach a client. An
        // admin sees everything, including the ones holding a licence key or a
        // server token that a client has no business reading.
        $isAdmin = auth()->user()->isAdmin();
        $variables = $server->template->variables
            ->filter(fn ($v) => $isAdmin || $v->user_viewable)
            ->values();

        $values = $server->variables->mapWithKeys(
            fn ($sv) => [$sv->template_variable_id => $sv->value]
        );

        return view('server.startup', [
            'title' => $server->name.' Startup',
            'server' => $server,
            'variables' => $variables,
            'values' => $values,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function update(Request $request, Server $server)
    {
        $this->guard($server, 'startup.update');

        $server->load('template.variables');
        $isAdmin = auth()->user()->isAdmin();
        $submitted = (array) $request->input('variables', []);

        $rules = [];
        $payload = [];

        foreach ($server->template->variables as $var) {
            // A variable the client cannot edit is skipped entirely rather than
            // validated and ignored: accepting the field and silently dropping
            // it is how people end up convinced the panel is broken.
            if (! $isAdmin && ! $var->user_editable) {
                continue;
            }
            if (! array_key_exists($var->id, $submitted)) {
                continue;
            }
            $rules['variables.'.$var->id] = $var->ruleArray();
            $payload[$var->id] = $submitted[$var->id];
        }

        $request->validate($rules, [], collect($server->template->variables)
            ->mapWithKeys(fn ($v) => ['variables.'.$v->id => $v->name])->all());

        foreach ($payload as $variableId => $value) {
            ServerVariable::updateOrCreate(
                ['server_id' => $server->id, 'template_variable_id' => $variableId],
                ['value' => $value],
            );
        }

        if ($isAdmin) {
            $extra = $request->validate([
                'image' => ['nullable', 'string', 'max:255'],
                'startup' => ['nullable', 'string', 'max:2000'],
            ]);
            $server->update(array_filter($extra, fn ($v) => filled($v)));
        }

        $this->log($server, 'startup.update', 'Changed startup variables');

        return back()->with('status', 'Saved. Restart the server for the changes to take effect.');
    }
}
