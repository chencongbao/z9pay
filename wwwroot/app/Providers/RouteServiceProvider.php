<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Merchant\GetMerchantApiOrderLimitService;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {

        RateLimiter::for('api', function (Request $request) {
            $mid = $request->input('mid');
            if (is_scalar($mid) && $mid !== '') {
                $mid = (string) $mid;
                $routeName = optional($request->route())->getName();
                if ($routeName == "api.v3.deposits") {
                    $limit = App::make(GetMerchantApiOrderLimitService::class)->excute($mid, 1);
                    return Limit::perMinute($limit ?: 1000)->by($mid);
                }
                if ($routeName == "api.v3.transfers") {
                    $limit = App::make(GetMerchantApiOrderLimitService::class)->excute($mid, 2);
                    return Limit::perMinute($limit ?: 1000)->by($mid);
                }
            }
            return Limit::perMinute(1000)->by(bob_ip());
        });

        RateLimiter::for('agent-captcha-get', function (Request $request) {
            return Limit::perMinute((int) config('agent-admin.captcha.get_per_minute', 20))
                ->by('agent-captcha-get:' . $this->captchaRateLimitKey($request));
        });

        RateLimiter::for('agent-captcha-check', function (Request $request) {
            return Limit::perMinute((int) config('agent-admin.captcha.check_per_minute', 60))
                ->by('agent-captcha-check:' . $this->captchaRateLimitKey($request));
        });
    }

    private function captchaRateLimitKey(Request $request): string
    {
        $ip = trim((string) $request->ip());

        return $ip !== '' ? 'ip:' . $ip : 'session:' . $request->session()->getId();
    }
}
