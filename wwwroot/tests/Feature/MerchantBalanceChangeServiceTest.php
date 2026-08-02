<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mockery;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use App\Models\TransferOrder;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\Queue;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Services\MerchantChannel\CheckMerchantChannelWhereService;
use App\Services\MerchantPayment\ApplyTransferChannelBankRateService;
use App\Services\TransferOrder\TransferOrderMerchantDeductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MerchantBalanceChangeServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_manual_reduce_allows_negative_merchant_balance(): void
    {
        $merchant = $this->createMerchant([
            'balance_amount' => '10.00',
            'available_balance' => '10.00',
            'freeze_amount' => '0.00',
        ]);

        $service = app(MerchantBalanceChangeService::class);
        $result = $service->reduceManual($merchant, 50, 'Codex manual reduce', 1);

        $merchant->refresh();
        $this->assertTrue((bool)($result['success'] ?? false));
        $this->assertSame('-40.00', number_format((float)$merchant->balance_amount, 2, '.', ''));
        $this->assertSame('-40.00', number_format((float)$merchant->available_balance, 2, '.', ''));
        $this->assertGreaterThan(0, $service->merchant_balance_log_id);
    }

    public function test_transfer_order_deduct_still_rejects_when_available_balance_is_insufficient(): void
    {
        $merchant = $this->createMerchant([
            'balance_amount' => '10.00',
            'available_balance' => '10.00',
            'freeze_amount' => '0.00',
        ]);
        $order = $this->createTransferOrder($merchant->merchant_user_id);

        $result = app(MerchantBalanceChangeService::class)->deductTransferOrder($order, 50, 0);

        $merchant->refresh();
        $this->assertFalse((bool)($result['success'] ?? false));
        $this->assertSame('10.00', number_format((float)$merchant->balance_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$merchant->available_balance, 2, '.', ''));
    }

    public function test_transfer_channel_deduct_rejects_when_available_balance_is_insufficient(): void
    {
        $merchant = $this->createMerchant([
            'balance_amount' => '10.00',
            'available_balance' => '10.00',
            'freeze_amount' => '0.00',
        ]);
        $order = $this->createTransferOrder($merchant->merchant_user_id);

        $rateService = Mockery::mock(ApplyTransferChannelBankRateService::class);
        $rateService->shouldReceive('excute')->once()->andReturn(['success' => true]);
        $this->app->instance(ApplyTransferChannelBankRateService::class, $rateService);

        $feeService = Mockery::mock(CheckMerchantChannelWhereService::class);
        $feeService->shouldReceive('excute')->once()->andReturn(0);
        $this->app->instance(CheckMerchantChannelWhereService::class, $feeService);

        try {
            app(TransferOrderMerchantDeductService::class)->deductForChannel($order, 1);
            $this->fail('代付渠道扣款余额不足时应拒绝');
        } catch (\RuntimeException $e) {
            $this->assertSame('商户余额不足', $e->getMessage());
        }

        $merchant->refresh();
        $this->assertSame('10.00', number_format((float)$merchant->balance_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$merchant->available_balance, 2, '.', ''));
        $this->assertDatabaseMissing('merchant_balance_logs', [
            'mid' => $merchant->merchant_user_id,
            'type_id' => $order->id,
            'type' => 2,
        ]);
    }

    public function test_settlement_channel_reverses_legacy_deduct_log_before_rededucting(): void
    {
        $merchant = $this->createMerchant([
            'balance_amount' => '950.00',
            'available_balance' => '950.00',
            'freeze_amount' => '0.00',
        ]);
        $order = $this->createTransferOrder($merchant->merchant_user_id);
        $order->forceFill(['type' => 1])->saveQuietly();

        MerchantBalanceLog::query()->forceCreate([
            'mid' => $merchant->merchant_user_id,
            'amount' => '-50.00',
            'fee' => '0.00',
            'type' => 6,
            'type_id' => $order->id,
            'currency_id' => 1,
            'payment_id' => 0,
            'order_type' => 0,
            'status' => 1,
            'balance_amount' => '950.00',
            'ordernumber' => $order->ordernumber,
            'order_no' => $order->order_no,
        ]);

        $feeService = Mockery::mock(CheckMerchantChannelWhereService::class);
        $feeService->shouldReceive('excute')->once()->andReturn(0);
        $this->app->instance(CheckMerchantChannelWhereService::class, $feeService);

        app(TransferOrderMerchantDeductService::class)->deductSettlementForChannel($order, 1);

        $merchant->refresh();
        $this->assertSame('950.00', number_format((float)$merchant->balance_amount, 2, '.', ''));
        $this->assertDatabaseHas('merchant_balance_logs', [
            'mid' => $merchant->merchant_user_id,
            'type_id' => $order->id,
            'type' => 15,
            'ordernumber' => $order->ordernumber,
        ]);
    }

    private function createMerchant(array $attributes = []): MerchantInfo
    {
        $suffix = str_replace('.', '_', uniqid('', true));
        $merchantUser = MerchantUser::query()->forceCreate([
            'username' => 'codex_merchant_balance_' . $suffix,
            'password' => bcrypt('password'),
            'name' => 'Codex Merchant Balance',
            'status' => 1,
        ]);

        return MerchantInfo::query()->forceCreate(array_merge([
            'merchant_user_id' => $merchantUser->id,
            'agent_user_id' => 0,
            'coder' => 'codex_balance_' . $suffix,
            'appkey' => 'codex_appkey_' . $suffix,
            'appsecret' => 'codex_appsecret_' . $suffix,
            'currency_id' => 1,
            'name' => 'Codex Merchant Balance',
            'balance_amount' => '0.00',
            'available_balance' => '0.00',
            'freeze_amount' => '0.00',
        ], $attributes));
    }

    private function createTransferOrder(int $mid): TransferOrder
    {
        return TransferOrder::query()->forceCreate([
            'ordernumber' => 'T' . date('YmdHis') . random_int(100000, 999999),
            'order_no' => 'CODEX' . random_int(100000, 999999),
            'mid' => $mid,
            'type' => 0,
            'currency_id' => 1,
            'amount' => '50.00',
            'actual_amount' => '50.00',
            'status' => 1,
        ]);
    }
}
