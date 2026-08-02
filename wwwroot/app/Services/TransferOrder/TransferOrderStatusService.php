<?php

namespace App\Services\TransferOrder;

use App\Models\TransferOrder;
use App\Services\Report\OrderStatusReportRepairService;
use App\Services\Order\OrderCacheService;
use Illuminate\Support\Facades\App;

class TransferOrderStatusService
{
    public function markFailed(TransferOrder $order, string $remark = ''): bool
    {
        $order->fill([
            'status' => 5,
            'remark' => $remark,
        ]);
        $order->save();
        App::make(OrderCacheService::class)->putTransfer($order);
        App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);

        return true;
    }

    public function markPending(TransferOrder $order, string $remark = ''): bool
    {
        $order->status = 3;
        if ($remark !== '') {
            $order->remark = $remark;
        }
        $order->save();
        App::make(OrderCacheService::class)->putTransfer($order);
        App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);

        return true;
    }

    public function markPendingConfirm(TransferOrder $order, array $channelInfo, string $remark = ''): bool
    {
        $order->status = 3;
        $order->remark = $remark;
        $order->channel_info = $this->confirmChannelInfo($channelInfo);
        $order->transfer_order_confirm_remark = [
            'remark' => $remark,
            'time' => date('Y-m-d H:i:s'),
            'action' => 'pending_confirm',
        ];
        $order->save();
        App::make(OrderCacheService::class)->putTransfer($order);
        App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);

        return true;
    }

    private function confirmChannelInfo(array $channelInfo): array
    {
        return [
            'channel_id' => intval($channelInfo['channel_id'] ?? 0),
            'merchant_channel_id' => intval($channelInfo['merchant_channel_id'] ?? 0),
            'name' => (string)($channelInfo['name'] ?? ($channelInfo['channel_name'] ?? '')),
            'classname' => (string)($channelInfo['classname'] ?? ''),
            'merchant_extra_fee' => bob_amount_format($channelInfo['merchant_extra_fee'] ?? 0),
        ];
    }
}
