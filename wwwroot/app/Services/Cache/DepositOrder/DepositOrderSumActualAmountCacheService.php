<?php

namespace App\Services\Cache\DepositOrder;

use App\Traits\CacheTrait;
use App\Models\DepositOrder;
use App\Traits\ServiceTraits;
use App\Services\Cache\CacheConstPrefixService;

class DepositOrderSumActualAmountCacheService
{
    use ServiceTraits, CacheTrait;

    public function excute($data = [])
    {
        $model = new DepositOrder();
        $historyResult = $this->oldTimeSum($model, 'actual_amount', CacheConstPrefixService::DEPOSIT_ORDER_SUM_ACTUAL_AMOUNT, $data);
        $todayResult = $this->todayTimeSum($model, 'actual_amount', $data);

        return $historyResult + $todayResult;
    }
}
