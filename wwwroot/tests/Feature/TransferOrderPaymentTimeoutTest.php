<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\TransferOrderPaymentTimeoutJob;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class TransferOrderPaymentTimeoutTest extends TestCase
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

        Schema::connection('sqlite')->create('transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->default(0);
            $table->tinyInteger('status')->default(2);
            $table->tinyInteger('type')->default(0);
            $table->tinyInteger('pay_status')->default(0);
            $table->string('ordernumber')->nullable();
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
    }

    /**
     * @dataProvider timeoutStatusProvider
     */
    public function test_unsubmitted_owner_order_is_released_after_timeout(int $type, int $status): void
    {
        $orderId = $this->createOrder([
            'type' => $type,
            'updated_at' => now()->subMinutes(11),
        ]);

        (new TransferOrderPaymentTimeoutJob($orderId, 10))->handle();

        $order = app('db')->table('transfer_orders')->find($orderId);
        $this->assertSame($status, (int) $order->status);
        $this->assertSame(0, (int) $order->user_id);
        $this->assertSame(0, (int) $order->pay_status);
        $this->assertStringContainsString('金主ID：58', $order->remark);
        $this->assertSame(1, app('db')->table('transfer_order_logs')->where('order_id', $orderId)->count());
    }

    public function timeoutStatusProvider(): array
    {
        return [
            'transfer order' => [0, 3],
            'settlement order' => [1, 6],
        ];
    }

    /**
     * @dataProvider protectedOrderProvider
     */
    public function test_timeout_does_not_change_non_expired_or_submitted_order(array $changes): void
    {
        $orderId = $this->createOrder($changes);

        (new TransferOrderPaymentTimeoutJob($orderId, 10))->handle();

        $order = app('db')->table('transfer_orders')->find($orderId);
        $this->assertSame((int) ($changes['status'] ?? 2), (int) $order->status);
        $this->assertSame((int) ($changes['user_id'] ?? 58), (int) $order->user_id);
        $this->assertSame((int) ($changes['pay_status'] ?? 1), (int) $order->pay_status);
        $this->assertSame(0, app('db')->table('transfer_order_logs')->where('order_id', $orderId)->count());
    }

    public function protectedOrderProvider(): array
    {
        return [
            'not expired' => [['updated_at' => null]],
            'already paid' => [['pay_status' => 2, 'updated_at' => '-11 minutes']],
            'waiting confirmation' => [['pay_status' => 3, 'updated_at' => '-11 minutes']],
            'already terminal' => [['status' => 4, 'updated_at' => '-11 minutes']],
            'unassigned' => [['user_id' => 0, 'pay_status' => 0, 'updated_at' => '-11 minutes']],
        ];
    }

    private function createOrder(array $changes): int
    {
        $updatedAt = $changes['updated_at'] ?? now();
        if (is_string($updatedAt)) {
            $updatedAt = now()->modify($updatedAt);
        }

        return (int) app('db')->table('transfer_orders')->insertGetId(array_merge([
            'user_id' => 58,
            'status' => 2,
            'type' => 0,
            'pay_status' => 1,
            'ordernumber' => uniqid('TIMEOUT-', true),
            'created_at' => now()->subMinutes(20),
            'updated_at' => $updatedAt,
        ], array_diff_key($changes, ['updated_at' => true])));
    }
}
