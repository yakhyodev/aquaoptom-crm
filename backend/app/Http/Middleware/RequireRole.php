<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->route('login');
        }

        if ($user->isOwner()) {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sizda ushbu amalni bajarish uchun yetarli rol huquqi mavjud emas.',
                ], 403);
            }

            abort(403, 'Sizda ushbu sahifaga kirish uchun yetarli rol huquqi mavjud emas.');
        }

        return $next($request);
    }
}
