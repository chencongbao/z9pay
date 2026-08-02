<?php

namespace App\Services\User;

use Throwable;
use App\Models\User;
use App\Models\UserBank;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\DB;
use App\Services\Common\ReportExceptionService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class ReserveUserDepositOrderService
{
    public function execute(
        int $orderId,
        int $userId,
        int $userBankId,
        float $pendingAmountLimit = 0,
        int $sameAmountLimit = 0,
        array $bankPendingLimits = []
    ): bool
    {
        $reservedOrder = DB::transaction(function () use (
            $orderId,
            $userId,
            $userBankId,
            $pendingAmountLimit,
            $sameAmountLimit,
            $bankPendingLimits
        ) {
            // 锁定金主后重新计算代收待付款金额，保证并发订单不能同时透支保证金。
            $user = User::query()
                ->whereKey($userId)
                ->where('is_agent', 0)
                ->where('status', 1)
                ->where('acquisition_status', 1)
                ->lockForUpdate()
                ->first([
                'id',
                'deposit_amount',
                'deposit_balance_amount',
                'transfer_balance_amount',
                'collection_limit_min',
                'collection_limit_max',
                'limit_deposit_paid_number',
                'pid',
                'user_rate',
                'deposit_user_rate',
                'user_deposit_payment_rate',
                'agent1_rate',
                'agent2_rate',
                'agent3_rate',
                'agent4_rate',
                'agent5_rate',
                'deposit_agent1_rate',
                'deposit_agent2_rate',
                'deposit_agent3_rate',
                'deposit_agent4_rate',
                'deposit_agent5_rate',
            ]);
            if (!$user) {
                return null;
            }

            $lockedOrder = DepositOrder::query()->whereKey($orderId)->lockForUpdate()->first();
            if (
                !$lockedOrder
                || intval($lockedOrder->user_id) > 0
                || !in_array(intval($lockedOrder->status), UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES, true)
                || floatval($lockedOrder->amount) <= 0
                || intval($lockedOrder->payment_id) <= 0
            ) {
                return null;
            }

            $userBank = UserBank::query()
                ->whereKey($userBankId)
                ->where('user_id', $userId)
                ->where('collection_status', 1)
                ->where('payment_id', $lockedOrder->payment_id)
                ->lockForUpdate()
                ->first([
                    'id',
                    'limint_min_amount',
                    'limint_max_amount',
                    'limint_day_amount',
                    'limit_day_order_number',
                ]);
            if (
                !$userBank
                || !$this->amountWithinLimits($lockedOrder->amount, $user->collection_limit_min, $user->collection_limit_max)
                || !$this->amountWithinLimits($lockedOrder->amount, $userBank->limint_min_amount, $userBank->limint_max_amount)
                || !$this->bankDailyLimitAllows($userBank, $lockedOrder)
                || !$this->bankPendingLimitAllows($userBank, $lockedOrder, $bankPendingLimits)
            ) {
                return null;
            }

            $pendingAmount = DepositOrder::query()
                ->where('user_id', $userId)
                ->where('id', '!=', $lockedOrder->id)
                ->whereIn('status', UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES)
                ->sum('amount');

            $effectiveSameAmountLimit = (int)$user->limit_deposit_paid_number > 0
                ? (int)$user->limit_deposit_paid_number
                : $sameAmountLimit;
            if ($effectiveSameAmountLimit > 0) {
                $sameAmountCount = DepositOrder::query()
                    ->where('user_id', $userId)
                    ->where('id', '!=', $lockedOrder->id)
                    ->whereIn('status', UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES)
                    ->where('amount', $lockedOrder->amount)
                    ->count();
                if ($sameAmountCount >= $effectiveSameAmountLimit) {
                    return null;
                }
            }

            if ($pendingAmountLimit > 0 && (float)$pendingAmount + (float)$lockedOrder->amount > $pendingAmountLimit) {
                return null;
            }

            $remainingDeposit = bob_amount_format(
                $user->deposit_amount
                + (float)$user->transfer_balance_amount
                - (float)$user->deposit_balance_amount
                - (float)$pendingAmount
            );
            if ((float)$user->deposit_amount > 0 && $this->amountLessThan($remainingDeposit, $lockedOrder->amount)) {
                return null;
            }

            $lockedOrder->fill(array_merge([
                'user_id' => $userId,
                'user_bank_id' => $userBankId,
            ], $this->commissionSnapshot($user, (int)$lockedOrder->payment_id)));
            $lockedOrder->save();

            return $lockedOrder;
        }, 3);

        if (!$reservedOrder) {
            return false;
        }

        // 数据库预占成功后同步缓存；缓存异常不影响数据库锁保证的额度正确性。
        try {
            app(GetUserDaifukuanDepositOrderListService::class)->add($userId, $reservedOrder);
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('金主保证金预占缓存同步失败', $e, [
                'order_id' => $reservedOrder->id,
                'user_id' => $userId,
                'user_bank_id' => $userBankId,
            ]);
        }

        return true;
    }

    private function commissionSnapshot(User $user, int $paymentId): array
    {
        $ancestorIds = DB::table('user_relations')
            ->where('child_id', $user->id)
            ->where('level', '>', 0)
            ->orderBy('level')
            ->limit(5)
            ->pluck('parent_id')
            ->map(fn($id) => (int)$id)
            ->values();

        $data = [
            'user_rate' => app(GetUserCommisonRateService::class)->resolve($user, $paymentId),
        ];
        foreach (range(1, 5) as $level) {
            $depositRate = (float)$user->{"deposit_agent{$level}_rate"};
            $defaultRate = (float)$user->{"agent{$level}_rate"};
            $data["user_agent{$level}_rate"] = ($depositRate > 0 ? $depositRate : $defaultRate) / 100;
            $data["user_agent{$level}_id"] = (int)($ancestorIds->get($level - 1) ?? 0);
        }

        return $data;
    }

    private function bankPendingLimitAllows(UserBank $userBank, DepositOrder $order, array $limits): bool
    {
        $sameAmountMinutes = (int)($limits['same_amount_minutes'] ?? 0);
        $sameAmountCount = (int)($limits['same_amount_count'] ?? 0);
        if ($sameAmountMinutes > 0 && $sameAmountCount > 0) {
            $count = DepositOrder::query()
                ->where('user_bank_id', $userBank->id)
                ->where('id', '!=', $order->id)
                ->whereIn('status', UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES)
                ->where('amount', $order->amount)
                ->where('created_at', '>=', now()->subMinutes($sameAmountMinutes))
                ->count();
            if ($count >= $sameAmountCount) {
                return false;
            }
        }

        $pendingMinutes = (int)($limits['pending_minutes'] ?? 0);
        $pendingCount = (int)($limits['pending_count'] ?? 0);
        if ($pendingMinutes > 0 && $pendingCount > 0) {
            $count = DepositOrder::query()
                ->where('user_bank_id', $userBank->id)
                ->where('id', '!=', $order->id)
                ->whereIn('status', UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES)
                ->where('created_at', '>=', now()->subMinutes($pendingMinutes))
                ->count();

            return $count < $pendingCount;
        }

        return true;
    }

    private function amountWithinLimits($amount, $minimum, $maximum): bool
    {
        if ((float)$minimum > 0 && $this->amountLessThan($amount, $minimum)) {
            return false;
        }

        return (float)$maximum <= 0 || !$this->amountGreaterThan($amount, $maximum);
    }

    private function bankDailyLimitAllows(UserBank $userBank, DepositOrder $order): bool
    {
        $dayStart = now()->startOfDay();
        $dayEnd = now()->endOfDay();
        $orders = DepositOrder::query()
            ->where('user_bank_id', $userBank->id)
            ->where('id', '!=', $order->id)
            ->whereBetween('created_at', [$dayStart, $dayEnd]);

        if ((float)$userBank->limint_day_amount > 0) {
            $reservedAmount = (clone $orders)
                ->whereIn('status', array_merge(UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES, [5]))
                ->sum('amount');
            if ($this->amountGreaterThan((float)$reservedAmount + (float)$order->amount, $userBank->limint_day_amount)) {
                return false;
            }
        }

        if ((int)$userBank->limit_day_order_number > 0) {
            $reservedCount = (clone $orders)
                ->whereIn('status', array_merge(UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES, [5]))
                ->count();
            if ($reservedCount + 1 > (int)$userBank->limit_day_order_number) {
                return false;
            }
        }

        return true;
    }

    private function amountLessThan($left, $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$left, (string)$right, 2) < 0;
        }

        return (float)$left < (float)$right;
    }

    private function amountGreaterThan($left, $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$left, (string)$right, 2) > 0;
        }

        return (float)$left > (float)$right;
    }
}
