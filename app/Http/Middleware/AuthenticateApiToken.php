<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a caller by their API token.
 *
 * The scope a route requires is declared on the route, as api.token:application
 * or api.token:client, rather than inferred from the path. Inferring it from
 * the URL means the day somebody moves a route the authorisation quietly moves
 * with it, and the two scopes exist precisely so a customer's own token cannot
 * list every server on the panel.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = ApiToken::findByPlaintext($request->bearerToken());

        if (! $token) {
            // One answer for absent, unknown and expired. Distinguishing them
            // tells an unauthenticated stranger which tokens exist.
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Behind a proxy this is only as good as the trusted proxy config, and
        // an address restriction that silently checks the wrong address is
        // worse than none, so it is worth confirming that is set on any install
        // that relies on this.
        if (! $token->allowsAddress($request->ip())) {
            return response()->json([
                'message' => 'That token is not permitted from this address.',
            ], 403);
        }

        if ($scope !== null && $token->scope !== $scope) {
            return response()->json([
                'message' => 'That token is a '.$token->scopeLabel().' token, and this endpoint needs a '.ucfirst($scope).' one.',
            ], 403);
        }

        // The application API is an edition feature; the client API is not,
        // because it is scoped to servers its owner can already reach and
        // gating it would gate people out of their own servers.
        if ($token->scope === 'application' && ! Edition::allows('api')) {
            $needs = Edition::cheapestWith('api');

            return response()->json([
                'message' => 'The application API is not included in the '.Edition::label().' edition.'
                    .($needs ? ' It is included from '.Edition::label($needs).' upwards.' : ''),
            ], 403);
        }

        if ($token->user === null || $token->user->suspended) {
            return response()->json(['message' => 'That account is not active.'], 403);
        }

        $token->touchUsage();
        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
