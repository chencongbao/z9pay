<?php

namespace App\Services\DepositOrder;

use App\Models\UserBank;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Jobs\QueryChannelBalanceByIdJob;
use App\Services\Order\OrderCacheService;
use App\Services\User\UserMonthTotalAmountService;
use App\Services\User\UserTodayDepositStatsService;
use App\Services\UserBank\UserBankTodayStatsService;
use App\Services\Channel\GetChannelNoticeBalanceService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class HandleDepositOrderSuccessService
{
    use ServiceTraits;

    private const CHANNEL_BALANCE_QUERY_LOCK_SECONDS = 60;

    public function excute($order = "")
    {
        if (!$order) {
            return;
        }

        App::make(OrderCacheService::class)->putDeposit($order, true);
        $this->updateUserBankLastCollectionTime($order);
        $this->updateUserBankStats($order);
        $this->updateUserStats($order);
        $this->resetReportStats($order);
        $this->queryChannelBalance($order);
    }

    protected function updateUserStats($order): void
    {
        $successTimestamp = $this->successTimestamp($order);
        $isToday = date('Y-m-d', $successTimestamp) == date('Y-m-d');
        $isThisMonth = date('Y-m', $successTimestamp) == date('Y-m');

        if ($order->user_id > 0) {
            App::make(GetUserDaifukuanDepositOrderListService::class)->remove($order->user_id, $order);
        }

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
                App::make(UserTodayDepositStatsService::class)->increase((int) $userId, (float) $order->actual_amount, 1, (float) $commission);
            }
            if ($isThisMonth) {
                App::make(UserMonthTotalAmountService::class)->excute($userId, $order->actual_amount, $isAgent);
            }
        }
    }

    protected function updateUserBankStats($order): void
    {
        if ($order->user_bank_id > 0 && date('Y-m-d', $this->successTimestamp($order)) == date('Y-m-d')) {
            App::make(UserBankTodayStatsService::class)->increase((int) $order->user_bank_id, (float) $order->actual_amount, 1, (float) $order->user_commission);
        }
    }

    protected function successTimestamp($order): int
    {
        return intval($order->success_time ?? 0) > 0 ? intval($order->success_time) : time();
    }

    protected function updateUserBankLastCollectionTime($order): void
    {
        $userBankId = intval($order->user_bank_id ?? 0);
        if ($userBankId <= 0) {
            return;
        }

        $lastCollectionTime = !empty($order->success_time) ? date('Y-m-d H:i:s', intval($order->success_time)) : now()->toDateTimeString();

        // 只写入更晚的成功入款时间，避免旧订单延迟成功覆盖新订单时间。
        UserBank::query()
            ->whereKey($userBankId)
            ->where(function ($query) use ($lastCollectionTime) {
                $query->whereNull('last_collection_time')->orWhere('last_collection_time', '<', $lastCollectionTime);
            })
            ->update(['last_collection_time' => $lastCollectionTime]);
    }

    protected function resetReportStats($order): void
    {
        App::make(DepositOrderCentusResetService::class)->excute($order);
    }

    protected function queryChannelBalance($order): void
    {
        if (intval($order->channel_id ?? 0) <= 1) {
            return;
        }

        if (! App::make(GetChannelNoticeBalanceService::class)->enabled((int) $order->channel_id)) {
            return;
        }

        $channelId = (int) $order->channel_id;
        $lockKey = 'deposit_order:success:channel_balance_query:' . $channelId;
        if (!Cache::add($lockKey, 1, now()->addSeconds(self::CHANNEL_BALANCE_QUERY_LOCK_SECONDS))) {
            return;
        }

        dispatch(new QueryChannelBalanceByIdJob($channelId))->onQueue('query');
    }
}
