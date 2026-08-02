<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\DepositOrder\ConfirmPaySuccessService;
use App\Services\Cache\ChannelRate\GetChannelRateDetailService;

class ChannelCostRateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::forget($this->cacheKey());

        parent::tearDown();
    }

    public function test_percentage_cost_uses_matching_range_and_default_fallback(): void
    {
        $this->putConfig([
            'type' => 0,
            'rate' => 1,
            'fixed_rate' => 0,
            'rate_ranges' => [
                ['min_amount' => 100, 'max_amount' => 500, 'rate' => 2, 'fixed_rate' => 0],
                ['min_amount' => 500, 'max_amount' => 1000, 'rate' => 3, 'fixed_rate' => 0],
            ],
        ]);
        $service = app(GetChannelRateDetailService::class);

        $this->assertMoney(0.5, $service->calculateCost(901, 902, 50));
        $this->assertMoney(2, $service->calculateCost(901, 902, 100));
        $this->assertMoney(15, $service->calculateCost(901, 902, 500));
        $this->assertMoney(10, $service->calculateCost(901, 902, 1000));
    }

    public function test_fixed_cost_supports_values_at_or_below_one(): void
    {
        $this->putConfig([
            'type' => 1,
            'rate' => 0,
            'fixed_rate' => 0.5,
            'rate_ranges' => [
                ['min_amount' => 100, 'max_amount' => 500, 'rate' => 0, 'fixed_rate' => 1],
                ['min_amount' => 500, 'max_amount' => 0, 'rate' => 0, 'fixed_rate' => 0.25],
            ],
        ]);
        $service = app(GetChannelRateDetailService::class);

        $this->assertMoney(0.5, $service->calculateCost(901, 902, 50));
        $this->assertMoney(1, $service->calculateCost(901, 902, 100));
        $this->assertMoney(0.25, $service->calculateCost(901, 902, 500));
        $this->assertMoney(0.25, $service->calculateCost(901, 902, 999999));
    }

    public function test_fixed_cost_matches_configured_screenshot_boundaries(): void
    {
        $this->putConfig([
            'type' => 1,
            'rate' => 0,
            'fixed_rate' => 1,
            'rate_ranges' => [
                ['min_amount' => 10, 'max_amount' => 100, 'rate' => 0, 'fixed_rate' => 2],
                ['min_amount' => 100, 'max_amount' => 200, 'rate' => 0, 'fixed_rate' => 3],
                ['min_amount' => 200, 'max_amount' => 1000, 'rate' => 0, 'fixed_rate' => 4],
            ],
        ]);
        $service = app(GetChannelRateDetailService::class);

        foreach ([
            [9.99, 1],
            [10, 2],
            [99.99, 2],
            [100, 3],
            [199.99, 3],
            [200, 4],
            [999.99, 4],
            [1000, 1],
        ] as [$amount, $expectedCost]) {
            $this->assertMoney($expectedCost, $service->calculateCost(901, 902, $amount));
        }
    }

    public function test_deposit_success_uses_range_cost_when_calculating_profit(): void
    {
        $this->putConfig([
            'type' => 0,
            'rate' => 1,
            'fixed_rate' => 0,
            'rate_ranges' => [
                ['min_amount' => 500, 'max_amount' => 1000, 'rate' => 3, 'fixed_rate' => 0],
            ],
        ]);
        $order = new DepositOrder([
            'channel_id' => 901,
            'payment_id' => 902,
            'merchant_rate' => 0.05,
            'merchant_extra_fee' => 2,
            'merchant_agent1_id' => 0,
            'merchant_agent2_id' => 0,
            'merchant_agent3_id' => 0,
            'user_id' => 0,
        ]);
        $service = new class extends ConfirmPaySuccessService {
            public function calculate(DepositOrder $order, float $amount): array
            {
                return $this->successData($order, $amount);
            }
        };

        $data = $service->calculate($order, 500);

        $this->assertMoney(15, $data['channel_cost']);
        $this->assertMoney(12, $data['profit']);
    }

    private function putConfig(array $config): void
    {
        Cache::forever($this->cacheKey(), $config);
    }

    private function cacheKey(): string
    {
        return CacheConstPrefixService::CHANNEL_RATE_DETAIL . '901_902';
    }

    private function assertMoney(float $expected, float $actual): void
    {
        $this->assertSame(number_format($expected, 6, '.', ''), number_format($actual, 6, '.', ''));
    }
}
