<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DepositOrder;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use App\Jobs\UserIncomeSettlementJob;
use App\Jobs\DepositOrderTimeoutJob;
use App\Jobs\MerchantBalanceSnapshotJob;
use App\Jobs\UserZeroBalanceSnapshotJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\Cache\Config\CacheAdminSettingService;

class CommandDispatchSelectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_merchant_balance_snapshot_dispatches_only_active_not_deleted_merchants(): void
    {
        Queue::fake();
        $active = $this->createMerchant(1, null, '123.45', '6.78');
        $disabled = $this->createMerchant(0);
        $deleted = $this->createMerchant(1, now());

        $this->artisan('merchant:balance-snapshot')
            ->expectsOutputToContain('商户每日余额快照更新任务派发完成，任务数：')
            ->assertExitCode(0);

        Queue::assertPushedOn('query', MerchantBalanceSnapshotJob::class);
        Queue::assertPushed(MerchantBalanceSnapshotJob::class, fn ($job) => (int)$job->id === (int)$active->merchant_user_id
            && abs((float)$job->available_balance - 123.45) < 0.0001
            && abs((float)$job->available_usdt_balance - 6.78) < 0.0001);
        Queue::assertNotPushed(MerchantBalanceSnapshotJob::class, fn ($job) => (int)$job->id === (int)$disabled->merchant_user_id);
        Queue::assertNotPushed(MerchantBalanceSnapshotJob::class, fn ($job) => (int)$job->id === (int)$deleted->merchant_user_id);
    }

    public function test_user_zero_balance_snapshot_dispatches_only_active_non_agent_users_with_snapshot_fields(): void
    {
        Queue::fake();
        $target = $this->createUser([
            'is_agent' => 0,
            'status' => 1,
            'deposit_amount' => '100.00',
            'deposit_balance_amount' => '20.00',
            'transfer_balance_amount' => '30.00',
            'balance_amount' => '40.00',
            'commission_balance_amount' => '50.00',
        ]);
        $agent = $this->createUser(['is_agent' => 1, 'status' => 1]);
        $disabled = $this->createUser(['is_agent' => 0, 'status' => 0]);

        $this->artisan('user:zero-balance-snapshot')
            ->expectsOutputToContain('金主0点余额快照更新任务已派发，任务数：')
            ->assertExitCode(0);

        Queue::assertPushedOn('query', UserZeroBalanceSnapshotJob::class);
        Queue::assertPushed(UserZeroBalanceSnapshotJob::class, fn ($job) => $job->userId === (int)$target->id
            && abs((float)$job->depositAmount - 100.00) < 0.0001
            && abs((float)$job->depositBalanceAmount - 20.00) < 0.0001
            && abs((float)$job->transferBalanceAmount - 30.00) < 0.0001
            && abs((float)$job->balanceAmount - 40.00) < 0.0001
            && abs((float)$job->commissionBalanceAmount - 50.00) < 0.0001);
        Queue::assertNotPushed(UserZeroBalanceSnapshotJob::class, fn ($job) => $job->userId === (int)$agent->id);
        Queue::assertNotPushed(UserZeroBalanceSnapshotJob::class, fn ($job) => $job->userId === (int)$disabled->id);
    }

    public function test_user_income_settlement_dispatches_only_enabled_active_non_agent_users_with_commission(): void
    {
        Queue::fake();
        $target = $this->createUser(['is_agent' => 0, 'status' => 1, 'income_settlement_to_deposit_on' => 1, 'commission_balance_amount' => '12.34']);
        $agent = $this->createUser(['is_agent' => 1, 'status' => 1, 'income_settlement_to_deposit_on' => 1, 'commission_balance_amount' => '12.34']);
        $disabled = $this->createUser(['is_agent' => 0, 'status' => 0, 'income_settlement_to_deposit_on' => 1, 'commission_balance_amount' => '12.34']);
        $closed = $this->createUser(['is_agent' => 0, 'status' => 1, 'income_settlement_to_deposit_on' => 0, 'commission_balance_amount' => '12.34']);
        $zeroCommission = $this->createUser(['is_agent' => 0, 'status' => 1, 'income_settlement_to_deposit_on' => 1, 'commission_balance_amount' => '0.00']);

        $this->artisan('user:income-settlement')
            ->expectsOutputToContain('金主收益结算任务已派发，任务数：')
            ->assertExitCode(0);

        Queue::assertPushedOn('query', UserIncomeSettlementJob::class);
        Queue::assertPushed(UserIncomeSettlementJob::class, fn ($job) => $job->userId === (int)$target->id);
        Queue::assertNotPushed(UserIncomeSettlementJob::class, fn ($job) => $job->userId === (int)$agent->id);
        Queue::assertNotPushed(UserIncomeSettlementJob::class, fn ($job) => $job->userId === (int)$disabled->id);
        Queue::assertNotPushed(UserIncomeSettlementJob::class, fn ($job) => $job->userId === (int)$closed->id);
        Queue::assertNotPushed(UserIncomeSettlementJob::class, fn ($job) => $job->userId === (int)$zeroCommission->id);
    }

    public function test_deposit_payment_timeout_dispatches_only_currently_expired_orders(): void
    {
        Queue::fake();
        $now = time();
        $target = $this->createDepositOrder(['expired_time' => $now - 1, 'status' => 1, 'pay_status' => 1]);
        $equalNow = $this->createDepositOrder(['expired_time' => $now, 'status' => 1, 'pay_status' => 1]);
        $future = $this->createDepositOrder(['expired_time' => $now + 60, 'status' => 1, 'pay_status' => 1]);
        $zero = $this->createDepositOrder(['expired_time' => 0, 'status' => 1, 'pay_status' => 1]);
        $wrongStatus = $this->createDepositOrder(['expired_time' => $now - 1, 'status' => 5, 'pay_status' => 1]);
        $wrongPayStatus = $this->createDepositOrder(['expired_time' => $now - 1, 'status' => 1, 'pay_status' => 2]);

        $this->artisan('deposit:payment-timeout')
            ->expectsOutputToContain('已派发代收支付超时订单处理任务：')
            ->assertExitCode(0);

        Queue::assertPushedOn('query', DepositOrderTimeoutJob::class);
        Queue::assertPushed(DepositOrderTimeoutJob::class, fn ($job) => (int)$job->order_id === (int)$target->id
            && $job->timeout_type === 'payment'
            && $job->delay !== null);
        foreach ([$equalNow, $future, $zero, $wrongStatus, $wrongPayStatus] as $order) {
            Queue::assertNotPushed(DepositOrderTimeoutJob::class, fn ($job) => (int)$job->order_id === (int)$order->id);
        }
    }

    public function test_deposit_confirm_timeout_dispatches_only_confirm_expired_orders(): void
    {
        Queue::fake();
        $this->fakeConfirmTimeoutMinutes(1);
        $now = time();
        $target = $this->createDepositOrder(['confirm_time' => $now - 60, 'status' => 3, 'pay_status' => 2]);
        $targetStatus7 = $this->createDepositOrder(['confirm_time' => $now - 61, 'status' => 7, 'pay_status' => 2]);
        $notExpired = $this->createDepositOrder(['confirm_time' => $now - 59, 'status' => 3, 'pay_status' => 2]);
        $zero = $this->createDepositOrder(['confirm_time' => 0, 'status' => 3, 'pay_status' => 2]);
        $wrongStatus = $this->createDepositOrder(['confirm_time' => $now - 61, 'status' => 1, 'pay_status' => 2]);
        $wrongPayStatus = $this->createDepositOrder(['confirm_time' => $now - 61, 'status' => 3, 'pay_status' => 1]);

        $this->artisan('deposit:confirm-timeout')
            ->expectsOutputToContain('已派发代收确认超时订单处理任务：')
            ->assertExitCode(0);

        Queue::assertPushedOn('query', DepositOrderTimeoutJob::class);
        Queue::assertPushed(DepositOrderTimeoutJob::class, fn ($job) => in_array((int)$job->order_id, [(int)$target->id, (int)$targetStatus7->id], true)
            && $job->timeout_type === 'confirm'
            && $job->delay !== null);
        foreach ([$notExpired, $zero, $wrongStatus, $wrongPayStatus] as $order) {
            Queue::assertNotPushed(DepositOrderTimeoutJob::class, fn ($job) => (int)$job->order_id === (int)$order->id);
        }
    }

    private function createMerchant(int $status = 1, $deletedAt = null, string $availableBalance = '1.00', string $availableUsdtBalance = '2.00'): MerchantInfo
    {
        $suffix = str_replace('.', '_', uniqid('', true));
        $merchantUser = MerchantUser::query()->forceCreate([
            'username' => 'codex_merchant_' . $suffix,
            'password' => bcrypt('password'),
            'name' => 'Codex Merchant',
            'status' => $status,
            'deleted_at' => $deletedAt,
        ]);

        return MerchantInfo::query()->forceCreate([
            'merchant_user_id' => $merchantUser->id,
            'agent_user_id' => 0,
            'coder' => 'codex_coder_' . $suffix,
            'appkey' => 'codex_appkey_' . $suffix,
            'appsecret' => 'codex_appsecret_' . $suffix,
            'currency_id' => 1,
            'name' => 'Codex Merchant',
            'available_balance' => $availableBalance,
            'available_usdt_balance' => $availableUsdtBalance,
            'deleted_at' => $deletedAt,
        ]);
    }

    private function createUser(array $attributes = []): User
    {
        $suffix = str_replace('.', '_', uniqid('', true));

        return User::query()->forceCreate(array_merge([
            'username' => 'codex_user_' . $suffix,
            'password' => bcrypt('password'),
            'name' => 'Codex User',
            'is_agent' => 0,
            'status' => 1,
            'balance_amount' => '0.00',
            'deposit_amount' => '0.00',
            'deposit_balance_amount' => '0.00',
            'transfer_balance_amount' => '0.00',
            'commission_balance_amount' => '0.00',
            'income_settlement_to_deposit_on' => 0,
        ], $attributes));
    }

    private function createDepositOrder(array $attributes = []): DepositOrder
    {
        return DepositOrder::query()->forceCreate(array_merge([
            'ordernumber' => 'D' . date('YmdHis') . random_int(100000, 999999),
            'order_no' => 'CODEx' . random_int(100000, 999999),
            'mid' => 1,
            'user_id' => 0,
            'amount' => '10.00',
            'actual_amount' => '10.00',
            'status' => 1,
            'pay_status' => 1,
            'expired_time' => 0,
            'confirm_time' => 0,
        ], $attributes));
    }

    private function fakeConfirmTimeoutMinutes(int $minutes): void
    {
        $service = new class($minutes) {
            public function __construct(private int $minutes)
            {
            }

            public function excute($name, $value = null, bool $isSet = false)
            {
                return $name === 'base_deposit_confirm_overtime' ? $this->minutes : null;
            }
        };

        App::instance(CacheAdminSettingService::class, $service);
    }
}
