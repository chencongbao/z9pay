<?php

namespace Tests\Feature;

use Exception;
use Tests\TestCase;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\DepositOrder\ConfirmPaySuccessService;

class ConfirmPaySuccessIdempotencyTest extends TestCase
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
            $table->tinyInteger('status')->default(3);
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('actual_amount', 10, 2)->default(0);
            $table->integer('success_time')->default(0);
            $table->timestamps();
        });
    }

    public function test_same_order_can_only_be_confirmed_successfully_once(): void
    {
        $orderId = app('db')->table('deposit_orders')->insertGetId([
            'status' => 3,
            'amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $service = new class extends ConfirmPaySuccessService {
            public int $balanceChanges = 0;

            protected function fields(): array
            {
                return ['id', 'status', 'amount', 'actual_amount', 'success_time'];
            }

            protected function successData(DepositOrder $order, $amount, string $remark = '', int $handAdminId = 0, int $handSuccess = 0): array
            {
                return ['status' => 5, 'actual_amount' => $amount, 'success_time' => time()];
            }

            protected function changeUserBalance(DepositOrder $order, array $data): void
            {
                $this->balanceChanges++;
            }

            protected function changeUserBankBalance(DepositOrder $order, array $data): void
            {
            }

            protected function changeMerchantAgentBalance(DepositOrder $order, array $data): void
            {
            }

            protected function changeMerchantBalance(DepositOrder $order, array $data, string $remark = ''): void
            {
            }

            protected function afterCommitSuccess(DepositOrder $order, bool $callback): void
            {
            }
        };

        $service->excute($orderId, 100, false);
        $this->assertSame(5, (int)app('db')->table('deposit_orders')->find($orderId)->status);
        $this->assertSame(1, $service->balanceChanges);

        try {
            $service->excute($orderId, 100, false);
            $this->fail('重复确认应被拒绝');
        } catch (Exception $e) {
            $this->assertSame('订单不存在或当前状态无法确认成功', $e->getMessage());
        }

        $this->assertSame(1, $service->balanceChanges);
    }
}
