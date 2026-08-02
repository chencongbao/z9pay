<?php

namespace App\Http\Middleware;

use App\Services\Cache\Merchant\CacheApKeyService;
use App\Services\Merchant\SetMerchantLangService;
use App\Traits\ResponseTraits;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class CheckApiKey
{

    use ResponseTraits;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $authorization = $request->header('Authorization');
        if (empty($authorization)) {
            return $this->error("非法请求，请填写Authorization！");
        }
        $appkey = trim($authorization);
        if (preg_match('/^api-key\s*(.+)$/i', $appkey, $matches)) {
            $appkey = trim($matches[1]);
        }
        if (empty($appkey) || !preg_match('/^[a-f0-9]{16}$/', $appkey)) {
            return $this->error("Authorization格式错误！");
        }
        $cacheApKeyService = App::make(CacheApKeyService::class);
        $merchant_user_id = $cacheApKeyService->excute($appkey);
        if (!$merchant_user_id) {
            return $this->error("api-key错误，请填写商户API密钥！");
        }
        App::make(SetMerchantLangService::class)->excute($merchant_user_id);
        $request->attributes->set("merchant_user_id", $merchant_user_id);
        $request->headers->set("merchant_user_id", bob_lock($merchant_user_id));
        return $next($request);
    }
}
