<?php

namespace App\Services\Cache\BlackContent;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class CacheCashierUserIpService
{
    use ServiceTraits;

    public function excute(): array
    {
        $cache = Cache::get(CacheConstPrefixService::CASHIER_USER_BLACK_IP_ADDRESS);

        return is_array($cache) ? $cache : [];
    }

    public function add(string $ip): void
    {
        $ip = trim($ip);
        if ($ip === '') {
            return;
        }

        $cache = $this->excute();
        $cache[$ip] = true;
        Cache::forever(CacheConstPrefixService::CASHIER_USER_BLACK_IP_ADDRESS, $cache);
    }
}
