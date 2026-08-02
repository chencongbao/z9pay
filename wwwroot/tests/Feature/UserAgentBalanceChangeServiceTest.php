<?php

namespace Tests\Feature;

use Exception;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\User\UserAgentBalanceChangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserAgentBalanceChangeServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manual_reduce_uses_agent_total_balance_only(): void
    {
        $agent = $this->createUserAgent([
            'balance_amount' => 16020.04,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserAgentBalanceChangeService::class)->excute([
            'user_id' => $agent->id,
            'amount' => 1000,
            'type' => 3,
            'remark' => 'Codex user agent manual reduce',
        ]);

        $agent->refresh();
        $this->assertSame('15020.04', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$agent->commission_balance_amount, 2, '.', ''));
        $this->assertSame('-1000.00', number_format((float)$log->amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$log->type_balance_amount, 2, '.', ''));
        $this->assertSame(1, (int)$log->is_agent);
        $this->assertSame(3, (int)$log->type);
    }

    public function test_manual_add_uses_agent_total_balance_only(): void
    {
        $agent = $this->createUserAgent([
            'balance_amount' => 100,
            'commission_balance_amount' => 0,
        ]);

        $log = app(UserAgentBalanceChangeService::class)->excute([
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
        $this->assertSame(4, (int)$log->type);
    }

    public function test_balance_log_correction_type_six_respects_reverse_amount_sign(): void
    {
        $agent = $this->createUserAgent(['balance_amount' => 100]);

        $reduceReverseLog = app(UserAgentBalanceChangeService::class)->excute([
            'user_id' => $agent->id,
            'amount' => 30,
            'type' => 6,
            'remark' => 'Codex reverse reduce',
        ]);

        $addReverseLog = app(UserAgentBalanceChangeService::class)->excute([
            'user_id' => $agent->id,
            'amount' => -20,
            'type' => 6,
            'remark' => 'Codex reverse add',
        ]);

        $agent->refresh();
        $this->assertSame('110.00', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float)$reduceReverseLog->amount, 2, '.', ''));
        $this->assertSame('-20.00', number_format((float)$addReverseLog->amount, 2, '.', ''));
    }

    public function test_manual_reduce_rejects_when_agent_balance_is_insufficient(): void
    {
        $agent = $this->createUserAgent(['balance_amount' => 10]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('金主代理余额不足');

        app(UserAgentBalanceChangeService::class)->excute([
            'user_id' => $agent->id,
            'amount' => 100,
            'type' => 3,
            'remark' => 'Codex user agent manual reduce insufficient',
        ]);
    }

    public function test_rejects_non_agent_user(): void
    {
        $user = $this->createUserAgent([
            'is_agent' => 0,
            'balance_amount' => 100,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('金主代理不存在');

        app(UserAgentBalanceChangeService::class)->excute([
            'user_id' => $user->id,
            'amount' => 10,
            'type' => 4,
            'remark' => 'Codex non agent reject',
        ]);
    }

    private function createUserAgent(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => '139' . mt_rand(10000000, 99999999),
            'password' => Hash::make('codex-password'),
            'name' => 'Codex金主代理余额测试',
            'mobile' => '139' . mt_rand(10000000, 99999999),
            'is_agent' => 1,
            'status' => 1,
            'acquisition_status' => 1,
            'balance_amount' => 0,
            'deposit_balance_amount' => 0,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
        ], $attributes));
    }
}
