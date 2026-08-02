<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserTodayTransferStatsService
{
    public function increase(int $userId, float $amount = 0, int $number = 0, float $income = 0): void
    {
        if ($userId <= 0) {
            return;
        }

        $amount = bob_amount_format($amount);
        $income = bob_amount_format($income);
        $number = max(0, $number);
        $today = date('Y-m-d');

        app(UserTodayStatsRebuildService::class)->runWithRebuildLock(function () use ($userId, $today, $number, $amount, $income) {
            // 成功代付时直接维护金主/代理今日统计，避免读取 Redis 或实时聚合订单。
            User::query()->whereKey($userId)->update([
                'today_transfer_total_number' => DB::raw("IF(today_transfer_stat_date = '{$today}', today_transfer_total_number + {$number}, {$number})"),
                'today_transfer_total_amount' => DB::raw("IF(today_transfer_stat_date = '{$today}', today_transfer_total_amount + {$amount}, {$amount})"),
                'today_transfer_total_income' => DB::raw("IF(today_transfer_stat_date = '{$today}', today_transfer_total_income + {$income}, {$income})"),
                // MySQL 会按赋值顺序求值，日期必须最后更新，前面的 IF 才能读取旧统计日期。
                'today_transfer_stat_date' => $today,
            ]);
        });
    }

    public function amount($user): float
    {
        if (!$this->isToday($user)) {
            return 0;
        }

        return bob_amount_format($user->today_transfer_total_amount ?? 0);
    }

    public function number($user): int
    {
        if (!$this->isToday($user)) {
            return 0;
        }

        return intval($user->today_transfer_total_number ?? 0);
    }

    public function income($user): float
    {
        if (!$this->isToday($user)) {
            return 0;
        }

        return bob_amount_format($user->today_transfer_total_income ?? 0);
    }

    public function amountFor(int $userId, int $isAgent = 0): float
    {
        $user = $this->findOrRefresh($userId, $isAgent);

        return $user ? $this->amount($user) : 0;
    }

    public function numberFor(int $userId, int $isAgent = 0): int
    {
        $user = $this->findOrRefresh($userId, $isAgent);

        return $user ? $this->number($user) : 0;
    }

    public function incomeFor(int $userId, int $isAgent = 0): float
    {
        $user = $this->findOrRefresh($userId, $isAgent);

        return $user ? $this->income($user) : 0;
    }

    private function findOrRefresh(int $userId, int $isAgent): ?User
    {
        $user = $this->findStats($userId);
        if ($this->isToday($user)) {
            return $user;
        }

        $this->refreshFromSources($userId, $isAgent);

        return $this->findStats($userId);
    }

    private function refreshFromSources(int $userId, int $isAgent): void
    {
        app(UserTodayStatsRebuildService::class)->rebuild($userId);
    }

    private function findStats(int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        return User::query()
            ->select(['id', 'today_transfer_stat_date', 'today_transfer_total_number', 'today_transfer_total_amount', 'today_transfer_total_income'])
            ->find($userId);
    }

    private function isToday($user): bool
    {
        if (!$user || empty($user->today_transfer_stat_date)) {
            return false;
        }

        return date('Y-m-d', strtotime($user->today_transfer_stat_date)) === date('Y-m-d');
    }
}
