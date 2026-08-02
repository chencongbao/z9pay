<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\UserBank;
use App\Models\DepositOrder;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UserTodayStatsRebuildService
{
    public function rebuild(?int $userId = null, ?int $userBankId = null): array
    {
        $lock = Cache::lock($this->lockKey(), 120);

        return $lock->block(3, fn () => $this->rebuildLocked($userId, $userBankId));
    }

    public function runWithRebuildLock(callable $callback)
    {
        $lock = Cache::lock($this->lockKey(), 120);

        return $lock->block(30, $callback);
    }

    private function rebuildLocked(?int $userId = null, ?int $userBankId = null): array
    {
        $today = date('Y-m-d');
        $startTime = strtotime($today . ' 00:00:00');
        $endTime = strtotime($today . ' +1 day');
        $userStats = $userBankId !== null && $userId === null ? [] : $this->buildUserStats($startTime, $endTime, $userId);
        $userBankStats = $this->buildUserBankStats($startTime, $endTime, $userId, $userBankId);

        return DB::transaction(function () use ($today, $userId, $userBankId, $userStats, $userBankStats) {
            $pendingStats = $userBankId !== null && $userId === null ? 0 : $this->rebuildPendingDepositStats($userId);

            return [
                'date' => $today,
                'users' => $userBankId !== null && $userId === null ? 0 : $this->writeUsers($today, $userStats, $userId),
                'user_banks' => $this->writeUserBanks($today, $userBankStats, $userId, $userBankId),
                'pending_deposit_users' => $pendingStats,
            ];
        });
    }

    private function writeUsers(string $today, array $stats, ?int $userId = null): int
    {
        $resetQuery = User::query();
        if ($userId !== null && $userId > 0) {
            $resetQuery->whereKey($userId);
        }

        $resetCount = $resetQuery->update([
            'today_deposit_stat_date' => $today,
            'today_deposit_total_number' => 0,
            'today_deposit_total_amount' => 0,
            'today_deposit_total_income' => 0,
            'today_transfer_stat_date' => $today,
            'today_transfer_total_number' => 0,
            'today_transfer_total_amount' => 0,
            'today_transfer_total_income' => 0,
        ]);

        foreach ($stats as $id => $row) {
            User::query()->whereKey((int)$id)->update([
                'today_deposit_stat_date' => $today,
                'today_deposit_total_number' => (int)$row['deposit_number'],
                'today_deposit_total_amount' => bob_amount_format($row['deposit_amount']),
                'today_deposit_total_income' => bob_amount_format($row['deposit_income']),
                'today_transfer_stat_date' => $today,
                'today_transfer_total_number' => (int)$row['transfer_number'],
                'today_transfer_total_amount' => bob_amount_format($row['transfer_amount']),
                'today_transfer_total_income' => bob_amount_format($row['transfer_income']),
            ]);
        }

        return $resetCount;
    }

    private function buildUserStats(int $startTime, int $endTime, ?int $userId = null): array
    {
        $stats = [];
        $this->fillDepositUserStats($stats, $startTime, $endTime, $userId);
        $this->fillTransferUserStats($stats, $startTime, $endTime, $userId);

        return $stats;
    }

    private function fillDepositUserStats(array &$stats, int $startTime, int $endTime, ?int $userId = null): void
    {
        $this->depositUserQuery('user_id', 'user_commission', $startTime, $endTime, $userId)
            ->chunk(1000, function ($rows) use (&$stats) {
                foreach ($rows as $row) {
                    $this->mergeUserStats($stats, $row, 'deposit');
                }
            });

        foreach ([1, 2, 3, 4, 5] as $level) {
            $this->depositUserQuery("user_agent{$level}_id", "user_agent{$level}_commission", $startTime, $endTime, $userId)
                ->chunk(1000, function ($rows) use (&$stats) {
                    foreach ($rows as $row) {
                        $this->mergeUserStats($stats, $row, 'deposit');
                    }
                });
        }
    }

    private function fillTransferUserStats(array &$stats, int $startTime, int $endTime, ?int $userId = null): void
    {
        $this->transferUserQuery('user_id', 'user_commission', $startTime, $endTime, $userId)
            ->chunk(1000, function ($rows) use (&$stats) {
                foreach ($rows as $row) {
                    $this->mergeUserStats($stats, $row, 'transfer');
                }
            });

        foreach ([1, 2, 3, 4, 5] as $level) {
            $this->transferUserQuery("user_agent{$level}_id", "user_agent{$level}_commission", $startTime, $endTime, $userId)
                ->chunk(1000, function ($rows) use (&$stats) {
                    foreach ($rows as $row) {
                        $this->mergeUserStats($stats, $row, 'transfer');
                    }
                });
        }
    }

    private function writeUserBanks(string $today, array $stats, ?int $userId = null, ?int $userBankId = null): int
    {
        $resetQuery = UserBank::query();
        if ($userId !== null && $userId > 0) {
            $resetQuery->where('user_id', $userId);
        }
        if ($userBankId !== null && $userBankId > 0) {
            $resetQuery->whereKey($userBankId);
        }

        $resetCount = $resetQuery->update([
            'today_stat_date' => $today,
            'today_total_amount' => 0,
            'today_total_number' => 0,
            'today_total_income' => 0,
        ]);

        foreach ($stats as $id => $row) {
            UserBank::query()->whereKey((int)$id)->update([
                'today_stat_date' => $today,
                'today_total_number' => (int)$row['number'],
                'today_total_amount' => bob_amount_format($row['amount']),
                'today_total_income' => bob_amount_format($row['income']),
            ]);
        }

        return $resetCount;
    }

    private function buildUserBankStats(int $startTime, int $endTime, ?int $userId = null, ?int $userBankId = null): array
    {
        $stats = [];
        $query = DepositOrder::query()
            ->where('status', 5)
            ->where('user_bank_id', '>', 0)
            ->where('success_time', '>=', $startTime)
            ->where('success_time', '<', $endTime);
        if ($userId !== null && $userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($userBankId !== null && $userBankId > 0) {
            $query->where('user_bank_id', $userBankId);
        }

        $query->groupBy('user_bank_id')
            ->selectRaw('user_bank_id, COUNT(*) as total_number, COALESCE(SUM(actual_amount), 0) as total_amount, COALESCE(SUM(user_commission), 0) as total_income')
            ->orderBy('user_bank_id')
            ->chunk(1000, function ($rows) use (&$stats) {
                foreach ($rows as $row) {
                    $stats[(int)$row->user_bank_id] = [
                        'number' => (int)$row->total_number,
                        'amount' => bob_amount_format($row->total_amount ?? 0),
                        'income' => bob_amount_format($row->total_income ?? 0),
                    ];
                }
            });

        return $stats;
    }

    private function rebuildPendingDepositStats(?int $userId = null): int
    {
        $resetQuery = User::query()->where('is_agent', 0);
        if ($userId !== null && $userId > 0) {
            $resetQuery->whereKey($userId);
        }

        $resetCount = $resetQuery->update([
            'pending_deposit_order_amount' => 0,
            'pending_deposit_order_count' => 0,
        ]);

        $query = DepositOrder::query()
            ->whereIn('status', UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES)
            ->where('user_id', '>', 0);
        if ($userId !== null && $userId > 0) {
            $query->where('user_id', $userId);
        }

        $query->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->orderBy('user_id')
            ->chunk(1000, function ($rows) {
                foreach ($rows as $row) {
                    User::query()->whereKey((int) $row->user_id)->where('is_agent', 0)->update([
                        'pending_deposit_order_amount' => bob_amount_format($row->total_amount ?? 0),
                        'pending_deposit_order_count' => (int) $row->total_count,
                    ]);
                }
            });

        return $resetCount;
    }

    private function depositUserQuery(string $userField, string $incomeField, int $startTime, int $endTime, ?int $userId = null)
    {
        $query = DepositOrder::query()
            ->where('status', 5)
            ->where($userField, '>', 0)
            ->where('success_time', '>=', $startTime)
            ->where('success_time', '<', $endTime);
        if ($userId !== null && $userId > 0) {
            $query->where($userField, $userId);
        }

        return $query->groupBy($userField)
            ->selectRaw("{$userField} as user_id, COUNT(*) as total_number, COALESCE(SUM(actual_amount), 0) as total_amount, COALESCE(SUM({$incomeField}), 0) as total_income")
            ->orderBy($userField);
    }

    private function transferUserQuery(string $userField, string $incomeField, int $startTime, int $endTime, ?int $userId = null)
    {
        $query = TransferOrder::query()
            ->where('type', 0)
            ->where('status', 4)
            ->where($userField, '>', 0)
            ->where('success_time', '>=', $startTime)
            ->where('success_time', '<', $endTime);
        if ($userId !== null && $userId > 0) {
            $query->where($userField, $userId);
        }

        return $query->groupBy($userField)
            ->selectRaw("{$userField} as user_id, COUNT(*) as total_number, COALESCE(SUM(actual_amount), 0) as total_amount, COALESCE(SUM({$incomeField}), 0) as total_income")
            ->orderBy($userField);
    }

    private function mergeUserStats(array &$stats, $row, string $type): void
    {
        $userId = (int)$row->user_id;
        if (!isset($stats[$userId])) {
            $stats[$userId] = $this->emptyUserStats();
        }

        $stats[$userId]["{$type}_number"] += (int)$row->total_number;
        $stats[$userId]["{$type}_amount"] = bob_amount_format($stats[$userId]["{$type}_amount"] + bob_amount_format($row->total_amount ?? 0));
        $stats[$userId]["{$type}_income"] = bob_amount_format($stats[$userId]["{$type}_income"] + bob_amount_format($row->total_income ?? 0));
    }

    private function emptyUserStats(): array
    {
        return [
            'deposit_number' => 0,
            'deposit_amount' => 0,
            'deposit_income' => 0,
            'transfer_number' => 0,
            'transfer_amount' => 0,
            'transfer_income' => 0,
        ];
    }

    private function lockKey(): string
    {
        return 'user_today_stats_rebuild';
    }
}
