<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use App\Services\TransferOrder\TransferOrderReverseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TransferOrderReverseUserBalanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_transfer_reverse_reverses_success_amount_and_commission(): void
    {
        $user = $this->createUser([
            'balance_amount' => 1000,
            'transfer_balance_amount' => 500,
            'commission_balance_amount' => 100,
        ]);
        $agent = $this->createUser([
            'is_agent' => 1,
            'balance_amount' => 100,
            'commission_balance_amount' => 0,
        ]);
        $order = $this->makeOrder($user->id, 200, 20, $agent->id, 15);

        $this->reverseUserSide($order, [
            'user_amount_reverse_type' => 8,
            'user_amount_reverse_remark' => '代付本金冲正',
            'user_commission_reverse_type' => 11,
            'user_agent_commission_reverse_type' => 5,
            'user_commission_reverse_remark' => '代付佣金冲正',
        ]);

        $user->refresh();
        $this->assertSame('780.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('300.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('80.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $agent->refresh();
        $this->assertSame('85.00', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$agent->commission_balance_amount, 2, '.', ''));
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $user->id, 'type' => 8, 'amount' => -200]);
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $user->id, 'type' => 11, 'amount' => -20]);
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $agent->id, 'type' => 5, 'amount' => -15, 'is_agent' => 1]);
    }

    public function test_settlement_reverse_reverses_success_amount_and_commission(): void
    {
        $user = $this->createUser([
            'balance_amount' => 1000,
            'transfer_balance_amount' => 500,
            'commission_balance_amount' => 100,
        ]);
        $agent = $this->createUser([
            'is_agent' => 1,
            'balance_amount' => 100,
            'commission_balance_amount' => 0,
        ]);
        $order = $this->makeOrder($user->id, 300, 30, $agent->id, 25);

        $this->reverseUserSide($order, [
            'user_amount_reverse_type' => 15,
            'user_amount_reverse_remark' => '结算本金冲正',
            'user_commission_reverse_type' => 15,
            'user_agent_commission_reverse_type' => 8,
            'user_commission_reverse_remark' => '结算佣金冲正',
        ]);

        $user->refresh();
        $this->assertSame('670.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('200.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $agent->refresh();
        $this->assertSame('75.00', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$agent->commission_balance_amount, 2, '.', ''));
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $user->id, 'type' => 15, 'amount' => -300, 'remark' => '结算本金冲正']);
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $user->id, 'type' => 15, 'amount' => -30, 'remark' => '结算佣金冲正']);
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $agent->id, 'type' => 8, 'amount' => -25, 'remark' => '结算佣金冲正', 'is_agent' => 1]);
    }

    private function reverseUserSide(TransferOrder $order, array $config): void
    {
        $service = new class extends TransferOrderReverseService {
            public function apply(TransferOrder $order, array $config): void
            {
                $this->reverseUserTransferAmount($order, $config);
                $this->reverseUserCommission($order, $config);
            }
        };

        $service->apply($order, $config);
    }

    private function makeOrder(int $userId, float $actualAmount, float $commission, int $agentId = 0, float $agentCommission = 0): TransferOrder
    {
        return new TransferOrder([
            'id' => 90001,
            'mid' => 24,
            'user_id' => $userId,
            'actual_amount' => $actualAmount,
            'user_commission' => $commission,
            'user_agent1_id' => $agentId,
            'user_agent1_commission' => $agentCommission,
            'user_agent2_id' => 0,
            'user_agent2_commission' => 0,
            'user_agent3_id' => 0,
            'user_agent3_commission' => 0,
            'user_agent4_id' => 0,
            'user_agent4_commission' => 0,
            'user_agent5_id' => 0,
            'user_agent5_commission' => 0,
            'ordernumber' => 'CODEX-REVERSE-ORDER',
        ]);
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => '139' . mt_rand(10000000, 99999999),
            'password' => Hash::make('codex-password'),
            'name' => 'Codex金主冲正测试',
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
