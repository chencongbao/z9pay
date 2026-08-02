<?php

namespace Tests\Feature;

use App\Jobs\Report\HandleReportUserAgentJob;
use App\Jobs\RepairCurrencyDepositOrderReportJob;
use App\Jobs\RepairCurrencyTransferOrderReportJob;
use App\Jobs\RepairMerchantAgentDepositOrderReportJob;
use App\Jobs\RepairMerchantAgentTransferOrderReportJob;
use App\Jobs\RepairMerchantDepositOrderReportJob;
use App\Jobs\RepairMerchantTransferOrderReportJob;
use App\Jobs\RepairUserReportJob;
use App\Models\DepositOrder;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\TransferOrder\TransferOrderCentusResetService;
use App\Services\DepositOrder\DepositOrderStatusService;
use App\Services\Report\OrderStatusReportRepairService;
use App\Services\Report\ReportPendingDateService;
use App\Services\TransferOrder\TransferOrderStatusService;
use Mockery;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportOrderStatusRepairDispatchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deposit_status_change_dispatches_historical_report_repair_jobs(): void
    {
        Queue::fake();

        $yesterday = now()->subDay();
        $user = $this->createUser();
        $service = App::make(DepositOrderStatusService::class);
        $order = $this->createDepositOrder([
            'user_id' => $user->id,
            'created_at' => $yesterday,
            'success_time' => $yesterday->timestamp,
            'mid' => 24,
            'currency_id' => 1,
            'merchant_agent1_id' => 1000,
            'status' => 1,
        ]);
        $date = $yesterday->toDateString();

        $service->markFailed($order);
        $this->assertReportJobsPushedForDepositOrder($order, $date);

        Queue::fake();
        $order->status = 1;
        $order->save();
        $service->markRisk($order);
        $this->assertReportJobsPushedForDepositOrder($order, $date);

        Queue::fake();
        $order->status = 1;
        $order->save();
        $service->markTimeout($order);
        $this->assertReportJobsPushedForDepositOrder($order, $date);
    }

    public function test_transfer_status_change_dispatches_historical_report_repair_jobs(): void
    {
        Queue::fake();

        $yesterday = now()->subDay();
        $user = $this->createUser();
        $service = App::make(TransferOrderStatusService::class);
        $order = $this->createTransferOrder([
            'user_id' => $user->id,
            'created_at' => $yesterday,
            'success_time' => $yesterday->timestamp,
            'mid' => 24,
            'currency_id' => 1,
            'merchant_agent1_id' => 1001,
            'status' => 1,
        ]);
        $date = $yesterday->toDateString();

        $service->markFailed($order);
        $this->assertReportJobsPushedForTransferOrder($order, $date);

        Queue::fake();
        $order->status = 1;
        $order->save();
        $service->markPending($order);
        $this->assertReportJobsPushedForTransferOrder($order, $date);

        Queue::fake();
        $order->status = 1;
        $order->save();
        $service->markPendingConfirm($order, ['name' => 'codex_channel'], '人工确认');
        $this->assertReportJobsPushedForTransferOrder($order, $date);
    }

    public function test_transfer_order_centus_reset_service_fetches_full_model_by_id(): void
    {
        $order = $this->createTransferOrder([
            'created_at' => now()->subDay()->toDateTimeString(),
            'success_time' => now()->subDay()->timestamp,
            'mid' => 24,
            'currency_id' => 1,
            'merchant_agent1_id' => 1002,
            'status' => 4,
        ]);

        $stub = new TransferOrder();
        $stub->id = $order->id;

        $repairService = Mockery::mock(OrderStatusReportRepairService::class);
        $repairService
            ->shouldReceive('forTransferOrder')
            ->once()
            ->with(Mockery::on(
                function ($value) use ($order) {
                    return $value instanceof TransferOrder
                        && (int) $value->id === (int) $order->id;
                }
            ));
        App::instance(OrderStatusReportRepairService::class, $repairService);

        App::make(TransferOrderCentusResetService::class)->excute($stub);
    }

    public function test_order_status_report_repair_excludes_today_from_auto_pending_dates(): void
    {
        Carbon::setTestNow('2026-07-28 12:00:00');

        try {
            $pendingDateService = Mockery::mock(ReportPendingDateService::class);
            $pendingDateService->shouldReceive('addDates')->never();
            App::instance(ReportPendingDateService::class, $pendingDateService);

            App::make(OrderStatusReportRepairService::class)->forDepositOrder(new DepositOrder([
                'created_at' => '2026-07-28 01:00:00',
                'updated_at' => '2026-07-28 02:00:00',
                'success_time' => Carbon::parse('2026-07-28 03:00:00')->timestamp,
            ]));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_order_status_report_repair_uses_created_at_to_judge_history_order(): void
    {
        Carbon::setTestNow('2026-07-28 12:00:00');

        try {
            $pendingDateService = Mockery::mock(ReportPendingDateService::class);
            $pendingDateService->shouldReceive('addDates')->never();
            App::instance(ReportPendingDateService::class, $pendingDateService);

            App::make(OrderStatusReportRepairService::class)->forTransferOrder(new TransferOrder([
                'created_at' => '2026-07-28 01:00:00',
                'success_time' => Carbon::parse('2026-07-27 23:59:59')->timestamp,
            ]));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_order_status_report_repair_keeps_history_dates_before_today(): void
    {
        Carbon::setTestNow('2026-07-28 12:00:00');

        try {
            $pendingDateService = Mockery::mock(ReportPendingDateService::class);
            $pendingDateService
                ->shouldReceive('addDates')
                ->once()
                ->with(Mockery::on(fn ($dates) => collect($dates)->all() === ['2026-07-27']));
            App::instance(ReportPendingDateService::class, $pendingDateService);

            App::make(OrderStatusReportRepairService::class)->forTransferOrder(new TransferOrder([
                'created_at' => '2026-07-27 01:00:00',
                'updated_at' => '2026-07-28 02:00:00',
                'success_time' => Carbon::parse('2026-07-27 23:59:59')->timestamp,
            ]));
        } finally {
            Carbon::setTestNow();
        }
    }

    private function assertReportJobsPushedForDepositOrder(DepositOrder $order, string $date): void
    {
        $this->assertReportJobsPushed(
            $order->user_id,
            $order->mid,
            $order->currency_id,
            (int) $order->merchant_agent1_id,
            $date,
            isTransfer: false
        );
    }

    private function assertReportJobsPushedForTransferOrder(TransferOrder $order, string $date): void
    {
        $this->assertReportJobsPushed(
            $order->user_id,
            $order->mid,
            $order->currency_id,
            (int) $order->merchant_agent1_id,
            $date,
            isTransfer: true
        );
    }

    private function assertReportJobsPushed(int $userId, int $mid, int $currencyId, int $agentId, string $date, bool $isTransfer): void
    {
        Queue::assertPushedOn('count', RepairUserReportJob::class, fn ($job) => $job->user_id === $userId && $job->date === $date);
        Queue::assertPushedOn('count', HandleReportUserAgentJob::class, fn ($job) => $job->date_add === $date);
        Queue::assertPushedOn('count', $isTransfer ? RepairMerchantTransferOrderReportJob::class : RepairMerchantDepositOrderReportJob::class, fn ($job) => $job->mid === $mid && $job->date === $date);
        Queue::assertPushedOn('count', $isTransfer ? RepairCurrencyTransferOrderReportJob::class : RepairCurrencyDepositOrderReportJob::class, fn ($job) => $job->currencyId === $currencyId && $job->date === $date);
        Queue::assertPushedOn('count', $isTransfer ? RepairMerchantAgentTransferOrderReportJob::class : RepairMerchantAgentDepositOrderReportJob::class, fn ($job) => $job->agent_id === $agentId && $job->date === $date);
    }

    private function createUser(array $attributes = []): User
    {
        $suffix = str_replace('.', '_', uniqid('', true));

        return User::query()->forceCreate(array_merge([
            'username' => 'codex_user_report_' . $suffix,
            'password' => bcrypt('password'),
            'name' => 'Codex User',
            'is_agent' => 0,
            'status' => 1,
            'balance_amount' => '0.00',
            'deposit_amount' => '0.00',
            'deposit_balance_amount' => '0.00',
            'transfer_balance_amount' => '0.00',
            'commission_balance_amount' => '0.00',
            'income_settlement_to_deposit_on' => 0,
        ], $attributes));
    }

    private function createDepositOrder(array $attributes = []): DepositOrder
    {
        $suffix = str_replace('.', '_', uniqid('', true));

        return DepositOrder::query()->create(array_merge([
            'ordernumber' => 'D' . date('YmdHis') . str_replace('_', '', $suffix),
            'order_no' => 'COD_' . $suffix,
            'mid' => 24,
            'user_id' => 0,
            'status' => 1,
            'amount' => '10.00',
            'actual_amount' => '10.00',
            'currency_id' => 1,
            'created_at' => now()->toDateTimeString(),
            'success_time' => 0,
            'merchant_agent1_id' => 0,
        ], $attributes));
    }

    private function createTransferOrder(array $attributes = []): TransferOrder
    {
        $suffix = str_replace('.', '_', uniqid('', true));

        return TransferOrder::query()->create(array_merge([
            'ordernumber' => 'T' . date('YmdHis') . str_replace('_', '', $suffix),
            'order_no' => 'TORD_' . $suffix,
            'mid' => 24,
            'user_id' => 0,
            'type' => 0,
            'status' => 1,
            'amount' => '10.00',
            'actual_amount' => '10.00',
            'currency_id' => 1,
            'created_at' => now()->toDateTimeString(),
            'success_time' => 0,
            'merchant_agent1_id' => 0,
        ], $attributes));
    }
}
