<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = Auth::user();

        if (!$user || !$user->role) {
            return response()->json([
                'message' => 'Unauthorized - No role assigned',
            ], 403);
        }

        $permissions = $user->role->permissions->pluck('slug')->toArray();

        if (!in_array($permission, $permissions)) {
            return response()->json([
                'message' => 'Unauthorized - Missing required permission',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
