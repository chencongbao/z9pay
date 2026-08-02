<?php

namespace App\Services\Cache\MerchantPayment;

use App\Traits\ServiceTraits;
use App\Models\MerchantPayment;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetMerchantPaymentRateListService
{
    use ServiceTraits;

    private const CACHE_TTL_SECONDS = 86400;

    private const RATE_FIELDS = [
        'id',
        'merchant_user_id',
        'payment_id',
        'status',
        'pay_rate',
        'agent1_rate',
        'agent2_rate',
        'agent3_rate',
        'min_limit_amount',
        'max_limit_amount',
        'transfer_rates',
    ];

    public function excute($mid = 0, $payment_id = 0, bool $force = false): array
    {
        $mid = intval($mid);
        $payment_id = intval($payment_id);
        if ($mid <= 0 || $payment_id <= 0) {
            return [];
        }

        if ($force) {
            return $this->update($mid, $payment_id);
        }

        return Cache::remember($this->cacheKey($mid, $payment_id), self::CACHE_TTL_SECONDS, function () use ($mid, $payment_id) {
            return $this->queryRates($mid, $payment_id);
        });
    }

    public function update($mid, $payment_id): array
    {
        $mid = intval($mid);
        $payment_id = intval($payment_id);
        if ($mid <= 0 || $payment_id <= 0) {
            return [];
        }

        $data = $this->queryRates($mid, $payment_id);
        Cache::put($this->cacheKey($mid, $payment_id), $data, self::CACHE_TTL_SECONDS);

        return $data;
    }

    private function queryRates(int $mid, int $paymentId): array
    {
        return MerchantPayment::query()
            ->where('merchant_user_id', $mid)
            ->where('payment_id', $paymentId)
            ->where('status', 1)
            ->get(self::RATE_FIELDS)
            ->toArray();
    }

    private function cacheKey(int $mid, int $paymentId): string
    {
        return CacheConstPrefixService::MERCHANT_PAYMENT_DETAIL_LIST . $mid . '_' . $paymentId;
    }
}
