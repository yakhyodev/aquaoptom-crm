<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
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

        if (! $user->hasPermission($permission)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Sizda ushbu operatsiyani bajarish uchun ruxsat yo'q: [{$permission}].",
                ], 403);
            }

            abort(403, "Sizda ushbu sahifaga kirish uchun ruxsat yo'q: [{$permission}].");
        }

        return $next($request);
    }
}
