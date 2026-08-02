<?php

namespace App\Services\Cache\DepositOrder;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class DepositOrderCacheChannelAutoPriorityService
{
    use ServiceTraits;

    public function excute($key = ''): int
    {
        return intval(Cache::get(CacheConstPrefixService::DEPOSIT_ORDER_CHSANNEL_AUTO_PRIORITY . $key, 0));
    }

    public function set($value = 0, $key = ''): void
    {
        Cache::forever(CacheConstPrefixService::DEPOSIT_ORDER_CHSANNEL_AUTO_PRIORITY . $key, intval($value));
    }
}
