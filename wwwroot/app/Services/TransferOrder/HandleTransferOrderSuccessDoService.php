<?php

namespace App\Services\TransferOrder;

use App\Jobs\QueryChannelBalanceByIdJob;
use App\Services\Channel\GetChannelNoticeBalanceService;
use App\Services\Order\OrderCacheService;
use App\Services\User\UserTodayTransferStatsService;
use App\Services\User\UserMonthTotalAmountService;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;

class HandleTransferOrderSuccessDoService
{
    use ServiceTraits;

    public function excute($order = null)
    {
        if (!$order) {
            return;
        }

        App::make(OrderCacheService::class)->putTransfer($order, true);
        $this->userCentus($order);
        $this->centusReset($order);
        $this->queryChannelBalance($order);
    }

    protected function userCentus($order)
    {
        $successTimestamp = intval($order->success_time ?? 0) > 0 ? intval($order->success_time) : time();
        $isToday = date('Y-m-d', $successTimestamp) == date('Y-m-d');
        $isThisMonth = date('Y-m', $successTimestamp) == date('Y-m');
        $items = [
            [$order->user_id, $order->user_commission, 0],
            [$order->user_agent1_id, $order->user_agent1_commission, 1],
            [$order->user_agent2_id, $order->user_agent2_commission, 1],
            [$order->user_agent3_id, $order->user_agent3_commission, 1],
            [$order->user_agent4_id, $order->user_agent4_commission, 1],
            [$order->user_agent5_id, $order->user_agent5_commission, 1],
        ];

        foreach ($items as [$userId, $commission, $isAgent]) {
            if ($userId <= 0) {
                continue;
            }

            if ($isToday) {
                App::make(UserTodayTransferStatsService::class)->increase((int) $userId, (float) $order->actual_amount, 1, (float) $commission);
            }
            if ($isThisMonth) {
                App::make(UserMonthTotalAmountService::class)->excute($userId, $order->actual_amount, $isAgent);
            }
        }
    }

    protected function centusReset($order)
    {
        App::make(TransferOrderCentusResetService::class)->excute($order);
    }

    protected function queryChannelBalance($order): void
    {
        if (intval($order->channel_id ?? 0) <= 1) {
            return;
        }

        if (! App::make(GetChannelNoticeBalanceService::class)->enabled((int) $order->channel_id)) {
            return;
        }

        dispatch(new QueryChannelBalanceByIdJob((int) $order->channel_id))->onQueue('query');
    }
}
