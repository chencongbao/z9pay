<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Controllers\Api\V2\TransferOrderController;
use App\Http\Requests\Api\V2\TransferOrdersubmitOrderRequest;

class TransferOrderCancelStatusTest extends TestCase
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
            'system-log.enabled' => false,
        ]);
        app('db')->purge('sqlite');

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username');
            $table->tinyInteger('is_agent')->default(0);
            $table->integer('pid')->default(0);
            $table->decimal('transfer_user_rate', 10, 2)->default(0);
            $table->decimal('user_rate', 10, 2)->default(0);
            $table->decimal('transfer_agent1_rate', 10, 2)->default(0);
            $table->decimal('agent1_rate', 10, 2)->default(0);
            $table->decimal('transfer_agent2_rate', 10, 2)->default(0);
            $table->decimal('agent2_rate', 10, 2)->default(0);
            $table->decimal('transfer_agent3_rate', 10, 2)->default(0);
            $table->decimal('agent3_rate', 10, 2)->default(0);
            $table->decimal('transfer_agent4_rate', 10, 2)->default(0);
            $table->decimal('agent4_rate', 10, 2)->default(0);
            $table->decimal('transfer_agent5_rate', 10, 2)->default(0);
            $table->decimal('agent5_rate', 10, 2)->default(0);
            $table->decimal('settlement_agent1_rate', 10, 2)->default(0);
            $table->decimal('settlement_agent2_rate', 10, 2)->default(0);
            $table->decimal('settlement_agent3_rate', 10, 2)->default(0);
            $table->decimal('settlement_agent4_rate', 10, 2)->default(0);
            $table->decimal('settlement_agent5_rate', 10, 2)->default(0);
            $table->softDeletes();
        });
        Schema::connection('sqlite')->create('transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->default(0);
            $table->tinyInteger('status')->default(2);
            $table->tinyInteger('type')->default(0);
            $table->tinyInteger('pay_status')->default(1);
            $table->decimal('actual_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('holder_name')->nullable();
            $table->string('card_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('ordernumber')->nullable();
            $table->string('bank_code')->nullable();
            $table->decimal('user_commission', 10, 2)->default(0);
            $table->string('remark')->nullable();
            $table->timestamps();
        });
        Schema::connection('sqlite')->create('transfer_order_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->string('type')->nullable();
            $table->string('message')->nullable();
            $table->text('content')->nullable();
            $table->timestamps();
        });

        app('db')->connection('sqlite')->table('users')->insert([
            'id' => 58,
            'name' => '测试金主',
            'username' => 'test-owner',
        ]);
    }

    public function test_cancel_transfer_order_moves_to_failed_status(): void
    {
        $this->assertCancelStatus(0, 3);
    }

    public function test_cancel_settlement_order_moves_to_cancelled_status(): void
    {
        $this->assertCancelStatus(1, 6);
    }

    public function test_invalid_cancel_rolls_back_transaction(): void
    {
        $user = User::query()->findOrFail(58);
        $request = Request::create('/api/v2/transfer-orders/cancelOrder', 'GET', ['id' => 999]);
        $request->setUserResolver(fn () => $user);

        $payload = app(TransferOrderController::class)->cancelOrder($request)->getData(true);

        $this->assertSame('订单已失效', $payload['message']);
        $this->assertSame(0, app('db')->connection('sqlite')->transactionLevel());
    }

    public function test_submitted_order_cannot_be_cancelled_or_unassigned(): void
    {
        foreach ([2, 3] as $payStatus) {
            $orderId = app('db')->connection('sqlite')->table('transfer_orders')->insertGetId([
                'user_id' => 58,
                'status' => 2,
                'type' => 0,
                'pay_status' => $payStatus,
                'ordernumber' => 'TEST-SUBMITTED-' . $payStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $user = User::query()->findOrFail(58);
            $request = Request::create('/api/v2/transfer-orders/cancelOrder', 'GET', ['id' => $orderId]);
            $request->setUserResolver(fn () => $user);

            $payload = app(TransferOrderController::class)->cancelOrder($request)->getData(true);
            $order = app('db')->connection('sqlite')->table('transfer_orders')->find($orderId);

            $this->assertSame('订单已失效', $payload['message']);
            $this->assertSame(2, (int) $order->status);
            $this->assertSame(58, (int) $order->user_id);
            $this->assertSame($payStatus, (int) $order->pay_status);
            $this->assertSame(0, app('db')->connection('sqlite')->transactionLevel());
        }
    }

    public function test_invalid_submit_rolls_back_transaction(): void
    {
        $missingUser = new User();
        $missingUser->id = 999;
        $request = TransferOrdersubmitOrderRequest::create('/api/v2/transfer-orders/submitOrder', 'POST', [
            'id' => 999,
            'pay_certificate_1' => 'transfer/test.jpg',
        ]);
        $request->setUserResolver(fn () => $missingUser);

        $payload = app(TransferOrderController::class)->submitOrder($request)->getData(true);

        $this->assertSame('订单已失效', $payload['message']);
        $this->assertSame(0, app('db')->connection('sqlite')->transactionLevel());
    }

    private function assertCancelStatus(int $type, int $expectedStatus): void
    {
        $orderId = app('db')->connection('sqlite')->table('transfer_orders')->insertGetId([
            'user_id' => 58,
            'status' => 2,
            'type' => $type,
            'pay_status' => 1,
            'ordernumber' => 'TEST-CANCEL-' . $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail(58);
        $request = Request::create('/api/v2/transfer-orders/cancelOrder', 'GET', ['id' => $orderId]);
        $request->setUserResolver(fn () => $user);

        app(TransferOrderController::class)->cancelOrder($request);

        $order = app('db')->connection('sqlite')->table('transfer_orders')->find($orderId);
        $this->assertSame($expectedStatus, (int) $order->status);
        $this->assertSame(0, (int) $order->user_id);
        $this->assertSame(0, (int) $order->pay_status);
    }
}
