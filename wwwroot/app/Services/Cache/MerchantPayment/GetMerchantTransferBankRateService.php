<?php

namespace App\Services\Cache\MerchantPayment;

use App\Traits\ServiceTraits;
use App\Models\MerchantPayment;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetMerchantTransferBankRateService
{
    use ServiceTraits;

    private const TRANSFER_PAYMENT_ID = 7;
    private const CACHE_TTL_SECONDS = 86400;

    public function excute($mid = 0, bool $force = false): array
    {
        $mid = intval($mid);
        if ($mid <= 0) {
            return [];
        }

        if ($force) {
            return $this->update($mid);
        }

        $key = $this->cacheKey($mid);
        return Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($mid) {
            return $this->queryTransferRates($mid);
        });
    }

    public function update($mid): array
    {
        $mid = intval($mid);
        if ($mid <= 0) {
            return [];
        }

        $data = $this->queryTransferRates($mid);
        Cache::put($this->cacheKey($mid), $data, self::CACHE_TTL_SECONDS);

        return $data;
    }

    private function queryTransferRates(int $mid): array
    {
        $data = [];
        $result = MerchantPayment::query()
            ->where('merchant_user_id', $mid)
            ->where('payment_id', self::TRANSFER_PAYMENT_ID)
            ->where('status', 1)
            ->pluck('transfer_rates');

        // 只缓存有效银行费率行，避免调用方每次再处理脏配置。
        foreach ($result as $rates) {
            foreach ($this->normalizeRates($rates) as $rate) {
                if (empty($rate['bank_id'])) {
                    continue;
                }

                $data[] = $rate;
            }
        }

        return $data;
    }

    private function normalizeRates($rates): array
    {
        if (empty($rates)) {
            return [];
        }

        if (is_string($rates)) {
            $rates = json_decode($rates, true);
        }

        if (!is_array($rates)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($rate) {
            if (is_object($rate)) {
                $rate = (array) $rate;
            }

            return is_array($rate) ? $rate : null;
        }, $rates)));
    }

    private function cacheKey(int $mid): string
    {
        return CacheConstPrefixService::MERCHANT_PAYMENT_TRANSFER_RATE . $mid;
    }
}
