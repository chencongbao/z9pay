<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AgentAdminNoStoreCache
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        if (trim($request->path(), '/') === $prefix . '/auth/logout') {
            $response->headers->set('Clear-Site-Data', '"cache"');
        }

        return $response;
    }
}
