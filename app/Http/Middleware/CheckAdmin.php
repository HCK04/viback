<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     * 
     * Ensures only users with 'admin' role can access protected routes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Load role if not already loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Check if user has admin role
        $roleName = $user->role->name ?? null;
        
        if ($roleName !== 'admin') {
            \Log::warning('Unauthorized admin access attempt', [
                'user_id' => $user->id,
                'role' => $roleName,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            
            return response()->json(['error' => 'Forbidden: Admin access required'], 403);
        }

        return $next($request);
    }
}
