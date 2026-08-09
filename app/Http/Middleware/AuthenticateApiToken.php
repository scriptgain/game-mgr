<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = ApiToken::findByPlaintext($bearer);

        if (! $token) {
            return response()->json(['message' => 'Invalid or expired API token.'], 401);
        }

        // The application API is an edition feature. The client API is not: it
        // is scoped to servers the token owner can already reach, and gating it
        // would gate people out of their own servers rather than out of a paid
        // integration surface.
        if ($token->scope === 'application' && ! Edition::allows('api')) {
            $needs = Edition::cheapestWith('api');

            return response()->json([
                'message' => 'The application API is not included in the '.Edition::label().' edition.'
                    .($needs ? ' It is included from '.Edition::label($needs).' upwards.' : ''),
            ], 403);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
