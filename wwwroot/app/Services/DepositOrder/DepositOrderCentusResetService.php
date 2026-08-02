<?php

namespace App\Services\DepositOrder;

use App\Models\DepositOrder;
use App\Services\Report\OrderStatusReportRepairService;
use Illuminate\Support\Facades\App;

class DepositOrderCentusResetService
{
    public function excute(?DepositOrder $order = null): void
    {
        if (!$order || empty($order->created_at)) {
            return;
        }

        App::make(OrderStatusReportRepairService::class)->forDepositOrder($order);
    }
}
