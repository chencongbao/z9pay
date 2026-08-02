<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SelfNewPayment\GetUserBankSameAmountTimeService;

class UserBankSameAmountIntervalCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_failed_reservation_cache_can_be_removed_precisely(): void
    {
        $service = app(GetUserBankSameAmountTimeService::class);
        $service->excute(86, 100, 10);
        $service->excute(86, 200, 10);
        $service->excute(87, 100, 10);

        $this->assertGreaterThan(0, $service->excute(86, 100));
        $this->assertTrue($service->forget(86, 100));

        $this->assertSame(0, $service->excute(86, 100));
        $this->assertGreaterThan(0, $service->excute(86, 200));
        $this->assertGreaterThan(0, $service->excute(87, 100));
    }

    public function test_invalid_bank_or_amount_does_not_touch_cache(): void
    {
        $service = app(GetUserBankSameAmountTimeService::class);

        $this->assertFalse($service->forget(0, 100));
        $this->assertFalse($service->forget(86, 0));
    }
}
