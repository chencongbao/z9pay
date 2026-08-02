<?php

namespace Tests\Feature;

use Exception;
use Tests\TestCase;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\DepositOrder\ConfirmPaySuccessService;

class DepositCommissionLedgerTest extends TestCase
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
        Queue::fake();

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->tinyInteger('is_agent')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->integer('level')->default(0);
            $table->integer('pid')->default(0);
            $table->tinyInteger('acquisition_status')->default(1);
            $table->decimal('collection_limit_min', 20, 2)->default(0);
            $table->decimal('collection_limit_max', 20, 2)->default(0);
            $table->decimal('pay_limit_min', 20, 2)->default(0);
            $table->decimal('pay_limit_max', 20, 2)->default(0);
            $table->integer('limit_deposit_paid_number')->default(0);
            $table->text('user_deposit_payment_rate')->nullable();
            $table->decimal('user_rate', 10, 2)->default(0);
            $table->decimal('deposit_user_rate', 10, 2)->default(0);
            $table->integer('round_times')->default(1);
            $table->decimal('balance_amount', 20, 2)->default(0);
            $table->decimal('deposit_balance_amount', 20, 2)->default(0);
            $table->decimal('transfer_balance_amount', 20, 2)->default(0);
            $table->decimal('commission_balance_amount', 20, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::connection('sqlite')->create('user_relations', function (Blueprint $table) {
            $table->id();
            $table->integer('parent_id');
            $table->integer('child_id');
            $table->integer('level');
        });
        Schema::connection('sqlite')->create('user_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('mid')->default(0);
            $table->integer('user_id')->default(0);
            $table->integer('action_user_id')->default(0);
            $table->integer('user_bank_id')->default(0);
            $table->tinyInteger('is_agent')->default(0);
            $table->decimal('amount', 20, 2)->default(0);
            $table->tinyInteger('type')->default(0);
            $table->integer('type_id')->default(0);
            $table->string('ordernumber')->nullable();
            $table->string('remark')->nullable();
            $table->decimal('balance_amount', 20, 2)->default(0);
            $table->decimal('type_balance_amount', 20, 2)->default(0);
            $table->tinyInteger('order_type')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::connection('sqlite')->create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->string('log_type')->default('operation');
            $table->string('ip', 45)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_input')->nullable();
            $table->timestamps();
        });

        $this->createUser(100, false, 2000, 2000);
        foreach (range(101, 105) as $agentId) {
            $this->createUser($agentId, true);
        }
    }

    public function test_owner_and_five_agents_receive_balances_and_order_level_logs(): void
    {
        $this->changeBalances($this->order(), $this->commissionData());

        $owner = DB::table('users')->find(100);
        $this->assertSame('1220.00', number_format((float) $owner->balance_amount, 2, '.', ''));
        $this->assertSame('2800.00', number_format((float) $owner->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $owner->commission_balance_amount, 2, '.', ''));

        foreach ([101 => 8, 102 => 6.4, 103 => 4.8, 104 => 3.2, 105 => 1.6] as $agentId => $commission) {
            $agent = DB::table('users')->find($agentId);
            $this->assertSame(number_format($commission, 2, '.', ''), number_format((float) $agent->balance_amount, 2, '.', ''));
            $this->assertSame('0.00', number_format((float) $agent->commission_balance_amount, 2, '.', ''));
        }

        $logs = DB::table('user_balance_logs')->where('type_id', 900)->orderBy('id')->get();
        $this->assertCount(7, $logs);
        $this->assertSame([4, 1, 1, 1, 1, 1, 1], $logs->pluck('type')->map(fn ($type) => (int) $type)->all());
        $this->assertSame('-800.00', number_format((float) $logs->first()->amount, 2, '.', ''));
        $this->assertSame(['DEPOSIT-COMMISSION-900'], $logs->pluck('ordernumber')->unique()->values()->all());
        $this->assertSame([100, 100, 101, 102, 103, 104, 105], $logs->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([0, 0, 1, 1, 1, 1, 1], $logs->pluck('is_agent')->map(fn ($isAgent) => (int) $isAgent)->all());
        $this->assertSame('0.00', number_format((float) $logs->last()->type_balance_amount, 2, '.', ''));
    }

    public function test_missing_agent_rolls_back_owner_and_previous_agent_changes(): void
    {
        $order = $this->order();
        $order->user_agent5_id = 999;

        try {
            $this->changeBalances($order, $this->commissionData());
            $this->fail('缺失代理应导致整组余额变化回滚');
        } catch (Exception $e) {
            $this->assertSame('金主代理不存在', $e->getMessage());
        }

        $owner = DB::table('users')->find(100);
        $this->assertSame('2000.00', number_format((float) $owner->balance_amount, 2, '.', ''));
        $this->assertSame('2000.00', number_format((float) $owner->deposit_balance_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $owner->commission_balance_amount, 2, '.', ''));
        $this->assertSame(0, DB::table('user_balance_logs')->count());
        foreach (range(101, 105) as $agentId) {
            $this->assertSame('0.00', number_format((float) DB::table('users')->where('id', $agentId)->value('balance_amount'), 2, '.', ''));
        }
    }

    private function changeBalances(DepositOrder $order, array $data): void
    {
        $service = new class extends ConfirmPaySuccessService {
            public function apply(DepositOrder $order, array $data): void
            {
                DB::transaction(fn () => $this->changeUserBalance($order, $data));
            }
        };
        $service->apply($order, $data);
    }

    private function order(): DepositOrder
    {
        return new DepositOrder([
            'id' => 900,
            'mid' => 24,
            'ordernumber' => 'DEPOSIT-COMMISSION-900',
            'user_id' => 100,
            'user_agent1_id' => 101,
            'user_agent2_id' => 102,
            'user_agent3_id' => 103,
            'user_agent4_id' => 104,
            'user_agent5_id' => 105,
        ]);
    }

    private function commissionData(): array
    {
        return [
            'actual_amount' => 800,
            'user_commission' => 20,
            'user_agent1_commission' => 8,
            'user_agent2_commission' => 6.4,
            'user_agent3_commission' => 4.8,
            'user_agent4_commission' => 3.2,
            'user_agent5_commission' => 1.6,
        ];
    }

    private function createUser(int $id, bool $isAgent, float $balance = 0, float $depositBalance = 0): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'is_agent' => $isAgent ? 1 : 0,
            'balance_amount' => $balance,
            'deposit_balance_amount' => $depositBalance,
            'transfer_balance_amount' => 0,
            'commission_balance_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
