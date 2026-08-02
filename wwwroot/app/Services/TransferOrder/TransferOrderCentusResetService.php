<?php

namespace App\Services\TransferOrder;

use App\Services\Report\OrderStatusReportRepairService;
use App\Models\TransferOrder;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;

class TransferOrderCentusResetService
{
    use ServiceTraits;

    public function excute($order = null): void
    {
        if (!$order || empty($order->id)) {
            return;
        }

        $order = TransferOrder::query()->find((int) $order->id);
        if (!$order) {
            return;
        }

        App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
    }
}
