<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Jobs\UserZeroBalanceSnapshotJob;

class UserZeroBalanceSnapshotCommand extends Command
{
    protected $signature = 'user:zero-balance-snapshot';

    protected $description = '更新金主0点余额快照';

    public function handle(): int
    {
        $total = 0;
        User::query()
            ->select(['id', 'balance_amount', 'deposit_balance_amount', 'transfer_balance_amount', 'commission_balance_amount', 'deposit_amount'])
            ->where('is_agent', 0)
            ->where('status', 1)
            ->chunkById(1000, function ($users) use (&$total) {
                foreach ($users as $user) {
                    dispatch((new UserZeroBalanceSnapshotJob($user->id, $user->deposit_amount, $user->deposit_balance_amount, $user->transfer_balance_amount, $user->balance_amount, $user->commission_balance_amount))->onQueue('query'));
                    $total++;
                }
            });

        $this->info("金主0点余额快照更新任务已派发，任务数：{$total}");
        return 0;
    }
}
