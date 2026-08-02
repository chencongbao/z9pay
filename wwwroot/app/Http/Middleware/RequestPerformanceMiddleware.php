<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestPerformanceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.debug')) {
            return $next($request);
        }

        $start = microtime(true);
        $response = $next($request);
        $durationMs = round((microtime(true) - $start) * 1000, 2);
        $statusCode = $response->getStatusCode();
        $memoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $mid = $request->input('mid') ?: $request->attributes->get('merchant_user_id');

        Log::channel('request_performance')->info(PHP_EOL . implode(PHP_EOL, [
            '========== 接口性能监控 ==========',
            '请求方式：' . $request->method(),
            '请求路径：' . $request->path(),
            '路由名称：' . (optional($request->route())->getName() ?: '-'),
            '客户端IP：' . $request->ip(),
            '商户ID：' . ($mid ?: '-'),
            '响应状态：' . $statusCode,
            '请求耗时：' . $durationMs . ' ms',
            '峰值内存：' . $memoryMb . ' MB',
            '请求时间：' . now()->format('Y-m-d H:i:s'),
            '==================================',
        ]));

        return $response;
    }
}
