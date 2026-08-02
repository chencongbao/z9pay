<?php

namespace App\Services\TransferOrder;

use App\Services\Order\OrderCacheService;

class GetTransferOrderInfoService
{
    public function __construct(private OrderCacheService $orderCacheService)
    {
    }

    public function excute($id = 0): array
    {
        $id = (int) $id;

        return $id > 0 ? $this->orderCacheService->getTransferById($id) : [];
    }
}
