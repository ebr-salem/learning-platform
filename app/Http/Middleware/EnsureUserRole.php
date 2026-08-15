<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->role?->value, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: insufficient permissions.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
