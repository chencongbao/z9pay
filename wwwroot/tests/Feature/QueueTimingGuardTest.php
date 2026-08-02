<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\DepositOrderTimeoutJob;
use App\Jobs\UserIncomeSettlementJob;
use App\Jobs\MerchantBalanceSnapshotJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QueueTimingGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_merchant_balance_snapshot_ignores_logs_after_snapshot_day_end(): void
    {
        $merchantId = 910000 + random_int(1, 9999);
        $yesterday = now()->subDay()->toDateString();

        DB::table('merchant_infos')->insert([
            'merchant_user_id' => $merchantId,
            'name' => 'Codex Snapshot Merchant',
            'available_balance' => 100,
            'available_usdt_balance' => 10,
        ]);
        DB::table('merchant_balance_logs')->insert([
            'mid' => $merchantId,
            'balance_amount' => 321,
            'usdt_balance_amount' => 32,
            'created_at' => "{$yesterday} 23:59:58",
            'updated_at' => "{$yesterday} 23:59:58",
        ]);
        DB::table('merchant_balance_logs')->insert([
            'mid' => $merchantId,
            'balance_amount' => 999,
            'usdt_balance_amount' => 99,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        (new MerchantBalanceSnapshotJob($merchantId, 100, 10))->handle();

        $snapshot = DB::table('merchant_day_balance_logs')->where('mid', $merchantId)->where('date_add', $yesterday)->first();
        $merchant = DB::table('merchant_infos')->where('merchant_user_id', $merchantId)->first();

        $this->assertSame('321.00', number_format((float)$snapshot->balance_amount, 2, '.', ''));
        $this->assertSame('32.00', number_format((float)$snapshot->usdt_balance_amount, 2, '.', ''));
        $this->assertSame('321.00', number_format((float)$merchant->history_balance_amount, 2, '.', ''));
    }

    public function test_user_income_settlement_rechecks_user_business_state_when_job_runs(): void
    {
        foreach ([
            ['is_agent' => 1, 'status' => 1, 'income_settlement_to_deposit_on' => 1],
            ['is_agent' => 0, 'status' => 0, 'income_settlement_to_deposit_on' => 1],
            ['is_agent' => 0, 'status' => 1, 'income_settlement_to_deposit_on' => 0],
        ] as $state) {
            $userId = $this->createUserForIncomeSettlement($state);

            (new UserIncomeSettlementJob($userId))->handle();

            $user = DB::table('users')->where('id', $userId)->first();
            $this->assertSame('100.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
            $this->assertSame('0.00', number_format((float)$user->deposit_amount, 2, '.', ''));
            $this->assertSame(0, DB::table('user_deposit_details')->where('user_id', $userId)->count());
        }
    }

    public function test_user_income_settlement_still_settles_when_state_is_valid(): void
    {
        $userId = $this->createUserForIncomeSettlement([
            'is_agent' => 0,
            'status' => 1,
            'income_settlement_to_deposit_on' => 1,
        ]);

        (new UserIncomeSettlementJob($userId))->handle();

        $user = DB::table('users')->where('id', $userId)->first();
        $this->assertSame('0.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('100.00', number_format((float)$user->deposit_amount, 2, '.', ''));
        $this->assertSame(1, DB::table('user_deposit_details')->where('user_id', $userId)->count());
    }

    public function test_payment_timeout_job_does_not_timeout_order_after_expired_time_is_extended(): void
    {
        $orderId = $this->createDepositOrder([
            'status' => 1,
            'pay_status' => 1,
            'expired_time' => time() + 3600,
        ]);

        (new DepositOrderTimeoutJob($orderId))->handle();

        $this->assertSame(1, (int)DB::table('deposit_orders')->where('id', $orderId)->value('status'));
    }

    public function test_confirm_timeout_job_does_not_timeout_order_after_confirm_time_is_refreshed(): void
    {
        bob_admin_setting('base_deposit_confirm_overtime', 10);
        $orderId = $this->createDepositOrder([
            'status' => 3,
            'pay_status' => 2,
            'confirm_time' => time(),
        ]);

        (new DepositOrderTimeoutJob($orderId, 'confirm'))->handle();

        $this->assertSame(3, (int)DB::table('deposit_orders')->where('id', $orderId)->value('status'));
    }

    private function createUserForIncomeSettlement(array $state): int
    {
        return (int)DB::table('users')->insertGetId(array_merge([
            'username' => 'codex_income_' . uniqid(),
            'name' => 'Codex Income User',
            'password' => 'secret',
            'balance_amount' => 100,
            'commission_balance_amount' => 100,
            'deposit_amount' => 0,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $state));
    }

    private function createDepositOrder(array $data): int
    {
        $now = now();

        return (int)DB::table('deposit_orders')->insertGetId(array_merge([
            'mid' => 24,
            'user_id' => 0,
            'payment_id' => 1,
            'channel_id' => 0,
            'ordernumber' => 'D' . date('YmdHis') . random_int(100000, 999999),
            'order_no' => 'COD' . uniqid(),
            'amount' => 100,
            'actual_amount' => 100,
            'pay_amount' => 100,
            'show_amount' => 100,
            'status' => 1,
            'pay_status' => 1,
            'expired_time' => time() - 60,
            'confirm_time' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $data));
    }
}
