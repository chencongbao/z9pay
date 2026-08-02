<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class MerchantBalanceSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $id = 0;

    public $available_balance = 0;

    public $available_usdt_balance = 0;

    public function __construct($id = 0, $available_balance = 0, $available_usdt_balance = 0)
    {
        $this->id = $id;
        $this->available_balance = $available_balance;
        $this->available_usdt_balance = $available_usdt_balance;
    }

    public function handle(): void
    {
        $merchantUserId = (int)$this->id;
        if ($merchantUserId <= 0) {
            return;
        }

        $dateAdd = now()->subDay()->toDateString();
        $endAt = $dateAdd . ' 23:59:59';

        // 默认使用当前商户余额；如果存在余额流水，则以最后一条流水作为昨日结余快照。
        $merchantData = [
            'history_balance_amount' => $this->available_balance,
            'history_end_balance_amount_time' => $endAt,
        ];
        $dayBalanceData = [
            'balance_amount' => $this->available_balance,
            'usdt_balance_amount' => $this->available_usdt_balance,
        ];
        $log = DB::table('merchant_balance_logs')
            ->where('mid', $merchantUserId)
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $endAt)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['created_at', 'usdt_balance_amount', 'balance_amount']);

        if ($log) {
            $merchantData['last_balance_amount_time'] = $log->created_at;
            $merchantData['history_balance_amount'] = $log->balance_amount;
            $dayBalanceData['balance_amount'] = $log->balance_amount;
            $dayBalanceData['usdt_balance_amount'] = $log->usdt_balance_amount ?? $this->available_usdt_balance;
        }

        DB::transaction(function () use ($merchantUserId, $dateAdd, $endAt, $merchantData, $dayBalanceData) {
            DB::table('merchant_day_balance_logs')->upsert([
                [
                    'mid' => $merchantUserId,
                    'date_add' => $dateAdd,
                    'balance_amount' => $dayBalanceData['balance_amount'],
                    'usdt_balance_amount' => $dayBalanceData['usdt_balance_amount'],
                    'created_at' => $endAt,
                    'updated_at' => now(),
                ],
            ], ['mid', 'date_add'], ['balance_amount', 'usdt_balance_amount', 'updated_at']);

            DB::table('merchant_infos')->where('merchant_user_id', $merchantUserId)->whereNull('deleted_at')->update($merchantData);
        });
    }
}
