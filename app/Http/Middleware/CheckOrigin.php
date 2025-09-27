<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckOrigin
{
    public function handle(Request $request, Closure $next)
    {
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedHosts = [];
        foreach ($allowedOrigins as $o) {
            $host = parse_url($o, PHP_URL_HOST);
            if ($host) $allowedHosts[] = $host;
        }
        $allowedHosts = array_values(array_unique($allowedHosts));

        $isProduction = app()->environment('production') || (config('app.debug') === false);

        $origin = $request->headers->get('Origin');
        if ($origin) {
            $host = parse_url($origin, PHP_URL_HOST);
            if ($isProduction && $host && !in_array($host, $allowedHosts, true)) {
                return response()->json(['error' => 'Unauthorized origin'], 403);
            }
        } else if ($isProduction) {
            // Fall back to Referer in production when Origin is missing
            $referer = $request->headers->get('Referer');
            if ($referer) {
                $refHost = parse_url($referer, PHP_URL_HOST);
                if ($refHost && !in_array($refHost, $allowedHosts, true)) {
                    return response()->json(['error' => 'Unauthorized origin'], 403);
                }
            } else {
                // No Origin and no Referer in production: block
                return response()->json(['error' => 'Unauthorized origin'], 403);
            }
        }

        return $next($request);
    }
}
