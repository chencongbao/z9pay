<?php

namespace App\Http\Middleware;

use App\Services\Common\DomainConfigService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $domainConfigService = app(DomainConfigService::class);
        $host = $request->getHost();
        $allowed = $domainConfigService->apiAllowedHosts();

        if (!$domainConfigService->isHostAllowed($host, $allowed)) {
            abort(404);
        }

        return $next($request);
    }
}
