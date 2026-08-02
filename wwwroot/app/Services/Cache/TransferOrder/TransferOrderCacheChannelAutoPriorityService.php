<?php

namespace App\Services\Cache\TransferOrder;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class TransferOrderCacheChannelAutoPriorityService
{
    use ServiceTraits;

    public function excute($key = ''): int
    {
        return intval(Cache::get($this->cacheKey($key), 0));
    }

    public function set($value = 0, $key = ''): void
    {
        Cache::forever($this->cacheKey($key), intval($value));
    }

    public function forget($key = ''): void
    {
        Cache::forget($this->cacheKey($key));
    }

    public function increment($key = '', int $value = 1): int
    {
        return intval(Cache::increment($this->cacheKey($key), $value));
    }

    private function cacheKey($key = ''): string
    {
        return CacheConstPrefixService::TRANSFER_ORDER_CHSANNEL_AUTO_PRIORITY . $key;
    }
}
