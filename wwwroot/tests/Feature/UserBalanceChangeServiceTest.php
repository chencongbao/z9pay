<?php

namespace Tests\Feature;

use Exception;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use App\Services\User\UserBalanceChangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserBalanceChangeServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_user_agent_manual_reduce_uses_total_balance_not_commission_balance(): void
    {
        $this->assertSame('人工减项', config('default.agent_balance_type.3'));
        $agent = $this->createUser([
            'is_agent' => 1,
            'balance_amount' => 16020.04,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $agent->id,
            'amount' => -1000,
            'type' => 3,
            'remark' => 'Codex user agent manual reduce',
        ]);

        $agent->refresh();
        $this->assertSame('15020.04', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$agent->commission_balance_amount, 2, '.', ''));
        $this->assertSame('-1000.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
        $this->assertSame(1, (int)$log->is_agent);
    }

    public function test_user_agent_manual_add_uses_total_balance_not_commission_balance(): void
    {
        $this->assertSame('人工加项', config('default.agent_balance_type.4'));
        $agent = $this->createUser([
            'is_agent' => 1,
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $agent->id,
            'amount' => 60,
            'type' => 4,
            'remark' => 'Codex user agent manual add',
        ]);

        $agent->refresh();
        $this->assertSame('160.00', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$agent->commission_balance_amount, 2, '.', ''));
        $this->assertSame('60.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
        $this->assertSame(1, (int)$log->is_agent);
    }

    public function test_transfer_reduce_only_checks_transfer_balance(): void
    {
        $this->assertSame('代付账户减项', config('default.user_balance_type.8'));
        $user = $this->createUser([
            'balance_amount' => 0,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 100,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 8,
            'balance_account' => 'transfer',
            'remark' => 'Codex transfer reduce',
        ]);

        $user->refresh();
        $this->assertSame('-50.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('-50.00', number_format((float)$log->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_transfer_reduce_allows_negative_transfer_balance(): void
    {
        $user = $this->createUser([
            'balance_amount' => 1000,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 10,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 8,
            'balance_account' => 'transfer',
            'remark' => 'Codex transfer reduce',
        ]);

        $user->refresh();
        $this->assertSame('950.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('-40.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('-50.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('-40.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_deposit_reduce_decreases_deposit_account_and_increases_total_balance(): void
    {
        $this->assertSame('代收账户减项', config('default.user_balance_type.5'));
        $user = $this->createUser([
            'balance_amount' => 0,
            'deposit_balance_amount' => 100,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 5,
            'balance_account' => 'deposit',
            'remark' => 'Codex deposit reduce',
        ]);

        $user->refresh();
        $this->assertSame('50.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$user->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_deposit_reduce_allows_negative_deposit_balance(): void
    {
        $user = $this->createUser([
            'balance_amount' => 1000,
            'deposit_balance_amount' => 10,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 5,
            'balance_account' => 'deposit',
            'remark' => 'Codex deposit reduce',
        ]);

        $user->refresh();
        $this->assertSame('1050.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('-40.00', number_format((float)$user->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('-40.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_commission_reduce_only_checks_commission_balance(): void
    {
        $this->assertSame('佣金账户减项', config('default.user_balance_type.2'));
        $user = $this->createUser([
            'balance_amount' => 0,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 100,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => -50,
            'type' => 2,
            'balance_account' => 'commission',
            'remark' => 'Codex commission reduce',
        ]);

        $user->refresh();
        $this->assertSame('-50.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('-50.00', number_format((float)$log->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_commission_reduce_still_rejects_when_commission_balance_is_insufficient(): void
    {
        $user = $this->createUser([
            'balance_amount' => 1000,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 10,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('佣金余额不足');

        app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => -50,
            'type' => 2,
            'balance_account' => 'commission',
            'remark' => 'Codex commission reduce',
        ]);
    }

    public function test_deposit_success_type_four_reduces_total_balance_but_increases_deposit_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 100,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => -60,
            'type' => 4,
            'remark' => 'Codex deposit success reduce',
        ]);

        $user->refresh();
        $this->assertSame('40.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('160.00', number_format((float)$user->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('-60.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('160.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_deposit_success_type_four_does_not_check_deposit_account_balance(): void
    {
        $user = $this->createUser([
            'balance_amount' => 0,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => -60,
            'type' => 4,
            'remark' => 'Codex deposit success no deposit balance check',
        ]);

        $user->refresh();
        $this->assertSame('-60.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('60.00', number_format((float)$user->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('-60.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('60.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_deposit_add_increases_deposit_account_and_reduces_total_balance(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 20,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 6,
            'balance_account' => 'deposit',
            'remark' => 'Codex deposit add',
        ]);

        $user->refresh();
        $this->assertSame('50.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$user->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('-50.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->balance_amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_deposit_commission_increases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 30,
            'type' => 1,
            'remark' => 'Codex deposit commission add',
        ]);

        $user->refresh();
        $this->assertSame('130.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_transfer_commission_increases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 30,
            'type' => 13,
            'remark' => 'Codex transfer commission add',
        ]);

        $user->refresh();
        $this->assertSame('130.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_settlement_commission_increases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 30,
            'type' => 14,
            'remark' => 'Codex settlement commission add',
        ]);

        $user->refresh();
        $this->assertSame('130.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_commission_add_increases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 30,
            'type' => 3,
            'balance_account' => 'commission',
            'remark' => 'Codex commission add',
        ]);

        $user->refresh();
        $this->assertSame('130.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_commission_reduce_decreases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => -10,
            'type' => 2,
            'balance_account' => 'commission',
            'remark' => 'Codex commission reduce',
        ]);

        $user->refresh();
        $this->assertSame('90.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('-10.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_commission_settlement_decreases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => -10,
            'type' => 10,
            'remark' => 'Codex commission settlement',
        ]);

        $user->refresh();
        $this->assertSame('90.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('-10.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_transfer_commission_reverse_decreases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 10,
            'type' => 11,
            'balance_account' => 'commission',
            'remark' => 'Codex transfer commission reverse',
        ]);

        $user->refresh();
        $this->assertSame('90.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('-10.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_settlement_reverse_decreases_total_balance_and_commission_account(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 20,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 10,
            'type' => 15,
            'balance_account' => 'commission',
            'remark' => 'Codex settlement reverse',
        ]);

        $user->refresh();
        $this->assertSame('90.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('-10.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_transfer_add_increases_transfer_account_and_total_balance(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 20,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 9,
            'balance_account' => 'transfer',
            'remark' => 'Codex transfer add',
        ]);

        $user->refresh();
        $this->assertSame('150.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('150.00', number_format((float)$log->balance_amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_transfer_success_increases_transfer_account_and_total_balance(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 20,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 7,
            'remark' => 'Codex transfer success',
        ]);

        $user->refresh();
        $this->assertSame('150.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_settlement_success_increases_transfer_account_and_total_balance(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 20,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 16,
            'remark' => 'Codex settlement success',
        ]);

        $user->refresh();
        $this->assertSame('150.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('50.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_balance_log_correction_type_twelve_follows_explicit_account_and_amount(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 20,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => -10,
            'type' => 12,
            'balance_account' => 'deposit',
            'remark' => 'Codex balance log correction',
        ]);

        $user->refresh();
        $this->assertSame('90.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$user->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('-10.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    public function test_deposit_success_type_four_still_allows_positive_manual_add(): void
    {
        $user = $this->createUser([
            'balance_amount' => 100,
            'deposit_balance_amount' => 100,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 60,
            'type' => 4,
            'remark' => 'Codex manual add remains positive',
        ]);

        $user->refresh();
        $this->assertSame('160.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('160.00', number_format((float)$user->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('60.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('160.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => '139' . mt_rand(10000000, 99999999),
            'password' => Hash::make('codex-password'),
            'name' => 'Codex金主余额测试',
            'mobile' => '139' . mt_rand(10000000, 99999999),
            'is_agent' => 0,
            'status' => 1,
            'acquisition_status' => 1,
            'balance_amount' => 0,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ], $attributes));
    }
}
