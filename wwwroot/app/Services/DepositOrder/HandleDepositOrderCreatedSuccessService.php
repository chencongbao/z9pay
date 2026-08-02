<?php

namespace App\Services\DepositOrder;

use Throwable;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Order\OrderCacheService;
use App\Services\Common\ReportExceptionService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class HandleDepositOrderCreatedSuccessService
{
    use ServiceTraits;

    public function excute($order = null): void
    {
        if (empty($order) || empty($order->id)) {
            return;
        }

        // 刷新通道匹配后的最终订单缓存。
        App::make(OrderCacheService::class)->putDeposit($order);
        $this->syncPendingPayOrder($order);
    }

    protected function syncPendingPayOrder($order): void
    {
        $userId = intval($order->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        try {
            // 创建、待支付、待确认状态进入列表，其他状态同步移除旧缓存。
            App::make(GetUserDaifukuanDepositOrderListService::class)->syncByOrder($userId, $order);
        } catch (Throwable $e) {
            App::make(ReportExceptionService::class)->report('代收待付款缓存刷新失败', $e, [
                'order_id' => $order->id,
                'ordernumber' => $order->ordernumber ?? null,
                'user_id' => $userId,
                'status' => $order->status ?? null,
            ]);
        }
    }
}
