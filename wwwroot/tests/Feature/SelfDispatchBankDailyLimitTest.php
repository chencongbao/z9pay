<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SelfChannel\SelfDispatchAnalyzeService;

class SelfDispatchBankDailyLimitTest extends TestCase
{
    /**
     * @dataProvider dailyLimitProvider
     */
    public function test_daily_limit_includes_incoming_order(float $current, float $order, float $limit, bool $exceeds): void
    {
        $service = new class extends SelfDispatchAnalyzeService {
            public function exceeds(float $currentAmount, float $orderAmount, float $limitAmount): bool
            {
                return $this->exceedsBankDailyAmountLimit($currentAmount, $orderAmount, $limitAmount);
            }
        };

        $this->assertSame($exceeds, $service->exceeds($current, $order, $limit));
    }

    public function dailyLimitProvider(): array
    {
        return [
            'limit disabled' => [900, 200, 0, false],
            'below limit' => [700, 200, 1000, false],
            'exactly reaches limit' => [900, 100, 1000, false],
            'exceeds limit by one cent' => [900, 100.01, 1000, true],
            'single order exceeds limit' => [0, 1000.01, 1000, true],
        ];
    }
}
