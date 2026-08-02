<?php

namespace App\Services\Cache\TransferOrder;

use App\Services\Order\OrderCacheService;

class CacheTransferOrderInfoService
{
    public function __construct(private OrderCacheService $orderCacheService)
    {
    }

    public function excute($ordernumber = null, $mid = 0, $force = false): array
    {
        if (!is_scalar($ordernumber) || trim((string) $ordernumber) === '') {
            return [];
        }

        $ordernumber = trim((string) $ordernumber);
        $mid = (int) $mid;
        $result = $force
            ? $this->orderCacheService->refreshTransferByOrdernumber($ordernumber)
            : $this->orderCacheService->getTransferByOrdernumber($ordernumber);

        if (!empty($result) || $mid <= 0) {
            return $result;
        }

        return $this->orderCacheService->getTransferByMerchantOrder($mid, $ordernumber, (bool) $force);
    }
}
