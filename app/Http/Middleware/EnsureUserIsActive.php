<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->is_blocked) {
            $request->user()->tokens()->delete();
            return response()->json(['message' => 'Учётная запись заблокирована.'], 401)->withoutCookie('auth_token');
        }
        return $next($request);
    }
}
