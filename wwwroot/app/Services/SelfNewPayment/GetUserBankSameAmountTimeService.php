<?php

namespace App\Services\SelfNewPayment;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetUserBankSameAmountTimeService
{
    use ServiceTraits;

    public function excute($user_bank_id = 0, $amount = 0, $time = 0)
    {
        if ($user_bank_id <= 0 || $amount <= 0) {
            return 0;
        }

        $key = $this->cacheKey($user_bank_id, $amount);
        if ($time > 0) {
            return Cache::put($key, time(), now()->addMinutes($time));
        }

        return (int) Cache::get($key, 0);
    }

    public function forget($user_bank_id = 0, $amount = 0): bool
    {
        if ($user_bank_id <= 0 || $amount <= 0) {
            return false;
        }

        return Cache::forget($this->cacheKey($user_bank_id, $amount));
    }

    private function cacheKey($user_bank_id, $amount): string
    {
        return CacheConstPrefixService::SAME_AMOUNT_INTERVAL_TIME . (int)$user_bank_id . '_' . (float)($amount * 100);
    }
}
