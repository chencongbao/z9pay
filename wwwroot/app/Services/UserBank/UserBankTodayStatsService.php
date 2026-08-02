<?php

namespace App\Services\UserBank;

use App\Models\UserBank;
use App\Services\User\UserTodayStatsRebuildService;
use Illuminate\Support\Facades\DB;

class UserBankTodayStatsService
{
    public function increase(int $userBankId, float $amount, int $number, float $income): void
    {
        if ($userBankId <= 0) {
            return;
        }

        $amount = bob_amount_format($amount);
        $income = bob_amount_format($income);
        $number = max(0, $number);
        $today = date('Y-m-d');

        app(UserTodayStatsRebuildService::class)->runWithRebuildLock(function () use ($userBankId, $today, $amount, $number, $income) {
            // 成功入款时直接维护收款卡今日统计，避免列表逐行回表聚合订单。
            UserBank::query()->whereKey($userBankId)->update([
                'today_total_amount' => DB::raw("IF(today_stat_date = '{$today}', today_total_amount + {$amount}, {$amount})"),
                'today_total_number' => DB::raw("IF(today_stat_date = '{$today}', today_total_number + {$number}, {$number})"),
                'today_total_income' => DB::raw("IF(today_stat_date = '{$today}', today_total_income + {$income}, {$income})"),
                // MySQL 会按赋值顺序求值，日期必须最后更新，前面的 IF 才能读取旧统计日期。
                'today_stat_date' => $today,
            ]);
        });
    }

    public function amount($userBank): float
    {
        if (!$this->isToday($userBank)) {
            return 0;
        }

        return bob_amount_format($userBank->today_total_amount ?? 0);
    }

    public function number($userBank): int
    {
        if (!$this->isToday($userBank)) {
            return 0;
        }

        return intval($userBank->today_total_number ?? 0);
    }

    public function income($userBank): float
    {
        if (!$this->isToday($userBank)) {
            return 0;
        }

        return bob_amount_format($userBank->today_total_income ?? 0);
    }

    public function findAmount(int $userBankId): float
    {
        $userBank = $this->findStats($userBankId);

        return $userBank ? $this->amount($userBank) : 0;
    }

    public function findNumber(int $userBankId): int
    {
        $userBank = $this->findStats($userBankId);

        return $userBank ? $this->number($userBank) : 0;
    }

    public function findIncome(int $userBankId): float
    {
        $userBank = $this->findStats($userBankId);

        return $userBank ? $this->income($userBank) : 0;
    }

    public function amountFor(int $userBankId): float
    {
        $userBank = $this->findOrRefresh($userBankId);

        return $userBank ? $this->amount($userBank) : 0;
    }

    public function numberFor(int $userBankId): int
    {
        $userBank = $this->findOrRefresh($userBankId);

        return $userBank ? $this->number($userBank) : 0;
    }

    public function incomeFor(int $userBankId): float
    {
        $userBank = $this->findOrRefresh($userBankId);

        return $userBank ? $this->income($userBank) : 0;
    }

    private function findStats(int $userBankId): ?UserBank
    {
        if ($userBankId <= 0) {
            return null;
        }

        return UserBank::query()->select(['id', 'today_stat_date', 'today_total_amount', 'today_total_number', 'today_total_income'])->find($userBankId);
    }

    private function findOrRefresh(int $userBankId): ?UserBank
    {
        $userBank = $this->findStats($userBankId);
        if ($this->isToday($userBank)) {
            return $userBank;
        }

        app(UserTodayStatsRebuildService::class)->rebuild(null, $userBankId);

        return $this->findStats($userBankId);
    }

    private function isToday($userBank): bool
    {
        if (!$userBank || empty($userBank->today_stat_date)) {
            return false;
        }

        return date('Y-m-d', strtotime($userBank->today_stat_date)) === date('Y-m-d');
    }
}
