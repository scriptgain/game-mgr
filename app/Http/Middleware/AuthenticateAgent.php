<?php

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a node daemon by its long-lived credential. Only the sha256 of
 * that credential is stored, so this compares hashes rather than secrets.
 */
class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $node = Node::where('daemon_token', hash('sha256', $bearer))->first();
        if (! $node) {
            return response()->json(['message' => 'Invalid node token.'], 401);
        }

        $request->attributes->set('agent_node', $node);

        return $next($request);
    }
}
