<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Models\UserDepositDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\User\UserBalanceChangeService;

class UserIncomeSettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $id = 0;

    public int $userId = 0;

    public function __construct(int $userId = 0)
    {
        $this->id = $userId;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $userId = $this->userId ?: (int) $this->id;
            $user = User::query()
                ->whereKey($userId)
                ->where('is_agent', 0)
                ->where('status', 1)
                ->where('income_settlement_to_deposit_on', 1)
                ->where('commission_balance_amount', '>', 0)
                ->lockForUpdate()
                ->first(['id', 'is_agent', 'commission_balance_amount']);

            if (!$user) {
                return;
            }

            $amount = (float) $user->commission_balance_amount;
            if ($amount <= 0) {
                return;
            }

            // 结清佣金账户后转入保证金，避免重复队列写入 0 金额流水。
            $remark = '系统自动结算金主收益到保证金';
            App::make(UserBalanceChangeService::class)->excute(['user_id' => $user->id, 'amount' => -$amount, 'type' => 10, 'remark' => $remark]);
            User::query()->whereKey($user->id)->increment('deposit_amount', $amount);
            UserDepositDetail::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'admin_id' => 0,
                'remark' => $remark,
            ]);
        });
    }
}
