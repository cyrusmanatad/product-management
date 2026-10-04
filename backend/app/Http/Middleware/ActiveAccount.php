<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->fresh()?->is_active, 403, 'Account is disabled.');
        if ($request->bearerToken()) {
            abort_unless((int) auth('api')->payload()->get('ver', 0) === (int) $request->user()->fresh()->token_version, 401, 'Sign in again.');
        }
        if ($request->user()->fresh()->must_reset_password && ! $request->is('api/v1/profile/password', 'api/v1/auth/logout')) {
            return response()->json(['message' => 'Change your password to continue.', 'code' => 'password_reset_required'], 403);
        }

        return $next($request);
    }
}
