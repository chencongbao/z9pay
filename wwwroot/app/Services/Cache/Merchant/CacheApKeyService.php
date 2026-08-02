<?php

namespace App\Services\Cache\Merchant;

use App\Models\MerchantInfo;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class CacheApKeyService
{
    use ServiceTraits;

    private const CACHE_MISSING = '__cache_missing__';

    public function excute($appkey = '', $force = false)
    {
        if (empty($appkey)) {
            return 0;
        }

        $key = CacheConstPrefixService::MERCHANT_APPKEY_PREFIEX . $appkey;
        if ($force) {
            return $this->updateCache($appkey, $key);
        }
        $value = Cache::get($key, self::CACHE_MISSING);
        if ($value !== self::CACHE_MISSING) {
            return $value;
        }
        return $this->updateCache($appkey, $key);
    }

    private function updateCache($appkey, $key)
    {
        $result = MerchantInfo::query()
            ->join('merchant_users', 'merchant_users.id', '=', 'merchant_infos.merchant_user_id')
            ->where('merchant_infos.appkey', $appkey)
            ->where('merchant_users.status', 1)
            ->first(['merchant_infos.merchant_user_id', 'merchant_infos.appkey']);
        if ($result) {
            Cache::put($key, $result->merchant_user_id, now()->addMinutes(30));

            return $result->merchant_user_id;
        }

        $this->removeCache($appkey);
        Cache::put($key, 0, now()->addMinutes(5));

        return 0;
    }

    public function removeCache($appkey = '')
    {
        $key = CacheConstPrefixService::MERCHANT_APPKEY_PREFIEX . $appkey;

        return Cache::forget($key);
    }
}
