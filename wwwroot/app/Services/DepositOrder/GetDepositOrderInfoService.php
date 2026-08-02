<?php

namespace App\Services\DepositOrder;

use App\Services\Order\OrderCacheService;

class GetDepositOrderInfoService
{
    public function __construct(private OrderCacheService $orderCacheService)
    {
    }

    public function excute($id = 0): array
    {
        $id = (int) $id;

        return $id > 0 ? $this->orderCacheService->getDepositById($id) : [];
    }
}
