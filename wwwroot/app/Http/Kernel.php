<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class
        ],

        'api' => [
            \App\Http\Middleware\RequestPerformanceMiddleware::class,
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnforceApiHost::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        "checkapi" => \App\Http\Middleware\CheckApiKey::class,
        "merchant.balance.throttle" => \App\Http\Middleware\MerchantBalanceThrottle::class,
        'set.lang' => \App\Http\Middleware\SetLang::class,
        "merchant.config" => \App\Http\Middleware\ResetConfig::class,
        "check.admin.user.status" => \App\Http\Middleware\CheckAdminUserStatus::class,
        "check.merchant.user.status" => \App\Http\Middleware\CheckMerchantUserStatus::class,
        "check.agent.user.status" => \App\Http\Middleware\CheckAgentUserStatus::class,
        'normalize.agent.grid.pagination' => \App\Http\Middleware\NormalizeAgentGridPagination::class,
        'normalize.merchant.grid.pagination' => \App\Http\Middleware\NormalizeMerchantGridPagination::class,
        'normalize.agent.grid.query' => \App\Http\Middleware\NormalizeAgentGridQuery::class,
        'normalize.merchant.grid.query' => \App\Http\Middleware\NormalizeMerchantGridQuery::class,
        'agent.admin.no-store' => \App\Http\Middleware\AgentAdminNoStoreCache::class,
        'merchant.admin.security.headers' => \App\Http\Middleware\MerchantAdminSecurityHeaders::class,
        "check.domain" => \App\Http\Middleware\EnforceApiHost::class,
        'force.change.password' => \App\Http\Middleware\ForceChangePassword::class,
        'system.log' => \App\Http\Middleware\SystemLogMiddleware::class,
        "check.user.status" => \App\Http\Middleware\CheckUserStatus::class,
        "allow.v4.ip" => \App\Http\Middleware\AllowV4FixedIp::class,
    ];
}
