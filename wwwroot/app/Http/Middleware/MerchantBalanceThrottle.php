<?php

namespace App\Http\Middleware;

use App\Traits\ResponseTraits;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class MerchantBalanceThrottle
{
    use ResponseTraits;

    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 2;

    public function handle(Request $request, Closure $next)
    {
        $mid = $request->attributes->get('merchant_user_id');
        $key = 'merchant-balance:' . ($mid ?: $request->ip());

        $limiter = app(RateLimiter::class);
        if ($limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return $this->error('请求过于频繁，请稍后再试');
        }

        $limiter->hit($key, self::DECAY_SECONDS);

        return $next($request);
    }
}
