<?php

namespace App\Services\DepositOrder;

use App\Models\DepositOrder;
use App\Services\Report\OrderStatusReportRepairService;
use App\Services\Order\OrderCacheService;
use App\Services\User\UserTodayStatsRebuildService;
use Illuminate\Support\Facades\App;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class DepositOrderStatusService
{
    // 标记代收订单失败，并立即刷新缓存，避免查单读到旧状态。
    public function markFailed(DepositOrder $order, string $remark = ''): bool
    {
        $order->fill([
            'status' => 6,
            'remark' => $remark,
        ]);
        $order->save();
        App::make(OrderCacheService::class)->putDeposit($order, true);
        if (intval($order->user_id) > 0) {
            App::make(GetUserDaifukuanDepositOrderListService::class)->remove($order->user_id, $order);
        }
        $this->rebuildTodayStats($order);
        App::make(OrderStatusReportRepairService::class)->forDepositOrder($order);

        return true;
    }

    // 标记收银台会员主动取消：主状态按超时处理，付款状态保留“付方已取消”方便追踪来源。
    public function markCancelled(DepositOrder $order, string $remark = '会员手动取消'): bool
    {
        $order->fill([
            'status' => 4,
            'pay_status' => 3,
            'remark' => $remark,
        ]);
        $order->save();
        App::make(OrderCacheService::class)->putDeposit($order, true);
        if (intval($order->user_id) > 0) {
            App::make(GetUserDaifukuanDepositOrderListService::class)->remove($order->user_id, $order);
        }
        $this->rebuildTodayStats($order);
        App::make(OrderStatusReportRepairService::class)->forDepositOrder($order);

        return true;
    }

    // 标记代收订单为风控状态，并立即刷新缓存，避免后续流程继续当正常订单处理。
    public function markRisk(DepositOrder $order, string $remark = '刷单'): bool
    {
        $order->fill([
            'status' => 2,
            'remark' => $remark,
        ]);
        $order->save();
        App::make(OrderCacheService::class)->putDeposit($order, true);
        $this->rebuildTodayStats($order);
        App::make(OrderStatusReportRepairService::class)->forDepositOrder($order);

        return true;
    }

    // 标记代收订单超时，并立即刷新缓存，避免查单读到旧状态。
    public function markTimeout(DepositOrder $order, string $remark = ''): bool
    {
        $order->fill([
            'status' => 4,
            'remark' => $remark,
        ]);
        $order->save();
        App::make(OrderCacheService::class)->putDeposit($order, true);
        $this->rebuildTodayStats($order);
        App::make(OrderStatusReportRepairService::class)->forDepositOrder($order);

        return true;
    }

    private function rebuildTodayStats(DepositOrder $order): void
    {
        $order = DepositOrder::query()->whereKey((int) $order->id)->first([
            'id',
            'user_id',
            'user_bank_id',
            'success_time',
            'user_agent1_id',
            'user_agent2_id',
            'user_agent3_id',
            'user_agent4_id',
            'user_agent5_id',
        ]);
        if (!$order) {
            return;
        }

        if (intval($order->success_time ?? 0) <= 0 || date('Y-m-d', (int) $order->success_time) !== date('Y-m-d')) {
            return;
        }

        $userIds = [
            (int) $order->user_id,
            (int) $order->user_agent1_id,
            (int) $order->user_agent2_id,
            (int) $order->user_agent3_id,
            (int) $order->user_agent4_id,
            (int) $order->user_agent5_id,
        ];

        foreach (array_unique(array_filter($userIds)) as $userId) {
            App::make(UserTodayStatsRebuildService::class)->rebuild((int) $userId);
        }

        if ((int) $order->user_bank_id > 0) {
            App::make(UserTodayStatsRebuildService::class)->rebuild(null, (int) $order->user_bank_id);
        }
    }
}
