<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckOrigin
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = [
            'http://localhost:3000',
    'http://localhost:8000',
    'https://vi-santé.com',
    'https://api.vi-santé.com',
    'https://xn--vi-sant-hya.com',
    'https://api.xn--vi-sant-hya.com',
    // Add these for direct API route access if needed:
    'https://vi-santé.com/api/medecins',
    'https://vi-santé.com/api/appointments',
    'https://vi-santé.com/api/organisations',
    'https://api.vi-sant-hya.com/api/medecins',
    'https://api.vi-sant-hya.com/api/appointments',
    'https://api.vi-sant-hya.com/api/organisations',
        ];

        $origin = $request->headers->get('Origin');
        if ($origin && !in_array($origin, $allowed)) {
            return response()->json(['error' => 'Unauthorized origin'], 403);
        }

        return $next($request);
    }
}
