<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MerchantAdminSecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if (!$response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (!$response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        return $response;
    }
}
