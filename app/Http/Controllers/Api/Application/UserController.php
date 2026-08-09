<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Customer accounts. A billing system creates one of these before it can create
 * a server for it, so this is the first call a provisioning module makes.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->query('email'), fn ($q, $email) => $q->where('email', $email))
            ->when($request->query('search'), fn ($q, $t) => $q->where('name', 'like', '%'.$t.'%')->orWhere('email', 'like', '%'.$t.'%'))
            ->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return ApiResource::list($users, UserResource::class);
    }

    public function show(User $user)
    {
        return (new UserResource($user))->toArray(request());
    }

    public function store(Request $request)
    {
        $data = $request->validate(static::rules('store'));

        $user = User::create($data + [
            'role' => $data['role'] ?? 'client',
            'timezone' => $data['timezone'] ?? config('app.timezone'),
        ]);
        $user->forceFill(['password_changed_at' => now()])->save();

        AuditLog::record('user.create', 'Created account "'.$user->email.'" over the API', $user);

        return response()->json((new UserResource($user))->toArray($request), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(static::rules('update', $user));

        // The root admin cannot be suspended or demoted by anything, including
        // this. An install has to keep one account that can always get in.
        if ($user->isRootAdmin()) {
            unset($data['suspended']);
        }

        $user->update(array_filter($data, fn ($v) => $v !== null));
        AuditLog::record('user.update', 'Updated account "'.$user->email.'" over the API', $user);

        return (new UserResource($user->fresh()))->toArray($request);
    }

    /**
     * A single sign-on link, so a billing client area can hand somebody
     * straight into their panel.
     *
     * Signed, short lived and single use. It is a credential in a URL, which is
     * a shape that ends up in browser history, referrer headers and support
     * tickets, so it expires in minutes rather than hours and stops working the
     * moment it is used.
     */
    public function sso(Request $request, User $user)
    {
        if ($user->suspended) {
            return response()->json(['message' => 'That account is suspended.'], 403);
        }

        $url = URL::temporarySignedRoute('sso.consume', now()->addMinutes(3), [
            'user' => $user->id,
            'nonce' => \Illuminate\Support\Str::random(32),
        ]);

        AuditLog::record('user.sso', 'Issued a sign-in link for "'.$user->email.'" over the API', $user);

        return ['object' => 'sso', 'attributes' => ['url' => $url, 'expires_in' => 180]];
    }

    public function destroy(User $user)
    {
        if ($user->isRootAdmin()) {
            return response()->json(['message' => 'The root administrator cannot be deleted.'], 422);
        }
        if ($user->servers()->exists()) {
            // Deliberately refused rather than cascading. Deleting an account
            // that still owns servers would orphan running games with no owner
            // and no way to reach them.
            return response()->json([
                'message' => 'That account still owns servers. Terminate or reassign them first.',
                'servers' => $user->servers()->count(),
            ], 409);
        }

        $email = $user->email;
        $user->delete();
        AuditLog::record('user.delete', 'Deleted account "'.$email.'" over the API');

        return response()->json(null, 204);
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
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'username' => ['nullable', 'string', 'min:3', 'max:48', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:users,username'],
                'password' => ['required', Password::min(8)],
                'role' => ['nullable', Rule::in(['admin', 'client'])],
                'timezone' => ['nullable', 'string', 'max:64'],
            ],
            'update' => [
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($subject?->id)],
                'username' => ['nullable', 'string', 'min:3', 'max:48', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('users', 'username')->ignore($subject?->id)],
                'password' => ['nullable', Password::min(8)],
                'suspended' => ['nullable', 'boolean'],
            ],
            default => [],
        };
    }
}
