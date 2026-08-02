<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Controllers\Api\V2\TransferOrderController;
use App\Services\Cache\CacheConstPrefixService;

class TransferOrderReceiveScopeTest extends TestCase
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
            $table->tinyInteger('is_agent')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('acquisition_status')->default(1);
            $table->decimal('pay_limit_min', 10, 2)->default(0);
            $table->decimal('pay_limit_max', 10, 2)->default(0);
            $table->string('pay_group_merchant_user_ids')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->softDeletes();
        });
        Schema::connection('sqlite')->create('transfer_orders', function (Blueprint $table) {
            $table->id();
            foreach (array_diff(CacheConstPrefixService::CACHE_TRANSFER_FILED, ['id', 'created_at']) as $field) {
                $table->string($field)->nullable();
            }
            $table->tinyInteger('pay_status')->default(0);
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

        app('db')->table('users')->insert([
            'id' => 87,
            'status' => 1,
            'acquisition_status' => 1,
            'pay_group_merchant_user_ids' => '24',
            'name' => '测试金主',
            'username' => 'owner87',
        ]);
    }

    /**
     * @dataProvider forbiddenOrderProvider
     */
    public function test_owner_cannot_receive_order_outside_self_scope(array $order): void
    {
        $originalUserId = (int)($order['user_id'] ?? 0);
        $orderId = app('db')->table('transfer_orders')->insertGetId(array_merge([
            'mid' => 24,
            'amount' => 100,
            'currency_id' => 1,
            'channel_id' => 1,
            'user_id' => 0,
            'status' => 2,
            'ordernumber' => uniqid('T-', true),
            'created_at' => now(),
            'updated_at' => now(),
        ], $order));

        $payload = app(TransferOrderController::class)
            ->receviceOrder($this->request($orderId))
            ->getData(true);

        $this->assertSame('未申购到此订单', $payload['message']);
        $this->assertSame($originalUserId, (int)app('db')->table('transfer_orders')->find($orderId)->user_id);
        $this->assertSame(0, app('db')->transactionLevel());
    }

    public function forbiddenOrderProvider(): array
    {
        return [
            'merchant not assigned to owner' => [['mid' => 25]],
            'not self channel' => [['channel_id' => 2]],
            'not cny' => [['currency_id' => 2]],
            'not receivable status' => [['status' => 3]],
            'already assigned' => [['user_id' => 99]],
            'already being paid' => [['pay_status' => 1]],
            'already paid' => [['pay_status' => 2]],
            'waiting confirmation' => [['pay_status' => 3]],
        ];
    }

    public function test_disabled_owner_cannot_receive_order(): void
    {
        app('db')->table('users')->where('id', 87)->update(['status' => 0]);

        $payload = app(TransferOrderController::class)
            ->receviceOrder($this->request(999))
            ->getData(true);

        $this->assertSame('用户已禁用', $payload['message']);
        $this->assertSame(0, app('db')->transactionLevel());
    }

    public function test_owner_with_acquisition_disabled_cannot_receive_order(): void
    {
        app('db')->table('users')->where('id', 87)->update(['acquisition_status' => 0]);

        $payload = app(TransferOrderController::class)
            ->receviceOrder($this->request(999))
            ->getData(true);

        $this->assertSame('接单已关闭', $payload['message']);
        $this->assertSame(0, app('db')->transactionLevel());
    }

    /**
     * @dataProvider receiveLimitProvider
     */
    public function test_owner_receive_limit_includes_exact_boundaries(
        float $amount,
        float $minimum,
        float $maximum,
        bool $accepted
    ): void {
        app('db')->table('users')->where('id', 87)->update([
            'pay_limit_min' => $minimum,
            'pay_limit_max' => $maximum,
        ]);
        $orderId = app('db')->table('transfer_orders')->insertGetId([
            'mid' => 24,
            'amount' => $amount,
            'currency_id' => 1,
            'channel_id' => 1,
            'user_id' => 0,
            'status' => 2,
            'bank_name' => '测试银行',
            'ordernumber' => uniqid('LIMIT-', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TransferOrderController::class)->receviceOrder($this->request($orderId));
        $order = app('db')->table('transfer_orders')->find($orderId);

        $this->assertSame($accepted ? 87 : 0, (int)$order->user_id);
        $this->assertSame($accepted ? 1 : 0, (int)$order->pay_status);
        $this->assertSame(0, app('db')->transactionLevel());
    }

    public function receiveLimitProvider(): array
    {
        return [
            'below minimum' => [99.99, 100, 200, false],
            'exact minimum' => [100, 100, 200, true],
            'exact maximum' => [200, 100, 200, true],
            'above maximum' => [200.01, 100, 200, false],
            'limits disabled' => [999, 0, 0, true],
        ];
    }

    public function test_disabled_owner_cannot_search_receivable_orders(): void
    {
        app('db')->table('users')->where('id', 87)->update(['status' => 0]);
        app('db')->table('transfer_orders')->insert([
            'mid' => 24,
            'amount' => 100,
            'currency_id' => 1,
            'channel_id' => 1,
            'user_id' => 0,
            'status' => 2,
            'ordernumber' => 'SEARCH-DISABLED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/v2/transfer-orders/search-order', 'GET');
        $request->setUserResolver(fn() => User::query()->findOrFail(87));
        $payload = app(TransferOrderController::class)->searchOrder($request)->getData(true);

        $this->assertEmpty($payload['data']['lists']);
    }

    public function test_search_only_returns_unassigned_unpaid_orders(): void
    {
        foreach ([0, 1, 2, 3] as $payStatus) {
            app('db')->table('transfer_orders')->insert([
                'mid' => 24,
                'amount' => 100,
                'currency_id' => 1,
                'channel_id' => 1,
                'user_id' => 0,
                'status' => 2,
                'pay_status' => $payStatus,
                'bank_name' => '测试银行',
                'ordernumber' => 'SEARCH-PAY-' . $payStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $request = Request::create('/api/v2/transfer-orders/search-order', 'GET');
        $request->setUserResolver(fn() => User::query()->findOrFail(87));

        $payload = app(TransferOrderController::class)->searchOrder($request)->getData(true);
        $lists = json_encode($payload['data']['lists']);

        $this->assertCount(1, $payload['data']['lists']);
        $this->assertStringContainsString('SEARCH-PAY-0', $lists);
        $this->assertStringNotContainsString('SEARCH-PAY-1', $lists);
        $this->assertStringNotContainsString('SEARCH-PAY-2', $lists);
        $this->assertStringNotContainsString('SEARCH-PAY-3', $lists);
    }

    public function test_only_first_owner_can_receive_same_order(): void
    {
        app('db')->table('users')->insert([
            'id' => 88,
            'status' => 1,
            'acquisition_status' => 1,
            'pay_group_merchant_user_ids' => '24',
            'name' => '第二金主',
            'username' => 'owner88',
        ]);
        $orderId = app('db')->table('transfer_orders')->insertGetId([
            'mid' => 24,
            'amount' => 100,
            'currency_id' => 1,
            'channel_id' => 1,
            'user_id' => 0,
            'status' => 2,
            'pay_status' => 0,
            'bank_name' => '测试银行',
            'ordernumber' => 'COMPETE-ORDER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstPayload = app(TransferOrderController::class)->receviceOrder($this->request($orderId))->getData(true);
        $secondPayload = app(TransferOrderController::class)->receviceOrder($this->request($orderId, 88))->getData(true);

        $this->assertSame('ok', $firstPayload['message']);
        $this->assertSame('未申购到此订单', $secondPayload['message']);
        $this->assertSame(87, (int)app('db')->table('transfer_orders')->find($orderId)->user_id);
        $this->assertSame(0, app('db')->transactionLevel());
    }

    private function request(int $orderId, int $userId = 87): Request
    {
        $user = User::query()->findOrFail($userId);
        $request = Request::create('/api/v2/transfer-orders/receive-order', 'PUT', ['id' => $orderId]);
        $request->setUserResolver(fn() => $user);

        return $request;
    }
}
