<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Controllers\Api\V2\AgentUserController;
use App\Http\Requests\Api\V2\DepositOrderConfirmPayRequest;

class AgentConfirmPayScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        app('db')->purge('sqlite');

        Schema::connection('sqlite')->create('deposit_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_agent1_id')->default(0);
            $table->integer('user_bank_id')->default(0);
            $table->tinyInteger('status')->default(3);
            $table->decimal('pay_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('ordernumber');
            $table->timestamps();
        });
    }

    public function test_agent_cannot_confirm_another_agents_order(): void
    {
        $orderId = app('db')->connection('sqlite')->table('deposit_orders')->insertGetId([
            'user_agent1_id' => 77,
            'status' => 3,
            'ordernumber' => 'D-OTHER-AGENT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $agent = new User(['id' => 58]);
        $request = DepositOrderConfirmPayRequest::create('/api/v2/agent-users/confirm-pay', 'POST', [
            'order_id' => $orderId,
            'amount' => '100.00',
        ]);
        $request->setUserResolver(fn () => $agent);

        $payload = app(AgentUserController::class)->confirmPay($request)->getData(true);

        $this->assertSame('非法操作', $payload['message']);
        $status = app('db')->connection('sqlite')->table('deposit_orders')->where('id', $orderId)->value('status');
        $this->assertSame(3, (int) $status);
    }

    public function test_agent_cannot_confirm_team_order_with_mismatched_amount(): void
    {
        $orderId = app('db')->connection('sqlite')->table('deposit_orders')->insertGetId([
            'user_agent1_id' => 58,
            'status' => 3,
            'pay_amount' => 100,
            'amount' => 100,
            'ordernumber' => 'D-WRONG-AMOUNT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $agent = new User(['id' => 58]);
        $request = DepositOrderConfirmPayRequest::create('/api/v2/agent-users/confirm-pay', 'POST', [
            'order_id' => $orderId,
            'amount' => '999.00',
        ]);
        $request->setUserResolver(fn () => $agent);

        $payload = app(AgentUserController::class)->confirmPay($request)->getData(true);

        $this->assertSame('确认金额与待支付金额不一致，请联系客服处理', $payload['message']);
        $status = app('db')->connection('sqlite')->table('deposit_orders')->where('id', $orderId)->value('status');
        $this->assertSame(3, (int) $status);
    }
}
