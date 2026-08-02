<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\DepositOrder;

class UserPendingDepositOrderStatsService
{
    public const PENDING_PAY_STATUSES = [1, 3, 7];

    public function replace(int $userId = 0, $amount = 0, int $count = 0): void
    {
        if ($userId <= 0) {
            return;
        }

        User::query()->whereKey($userId)->where('is_agent', 0)->update([
            'pending_deposit_order_amount' => bob_amount_format($amount),
            'pending_deposit_order_count' => max(0, $count),
        ]);
    }

    public function rebuild(int $userId = 0): array
    {
        if ($userId <= 0) {
            return ['amount' => 0, 'count' => 0];
        }

        $summary = DepositOrder::query()
            ->where('user_id', $userId)
            ->whereIn('status', self::PENDING_PAY_STATUSES)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        $amount = bob_amount_format($summary->total_amount ?? 0);
        $count = intval($summary->total_count ?? 0);
        $this->replace($userId, $amount, $count);

        return ['amount' => $amount, 'count' => $count];
    }

    public function amount(int $userId = 0, bool $force = false): float
    {
        if ($userId <= 0) {
            return 0;
        }

        if ($force) {
            return $this->rebuild($userId)['amount'];
        }

        $user = User::query()->whereKey($userId)->where('is_agent', 0)->first(['pending_deposit_order_amount']);

        return bob_amount_format($user->pending_deposit_order_amount ?? 0);
    }

    public function count(int $userId = 0, bool $force = false): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if ($force) {
            return $this->rebuild($userId)['count'];
        }

        $user = User::query()->whereKey($userId)->where('is_agent', 0)->first(['pending_deposit_order_count']);

        return intval($user->pending_deposit_order_count ?? 0);
    }
}
