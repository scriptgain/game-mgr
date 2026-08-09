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

        // Validated as well as documented. This took whatever `variables`
        // happened to be, including a string, and only found out further down.
        $submitted = (array) $request->validate(static::rules('update'))['variables'];
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

    /**
     * The request body, in one place so the API reference can describe it.
     *
     * `variables` is a map of the template's own environment names to values,
     * which is why it is an object rather than a listed set of fields: what is
     * accepted depends on the template this server runs. The Startup screen in
     * the panel shows the exact names.
     *
     * @return array<string,mixed>
     */
    public static function rules(string $action = 'update', mixed $subject = null): array
    {
        return match ($action) {
            'update' => [
                'variables' => ['required', 'array'],
            ],
            default => [],
        };
    }

}
