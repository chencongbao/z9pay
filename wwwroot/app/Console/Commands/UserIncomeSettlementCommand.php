<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Jobs\UserIncomeSettlementJob;

class UserIncomeSettlementCommand extends Command
{
    protected $signature = 'user:income-settlement';

    protected $description = '金主收益结算至保证金';

    public function handle(): int
    {
        $total = 0;

        User::query()
            ->select(['id'])
            ->where('is_agent', 0)
            ->where('status', 1)
            ->where('income_settlement_to_deposit_on', 1)
            ->where('commission_balance_amount', '>', 0)
            ->chunkById(1000, function ($users) use (&$total) {
                foreach ($users as $user) {
                    dispatch((new UserIncomeSettlementJob($user->id))->onQueue('query'));
                    $total++;
                }
            });

        $this->info("金主收益结算任务已派发，任务数：{$total}");

        return self::SUCCESS;
    }
}
