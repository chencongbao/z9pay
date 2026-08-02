<?php

namespace App\Services\Cache\DepositOrder;

use App\Traits\ServiceTraits;
use App\Models\DepositOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class CacheDepositOrderInfoService
{
    use ServiceTraits;

    public $cache_day = 4;

    public function excute($ordernumber = null, $mid = 0, $force = false)
    {
        if (!is_scalar($ordernumber) || $ordernumber === '') {
            return [];
        }

        $ordernumber = trim((string) $ordernumber);
        $key = CacheConstPrefixService::DEPOSIT_ORDER_INFO . $ordernumber;
        if ($force) {
            return $this->update($ordernumber);
        }

        $data = Cache::get($key);
        if ($data !== null) {
            if (empty($data) || $this->hasRequiredFields($data)) {
                return $this->normalize($data);
            }
            Cache::forget($key);
        }

        if ($mid > 0) {
            $key = $key . '_' . $mid;
            $data = Cache::get($key);
            if ($data !== null) {
                if (empty($data) || $this->hasRequiredFields($data)) {
                    return $this->normalize($data);
                }
                Cache::forget($key);
            }
        }

        return $this->update($ordernumber);
    }

    public function getMerchantOrder($orderNo = null, $mid = 0, $force = false)
    {
        if (empty($orderNo) || intval($mid) <= 0) {
            return [];
        }

        $key = CacheConstPrefixService::DEPOSIT_ORDER_INFO . $orderNo . '_' . $mid;
        $data = $force ? null : Cache::get($key);
        if ($data !== null) {
            if (empty($data) || $this->hasRequiredFields($data)) {
                return $this->normalize($data);
            }
            Cache::forget($key);
        }

        $model = DepositOrder::query()
            ->where('order_no', $orderNo)
            ->where('mid', $mid)
            ->first(CacheConstPrefixService::CACHE_DEPOSIT_FILED);

        if (!$model) {
            Cache::put($key, [], now()->addSeconds(30));
            return [];
        }

        return $this->cache($model);
    }

    private function update($ordernumber)
    {
        $field = CacheConstPrefixService::CACHE_DEPOSIT_FILED;
        $model = DepositOrder::query()->where('ordernumber', $ordernumber)->first($field);
        if ($model) {
            return $this->cache($model);
        }

        $model = DepositOrder::query()->where('order_no', $ordernumber)->first($field);
        if ($model) {
            return $this->cache($model);
        }

        return [];
    }

    public function cache($model)
    {
        $data = $this->getModelCacheData($model);
        $key1 = CacheConstPrefixService::DEPOSIT_ORDER_INFO . $model->ordernumber;
        Cache::put($key1, $data, now()->addDays($this->cache_day));

        $key2 = CacheConstPrefixService::DEPOSIT_ORDER_INFO . $model->order_no . '_' . $model->mid;
        Cache::put($key2, $data, now()->addDays($this->cache_day));

        $key3 = CacheConstPrefixService::DEPOSIT_ORDER_ID_INFO . $model->id;
        Cache::put($key3, $data, now()->addDays($this->cache_day));

        return $data;
    }

    private function getModelCacheData($model): array
    {
        $data = Arr::only($model->toArray(), CacheConstPrefixService::CACHE_DEPOSIT_FILED);
        if (!$this->hasRequiredFields($data) && !empty($model->id)) {
            $freshModel = DepositOrder::query()->whereKey($model->id)->first(CacheConstPrefixService::CACHE_DEPOSIT_FILED);
            if ($freshModel) {
                $data = Arr::only($freshModel->toArray(), CacheConstPrefixService::CACHE_DEPOSIT_FILED);
            }
        }

        return $this->normalize($data);
    }

    private function hasRequiredFields($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        foreach (CacheConstPrefixService::CACHE_DEPOSIT_FILED as $field) {
            if (!array_key_exists($field, $data)) {
                return false;
            }
        }

        return true;
    }

    private function normalize($data): array
    {
        if (!is_array($data) || empty($data)) {
            return [];
        }

        $defaults = array_fill_keys(CacheConstPrefixService::CACHE_DEPOSIT_FILED, null);
        $defaults['actual_amount'] = 0;
        $defaults['merchant_fee'] = 0;
        $defaults['merchant_extra_fee'] = 0;
        $defaults['callback_time'] = 0;
        $defaults['success_time'] = 0;
        $defaults['show_amount'] = 0;

        return array_merge($defaults, Arr::only($data, CacheConstPrefixService::CACHE_DEPOSIT_FILED));
    }
}
