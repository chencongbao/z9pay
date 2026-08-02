<?php

namespace App\Services\Cache\DepositOrder;

use App\Traits\CacheTrait;
use App\Models\DepositOrder;
use App\Traits\ServiceTraits;
use App\Services\Cache\CacheConstPrefixService;

class DepositOrderSumMerchantAgent1CommissionCacheService
{
    use ServiceTraits, CacheTrait;

    public function excute($data = [])
    {
        $model = new DepositOrder();
        $historyResult = $this->oldTimeSum($model, 'merchant_agent1_commission', CacheConstPrefixService::DEPOSIT_ORDER_SUM_MERCHANTAGENT1_COMMISSION, $data);
        $todayResult = $this->todayTimeSum($model, 'merchant_agent1_commission', $data);

        return $historyResult + $todayResult;
    }
}
