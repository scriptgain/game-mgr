<?php

namespace App\Http\Controllers\Api\Client;

use App\Models\AuditLog;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The variables a server was built with.
 *
 * Only what the template marks visible is returned, and only what it marks
 * editable can be changed. A variable holding a licence key or a server token
 * is admin-only for a reason, and the API has to honour that as the web screen
 * does rather than handing it over because it was asked politely.
 */
class StartupController extends ServerApiController
{
    public function index(Server $server)
    {
        $this->guard($server, 'startup.read');

        $isAdmin = auth()->user()->isAdmin();

        $variables = $server->template->variables
            ->filter(fn ($v) => $isAdmin || $v->user_viewable)
            ->map(fn ($v) => [
                'name' => $v->name,
                'description' => $v->description,
                'env_variable' => $v->env_variable,
                'default_value' => $v->default_value,
                'rules' => $v->rules,
                'editable' => (bool) $v->user_editable,
                'value' => $server->variables->firstWhere('template_variable_id', $v->id)?->value ?? $v->default_value,
            ])->values();

        return [
            'object' => 'startup',
            'attributes' => [
                'startup' => $server->startup,
                'image' => $server->image,
                'variables' => $variables,
            ],
        ];
    }

    public function update(Request $request, Server $server)
    {
        $this->guard($server, 'startup.update');

        $submitted = (array) $request->input('variables', []);
        $changed = 0;

        foreach ($server->template->variables as $variable) {
            if (! array_key_exists($variable->env_variable, $submitted)) {
                continue;
            }
            // Not merely skipped: asking to change one of these is asking for
            // something the panel does not permit, and silence would look like
            // it worked.
            if (! $variable->user_editable || ! $variable->user_viewable) {
                return response()->json([
                    'message' => $variable->env_variable.' cannot be changed on this template.',
                ], 403);
            }

            $value = (string) $submitted[$variable->env_variable];
            $check = Validator::make(['v' => $value], ['v' => explode('|', (string) $variable->rules)]);
            if ($check->fails()) {
                return response()->json([
                    'message' => $variable->env_variable.': '.$check->errors()->first('v'),
                ], 422);
            }

            $server->variables()->updateOrCreate(
                ['template_variable_id' => $variable->id],
                ['value' => $value],
            );
            $changed++;
        }

        AuditLog::record('startup.update', 'Changed '.$changed.' variable(s) on "'.$server->name.'" over the API', $server, $server->id);

        return response()->json([
            'object' => 'startup',
            'attributes' => ['changed' => $changed],
            'meta' => ['note' => 'The server has to restart before these apply.'],
        ]);
    }
}
