<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\User\GetUserDepositOrderDaifukuanAmountService;
use App\Services\User\GetUserRemainingDepositService;

class UserZeroBalanceSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?User $user = null;

    public ?int $userId = null;

    public $depositAmount;

    public $depositBalanceAmount;

    public $transferBalanceAmount;

    public $balanceAmount;

    public $commissionBalanceAmount;

    public function __construct(int $userId, $depositAmount, $depositBalanceAmount, $transferBalanceAmount, $balanceAmount = 0, $commissionBalanceAmount = 0)
    {
        $this->userId = $userId;
        $this->depositAmount = $depositAmount;
        $this->depositBalanceAmount = $depositBalanceAmount;
        $this->transferBalanceAmount = $transferBalanceAmount;
        $this->balanceAmount = $balanceAmount;
        $this->commissionBalanceAmount = $commissionBalanceAmount;
    }

    public function handle(): void
    {
        if (!$this->userId && $this->user) {
            $this->userId = $this->user->id;
            $this->depositAmount = $this->user->deposit_amount;
            $this->depositBalanceAmount = $this->user->deposit_balance_amount;
            $this->transferBalanceAmount = $this->user->transfer_balance_amount;
            $this->balanceAmount = $this->user->balance_amount;
            $this->commissionBalanceAmount = $this->user->commission_balance_amount;
        }

        if (!$this->userId) {
            return;
        }

        // 只按金主ID重建代收待付款缓存，余额字段使用派发时快照，避免队列执行时重新加载完整模型。
        $daishoukuan = App::make(GetUserDepositOrderDaifukuanAmountService::class)->excute($this->userId, true);
        $remainingDeposit = App::make(GetUserRemainingDepositService::class)->calculate($this->depositAmount, $this->transferBalanceAmount, $this->depositBalanceAmount, $daishoukuan);
        $zerosBalance = $remainingDeposit['remaining_deposit'];
        $dateAdd = now()->subDay()->toDateString();
        $endAt = $dateAdd . ' 23:59:59';

        DB::transaction(function () use ($dateAdd, $endAt, $daishoukuan, $zerosBalance) {
            DB::table('user_day_balance_logs')->upsert([
                [
                    'uid' => $this->userId,
                    'date_add' => $dateAdd,
                    'balance_amount' => $this->balanceAmount,
                    'deposit_balance_amount' => $this->depositBalanceAmount,
                    'transfer_balance_amount' => $this->transferBalanceAmount,
                    'commission_balance_amount' => $this->commissionBalanceAmount,
                    'deposit_amount' => $this->depositAmount,
                    'daifukuan_amount' => $daishoukuan,
                    'zeros_balance' => $zerosBalance,
                    'created_at' => $endAt,
                    'updated_at' => now(),
                ],
            ], ['uid', 'date_add'], [
                'balance_amount',
                'deposit_balance_amount',
                'transfer_balance_amount',
                'commission_balance_amount',
                'deposit_amount',
                'daifukuan_amount',
                'zeros_balance',
                'updated_at',
            ]);

            DB::table('users')->where('id', $this->userId)->whereNull('deleted_at')->update(['zeros_balance' => $zerosBalance]);
        });
    }
}
