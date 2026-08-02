<?php

namespace App\Http\Middleware;

use App\Services\Common\SystemLogService;
use Closure;
use Illuminate\Http\Request;

class SystemLogMiddleware
{
    public function handle(Request $request, Closure $next, string $appType = 'admin')
    {
        $logType = $this->resolveLogType($request, $appType);
        app(SystemLogService::class)->excute($request, $appType, null, $logType);
        return $next($request);
    }

    private function resolveLogType(Request $request, string $appType): string
    {
        if ($this->isLoginRequest($request, $appType)) {
            return 'login';
        }

        return 'operation';
    }

    private function isLoginRequest(Request $request, string $appType): bool
    {
        $path = trim((string)$request->path(), '/');

        $prefix = match ($appType) {
            'admin' => config('admin.route.prefix'),
            'merchant' => config('merchant-admin.route.prefix'),
            'agent' => config('agent-admin.route.prefix'),
            default => null,
        };

        if (!$prefix) {
            return false;
        }

        $prefix = trim((string)$prefix, '/');

        // Dcat 登录流程路径：{prefix}/auth/login、{prefix}/auth/verify
        return $path === $prefix . '/auth/login' || $path === $prefix . '/auth/verify';
    }
}
