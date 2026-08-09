<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\SubuserResource;
use App\Models\Server;
use App\Models\AuditLog;
use App\Models\Subuser;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

/**
 * Subuser for one server, as its owner sees them.
 *
 * Guarded by user.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class SubuserController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'user.read');

        return $this->paginate($request, $server->subusers(), SubuserResource::class);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'user.create');

        $data = $request->validate(static::rules('store'));

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'No account has that email address.'], 404);
        }
        if ($user->id === $server->owner_id) {
            return response()->json(['message' => 'That account already owns this server.'], 409);
        }

        $subuser = $server->subusers()->updateOrCreate(
            ['user_id' => $user->id],
            ['permissions' => array_values($data['permissions'])],
        );

        AuditLog::record('subuser.create', 'Invited '.$user->email.' to "'.$server->name.'" over the API', $server, $server->id);

        return response()->json($this->one($subuser->fresh(), SubuserResource::class), 201);
    }

    public function update(Request $request, Server $server, $subuser)
    {
        $this->guard($server, 'user.update');

        $data = $request->validate(static::rules('update'));

        $record = $server->subusers()->findOrFail($subuser);
        $record->update(['permissions' => array_values($data['permissions'])]);

        return $this->one($record->fresh(), SubuserResource::class);
    }

    public function destroy(Server $server, $subuser)
    {
        $this->guard($server, 'user.delete');

        $server->subusers()->findOrFail($subuser)->delete();
        AuditLog::record('subuser.delete', 'Removed a subuser from "'.$server->name.'" over the API', $server, $server->id);

        return $this->done();
    }

    /**
     * The request body for each write action, in one place so the API
     * reference can describe it rather than admitting it cannot.
     *
     * Static and public because two callers need it: validation here, and the
     * OpenAPI document, which would otherwise have to parse this file. The
     * subject is the record being acted on, for rules that must ignore it.
     *
     * @return array<string,mixed>
     */
    public static function rules(string $action = 'store', mixed $subject = null): array
    {
        return match ($action) {
            'store' => [
                'email' => ['required', 'email'],
                'permissions' => ['required', 'array'],
                'permissions.*' => ['string', Rule::in(Subuser::allPermissions())],
            ],
            'update' => [
                'permissions' => ['required', 'array'],
                'permissions.*' => ['string', Rule::in(Subuser::allPermissions())],
            ],
            default => [],
        };
    }
}
