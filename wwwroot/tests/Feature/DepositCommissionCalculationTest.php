<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use App\Models\DepositOrder;
use App\Services\DepositOrder\ConfirmPaySuccessService;
use App\Services\Cache\ChannelRate\GetChannelRateDetailService;

class DepositCommissionCalculationTest extends TestCase
{
    public function test_owner_and_five_agent_commissions_use_actual_amount_and_order_rate_snapshot(): void
    {
        $channelRate = Mockery::mock(GetChannelRateDetailService::class);
        $channelRate->shouldReceive('excute')->once()->with(1, 10, 0, 800.0)->andReturn(0);
        $channelRate->shouldReceive('calculateCost')->once()->with(1, 10, 800.0)->andReturn(0);
        app()->instance(GetChannelRateDetailService::class, $channelRate);

        $order = new DepositOrder([
            'user_id' => 100,
            'user_rate' => 0.025,
            'user_agent1_id' => 101,
            'user_agent1_rate' => 0.01,
            'user_agent2_id' => 102,
            'user_agent2_rate' => 0.008,
            'user_agent3_id' => 103,
            'user_agent3_rate' => 0.006,
            'user_agent4_id' => 104,
            'user_agent4_rate' => 0.004,
            'user_agent5_id' => 105,
            'user_agent5_rate' => 0.002,
            'merchant_rate' => 0,
            'merchant_agent1_id' => 0,
            'merchant_agent2_id' => 0,
            'merchant_agent3_id' => 0,
            'merchant_extra_fee' => 0,
            'channel_id' => 1,
            'payment_id' => 10,
        ]);
        $service = new class extends ConfirmPaySuccessService {
            public function calculate(DepositOrder $order, float $amount): array
            {
                return $this->successData($order, $amount);
            }
        };

        $data = $service->calculate($order, 800);

        $this->assertSame('20.00', number_format((float) $data['user_commission'], 2, '.', ''));
        $this->assertSame('8.00', number_format((float) $data['user_agent1_commission'], 2, '.', ''));
        $this->assertSame('6.40', number_format((float) $data['user_agent2_commission'], 2, '.', ''));
        $this->assertSame('4.80', number_format((float) $data['user_agent3_commission'], 2, '.', ''));
        $this->assertSame('3.20', number_format((float) $data['user_agent4_commission'], 2, '.', ''));
        $this->assertSame('1.60', number_format((float) $data['user_agent5_commission'], 2, '.', ''));
    }

    public function test_agent_without_snapshot_id_does_not_receive_commission(): void
    {
        $channelRate = Mockery::mock(GetChannelRateDetailService::class);
        $channelRate->shouldReceive('excute')->once()->andReturn(0);
        $channelRate->shouldReceive('calculateCost')->once()->andReturn(0);
        app()->instance(GetChannelRateDetailService::class, $channelRate);

        $order = new DepositOrder([
            'user_id' => 100,
            'user_rate' => 0.01,
            'user_agent1_id' => 0,
            'user_agent1_rate' => 0.50,
            'merchant_rate' => 0,
            'merchant_agent1_id' => 0,
            'merchant_agent2_id' => 0,
            'merchant_agent3_id' => 0,
            'merchant_extra_fee' => 0,
            'channel_id' => 1,
            'payment_id' => 10,
        ]);
        $service = new class extends ConfirmPaySuccessService {
            public function calculate(DepositOrder $order, float $amount): array
            {
                return $this->successData($order, $amount);
            }
        };

        $data = $service->calculate($order, 100);

        $this->assertSame(0, $data['user_agent1_commission']);
    }
}
