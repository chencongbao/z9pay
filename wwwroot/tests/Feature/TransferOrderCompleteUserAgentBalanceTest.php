<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use App\Services\TransferOrder\TransferOrderCompleteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TransferOrderCompleteUserAgentBalanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_transfer_success_agent_commission_uses_user_agent_balance_service(): void
    {
        $user = $this->createUser(['balance_amount' => 100, 'transfer_balance_amount' => 20, 'commission_balance_amount' => 10]);
        $agent = $this->createUser(['is_agent' => 1, 'balance_amount' => 50, 'commission_balance_amount' => 0]);
        $order = $this->makeOrder($user->id, $agent->id, 200, 20, 15);

        $this->changeBalances($order, [
            'actual_amount' => 200,
            'user_commission' => 20,
            'user_agent1_commission' => 15,
            'user_agent2_commission' => 0,
            'user_agent3_commission' => 0,
            'user_agent4_commission' => 0,
            'user_agent5_commission' => 0,
        ], 7, 13, 2);

        $user->refresh();
        $agent->refresh();
        $this->assertSame('320.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('220.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('65.00', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$agent->commission_balance_amount, 2, '.', ''));
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $agent->id, 'type' => 2, 'amount' => 15, 'is_agent' => 1, 'type_balance_amount' => 0]);
    }

    public function test_settlement_success_agent_commission_uses_user_agent_balance_service(): void
    {
        $user = $this->createUser(['balance_amount' => 100, 'transfer_balance_amount' => 20, 'commission_balance_amount' => 10]);
        $agent = $this->createUser(['is_agent' => 1, 'balance_amount' => 50, 'commission_balance_amount' => 0]);
        $order = $this->makeOrder($user->id, $agent->id, 300, 30, 25);

        $this->changeBalances($order, [
            'actual_amount' => 300,
            'user_commission' => 30,
            'user_agent1_commission' => 25,
            'user_agent2_commission' => 0,
            'user_agent3_commission' => 0,
            'user_agent4_commission' => 0,
            'user_agent5_commission' => 0,
        ], 16, 14, 7);

        $user->refresh();
        $agent->refresh();
        $this->assertSame('430.00', number_format((float)$user->balance_amount, 2, '.', ''));
        $this->assertSame('320.00', number_format((float)$user->transfer_balance_amount, 2, '.', ''));
        $this->assertSame('40.00', number_format((float)$user->commission_balance_amount, 2, '.', ''));
        $this->assertSame('75.00', number_format((float)$agent->balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float)$agent->commission_balance_amount, 2, '.', ''));
        $this->assertDatabaseHas('user_balance_logs', ['user_id' => $agent->id, 'type' => 7, 'amount' => 25, 'is_agent' => 1, 'type_balance_amount' => 0]);
    }

    private function changeBalances(TransferOrder $order, array $data, int $userAmountType, int $userCommissionType, int $userAgentCommissionType): void
    {
        $service = new class extends TransferOrderCompleteService {
            public function apply(TransferOrder $order, array $data, int $userAmountType, int $userCommissionType, int $userAgentCommissionType): void
            {
                $this->changeUserBalance($order, $data, $userAmountType, $userCommissionType, $userAgentCommissionType);
            }
        };

        $service->apply($order, $data, $userAmountType, $userCommissionType, $userAgentCommissionType);
    }

    private function makeOrder(int $userId, int $agentId, float $actualAmount, float $userCommission, float $agentCommission): TransferOrder
    {
        return new TransferOrder([
            'id' => 90101,
            'mid' => 24,
            'user_id' => $userId,
            'actual_amount' => $actualAmount,
            'user_commission' => $userCommission,
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
            'ordernumber' => 'CODEX-TRANSFER-COMPLETE-ORDER',
        ]);
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => '139' . mt_rand(10000000, 99999999),
            'password' => Hash::make('codex-password'),
            'name' => 'Codex代付成功金主代理测试',
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
