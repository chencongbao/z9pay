<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\User\GetUserRemainingDepositService;

class UserRemainingDepositServiceTest extends TestCase
{
    public function test_calculate_remaining_deposit_uses_single_business_formula(): void
    {
        $data = app(GetUserRemainingDepositService::class)->calculate(1000, 300, 200, 150);

        $this->assertTrue($data['limited']);
        $this->assertSame(1000.0, $data['deposit_amount']);
        $this->assertSame(300.0, $data['transfer_balance_amount']);
        $this->assertSame(200.0, $data['deposit_balance_amount']);
        $this->assertSame(150.0, $data['pending_deposit_amount']);
        $this->assertSame(1300.0, $data['total_deposit_amount']);
        $this->assertSame(950.0, $data['remaining_deposit']);
    }

    public function test_zero_deposit_amount_means_unlimited(): void
    {
        $data = app(GetUserRemainingDepositService::class)->calculate(0, 300, 200, 150);

        $this->assertFalse($data['limited']);
        $this->assertSame(-50.0, $data['remaining_deposit']);
    }
}
