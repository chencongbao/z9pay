<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\User\ReserveUserDepositOrderService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class UserDepositReservationTest extends TestCase
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

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_agent')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('acquisition_status')->default(1);
            $table->decimal('deposit_amount', 20, 2)->default(0);
            $table->decimal('deposit_balance_amount', 20, 2)->default(0);
            $table->decimal('transfer_balance_amount', 20, 2)->default(0);
            $table->decimal('collection_limit_min', 20, 2)->default(0);
            $table->decimal('collection_limit_max', 20, 2)->default(0);
            $table->unsignedInteger('limit_deposit_paid_number')->default(0);
            $table->integer('pid')->default(0);
            $table->decimal('user_rate', 10, 2)->default(0);
            $table->decimal('deposit_user_rate', 10, 2)->default(0);
            $table->text('user_deposit_payment_rate')->nullable();
            foreach (range(1, 5) as $level) {
                $table->decimal("agent{$level}_rate", 10, 2)->default(0);
                $table->decimal("deposit_agent{$level}_rate", 10, 2)->default(0);
            }
            $table->decimal('pending_deposit_order_amount', 20, 2)->default(0);
            $table->unsignedInteger('pending_deposit_order_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::connection('sqlite')->create('deposit_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedBigInteger('user_bank_id')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->decimal('amount', 20, 2)->default(0);
            $table->unsignedInteger('payment_id')->default(2);
            $table->decimal('user_rate', 10, 6)->default(0);
            foreach (range(1, 5) as $level) {
                $table->unsignedBigInteger("user_agent{$level}_id")->default(0);
                $table->decimal("user_agent{$level}_rate", 10, 6)->default(0);
            }
            $table->timestamps();
        });
        Schema::connection('sqlite')->create('user_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('child_id');
            $table->unsignedInteger('level');
        });
        Schema::connection('sqlite')->create('user_banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('collection_status')->default(1);
            $table->unsignedInteger('payment_id')->default(2);
            $table->decimal('limint_min_amount', 20, 2)->default(0);
            $table->decimal('limint_max_amount', 20, 2)->default(0);
            $table->decimal('limint_day_amount', 20, 2)->default(0);
            $table->unsignedInteger('limit_day_order_number')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        app('db')->table('users')->insert([
            'id' => 87,
            'deposit_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app('db')->table('user_banks')->insert([
            'id' => 86,
            'user_id' => 87,
            'collection_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app('db')->table('deposit_orders')->insert([
            [
                'id' => 1001,
                'amount' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 1002,
                'amount' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_only_one_order_can_reserve_the_last_available_deposit_amount(): void
    {
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86));
        $this->assertFalse($service->execute(1002, 87, 86));

        $this->assertSame(87, (int)app('db')->table('deposit_orders')->find(1001)->user_id);
        $this->assertSame(0, (int)app('db')->table('deposit_orders')->find(1002)->user_id);
        $this->assertEquals(100.0, (float)app('db')->table('users')->find(87)->pending_deposit_order_amount);
        $this->assertSame(1, (int)app('db')->table('users')->find(87)->pending_deposit_order_count);
    }

    public function test_final_reservation_snapshots_current_owner_and_five_agent_rates(): void
    {
        app('db')->table('users')->where('id', 87)->update([
            'user_rate' => 1,
            'deposit_user_rate' => 2,
            'user_deposit_payment_rate' => json_encode([
                ['payment_id' => 2, 'deposit_user_rate' => 3],
            ]),
            'agent1_rate' => 1,
            'deposit_agent1_rate' => 1.1,
            'agent2_rate' => 2.2,
            'deposit_agent3_rate' => 3.3,
            'agent4_rate' => 4.4,
            'deposit_agent5_rate' => 5.5,
        ]);
        foreach (range(1, 5) as $level) {
            app('db')->table('user_relations')->insert([
                'parent_id' => 100 + $level,
                'child_id' => 87,
                'level' => $level,
            ]);
        }

        $this->assertTrue(app(ReserveUserDepositOrderService::class)->execute(1001, 87, 86));

        $order = app('db')->table('deposit_orders')->find(1001);
        $this->assertEqualsWithDelta(0.03, (float)$order->user_rate, 0.000001);
        foreach ([1 => 0.011, 2 => 0.022, 3 => 0.033, 4 => 0.044, 5 => 0.055] as $level => $rate) {
            $this->assertSame(100 + $level, (int)$order->{"user_agent{$level}_id"});
            $this->assertEqualsWithDelta($rate, (float)$order->{"user_agent{$level}_rate"}, 0.000001);
        }
    }

    public function test_rate_changes_only_affect_newly_reserved_orders(): void
    {
        app('db')->table('users')->where('id', 87)->update([
            'deposit_amount' => 1000,
            'deposit_user_rate' => 2,
            'deposit_agent1_rate' => 1,
        ]);
        app('db')->table('user_relations')->insert([
            'parent_id' => 101,
            'child_id' => 87,
            'level' => 1,
        ]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86));

        app('db')->table('users')->where('id', 87)->update([
            'deposit_user_rate' => 4,
            'deposit_agent1_rate' => 3,
        ]);
        $this->assertTrue($service->execute(1002, 87, 86));

        $oldOrder = app('db')->table('deposit_orders')->find(1001);
        $newOrder = app('db')->table('deposit_orders')->find(1002);
        $this->assertEqualsWithDelta(0.02, (float) $oldOrder->user_rate, 0.000001);
        $this->assertEqualsWithDelta(0.01, (float) $oldOrder->user_agent1_rate, 0.000001);
        $this->assertEqualsWithDelta(0.04, (float) $newOrder->user_rate, 0.000001);
        $this->assertEqualsWithDelta(0.03, (float) $newOrder->user_agent1_rate, 0.000001);
    }

    public function test_new_order_cannot_make_pending_amount_exceed_system_limit(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 0]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86, 150));
        $this->assertFalse($service->execute(1002, 87, 86, 150));

        $this->assertSame(87, (int)app('db')->table('deposit_orders')->find(1001)->user_id);
        $this->assertSame(0, (int)app('db')->table('deposit_orders')->find(1002)->user_id);
    }

    public function test_pending_orders_cannot_exceed_bank_daily_amount_limit(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        app('db')->table('user_banks')->where('id', 86)->update(['limint_day_amount' => 150]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86));
        $this->assertFalse($service->execute(1002, 87, 86));
    }

    public function test_pending_orders_cannot_exceed_bank_daily_order_limit(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        app('db')->table('user_banks')->where('id', 86)->update(['limit_day_order_number' => 1]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86));
        $this->assertFalse($service->execute(1002, 87, 86));
    }

    /**
     * @dataProvider releasedStatusProvider
     */
    public function test_non_pending_order_releases_bank_daily_limit(int $releasedStatus): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        app('db')->table('user_banks')->where('id', 86)->update([
            'limint_day_amount' => 100,
            'limit_day_order_number' => 1,
        ]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86));
        app('db')->table('deposit_orders')->where('id', 1001)->update(['status' => $releasedStatus]);

        $this->assertTrue($service->execute(1002, 87, 86));
    }

    public function releasedStatusProvider(): array
    {
        return [
            'risk' => [2],
            'timeout' => [4],
            'failed' => [6],
        ];
    }

    public function test_successful_order_keeps_bank_daily_limit_reserved(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        app('db')->table('user_banks')->where('id', 86)->update([
            'limint_day_amount' => 100,
            'limit_day_order_number' => 1,
        ]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86));
        app('db')->table('deposit_orders')->where('id', 1001)->update(['status' => 5]);

        $this->assertFalse($service->execute(1002, 87, 86));
    }

    /**
     * @dataProvider finalParameterProvider
     */
    public function test_final_reservation_rechecks_changed_owner_and_bank_parameters(string $table, array $values): void
    {
        app('db')->table($table)->where('id', $table === 'users' ? 87 : 86)->update($values);

        $this->assertFalse(app(ReserveUserDepositOrderService::class)->execute(1001, 87, 86));
        $this->assertSame(0, (int)app('db')->table('deposit_orders')->find(1001)->user_id);
    }

    public function finalParameterProvider(): array
    {
        return [
            'owner disabled' => ['users', ['status' => 0]],
            'owner acquisition disabled' => ['users', ['acquisition_status' => 0]],
            'owner minimum raised' => ['users', ['collection_limit_min' => 100.01]],
            'owner maximum lowered' => ['users', ['collection_limit_max' => 99.99]],
            'bank disabled' => ['user_banks', ['collection_status' => 0]],
            'bank payment changed' => ['user_banks', ['payment_id' => 3]],
            'bank minimum raised' => ['user_banks', ['limint_min_amount' => 100.01]],
            'bank maximum lowered' => ['user_banks', ['limint_max_amount' => 99.99]],
        ];
    }

    public function test_global_same_amount_limit_is_checked_during_final_reservation(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86, 0, 1));
        $this->assertFalse($service->execute(1002, 87, 86, 0, 1));
    }

    public function test_owner_same_amount_limit_overrides_global_limit(): void
    {
        app('db')->table('users')->where('id', 87)->update([
            'deposit_amount' => 1000,
            'limit_deposit_paid_number' => 2,
        ]);
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86, 0, 1));
        $this->assertTrue($service->execute(1002, 87, 86, 0, 1));
    }

    public function test_bank_timed_same_amount_limit_is_checked_during_final_reservation(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        $limits = ['same_amount_minutes' => 10, 'same_amount_count' => 1];
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86, 0, 0, $limits));
        $this->assertFalse($service->execute(1002, 87, 86, 0, 0, $limits));
    }

    public function test_bank_timed_pending_count_limit_is_checked_during_final_reservation(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        app('db')->table('deposit_orders')->where('id', 1002)->update(['amount' => 99]);
        $limits = ['pending_minutes' => 10, 'pending_count' => 1];
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86, 0, 0, $limits));
        $this->assertFalse($service->execute(1002, 87, 86, 0, 0, $limits));
    }

    public function test_timed_bank_limit_is_disabled_when_either_setting_is_zero(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        $limits = [
            'same_amount_minutes' => 10,
            'same_amount_count' => 0,
            'pending_minutes' => 0,
            'pending_count' => 1,
        ];
        $service = app(ReserveUserDepositOrderService::class);

        $this->assertTrue($service->execute(1001, 87, 86, 0, 0, $limits));
        $this->assertTrue($service->execute(1002, 87, 86, 0, 0, $limits));
    }

    /**
     * @dataProvider pendingSummaryReleasedStatusProvider
     */
    public function test_terminal_status_immediately_releases_pending_summary(int $releasedStatus): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        $this->assertTrue(app(ReserveUserDepositOrderService::class)->execute(1001, 87, 86));
        $this->assertEquals(100.0, (float)app('db')->table('users')->find(87)->pending_deposit_order_amount);
        $this->assertSame(1, (int)app('db')->table('users')->find(87)->pending_deposit_order_count);

        $order = DepositOrder::query()->findOrFail(1001);
        $order->status = $releasedStatus;
        $order->save();
        app(GetUserDaifukuanDepositOrderListService::class)->syncByOrder(87, $order);

        $this->assertEquals(0.0, (float)app('db')->table('users')->find(87)->pending_deposit_order_amount);
        $this->assertSame(0, (int)app('db')->table('users')->find(87)->pending_deposit_order_count);
    }

    public function pendingSummaryReleasedStatusProvider(): array
    {
        return [
            'risk' => [2],
            'timeout' => [4],
            'success' => [5],
            'failed' => [6],
        ];
    }

    public function test_pending_confirmation_status_keeps_pending_summary(): void
    {
        app('db')->table('users')->where('id', 87)->update(['deposit_amount' => 1000]);
        $this->assertTrue(app(ReserveUserDepositOrderService::class)->execute(1001, 87, 86));

        $order = DepositOrder::query()->findOrFail(1001);
        $order->status = 7;
        $order->save();
        app(GetUserDaifukuanDepositOrderListService::class)->syncByOrder(87, $order);

        $this->assertEquals(100.0, (float)app('db')->table('users')->find(87)->pending_deposit_order_amount);
        $this->assertSame(1, (int)app('db')->table('users')->find(87)->pending_deposit_order_count);
    }

    /**
     * @dataProvider terminalOrderStatusProvider
     */
    public function test_terminal_order_cannot_be_assigned_to_owner(int $status): void
    {
        app('db')->table('deposit_orders')->where('id', 1001)->update(['status' => $status]);

        $this->assertFalse(app(ReserveUserDepositOrderService::class)->execute(1001, 87, 86));
        $this->assertSame(0, (int)app('db')->table('deposit_orders')->find(1001)->user_id);
    }

    public function terminalOrderStatusProvider(): array
    {
        return [
            'risk' => [2],
            'timeout' => [4],
            'success' => [5],
            'failed' => [6],
        ];
    }

    /**
     * @dataProvider invalidOrderValueProvider
     */
    public function test_invalid_order_value_cannot_be_assigned_to_owner(string $field, $value): void
    {
        app('db')->table('deposit_orders')->where('id', 1001)->update([$field => $value]);

        $this->assertFalse(app(ReserveUserDepositOrderService::class)->execute(1001, 87, 86));
    }

    public function invalidOrderValueProvider(): array
    {
        return [
            'zero amount' => ['amount', 0],
            'negative amount' => ['amount', -1],
            'missing payment type' => ['payment_id', 0],
        ];
    }
}
