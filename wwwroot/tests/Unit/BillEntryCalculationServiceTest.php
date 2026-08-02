<?php

namespace Tests\Unit;

use stdClass;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use App\Models\BillLog;
use App\Extendtions\Telegram\BillAction;
use App\Services\Telegram\BillEntryCalculationService;

class BillEntryCalculationServiceTest extends TestCase
{
    private BillEntryCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BillEntryCalculationService();
    }

    public function test_cny_income_uses_its_own_rate_and_fee_snapshot(): void
    {
        $result = $this->service->calculate(1000, BillEntryCalculationService::CURRENCY_CNY, 7.2, 2, true);

        $this->assertSame(1000.0, $result['amount']);
        $this->assertSame(980.0, $result['payable_amount']);
        $this->assertSame(7.2, $result['exchange_rate']);
        $this->assertSame(2.0, $result['fee_rate']);
    }

    public function test_usdt_income_is_converted_before_its_fee_is_calculated(): void
    {
        $result = $this->service->calculate(100, BillEntryCalculationService::CURRENCY_USDT, 7.2, 2, true);

        $this->assertSame(720.0, $result['amount']);
        $this->assertSame(705.6, $result['payable_amount']);
        $this->assertSame(100.0, $result['original_amount']);
        $this->assertSame(BillEntryCalculationService::CURRENCY_USDT, $result['original_currency']);
    }

    public function test_outgoing_entries_keep_the_snapshot_without_charging_the_fee_again(): void
    {
        $result = $this->service->calculate(100, BillEntryCalculationService::CURRENCY_USDT, 7.2, 2, false);

        $this->assertSame(720.0, $result['amount']);
        $this->assertNull($result['payable_amount']);
        $this->assertSame(7.2, $result['exchange_rate']);
        $this->assertSame(2.0, $result['fee_rate']);
    }

    public function test_new_settings_only_change_the_new_entry_snapshot(): void
    {
        $first = $this->service->calculate(100, BillEntryCalculationService::CURRENCY_USDT, 7.2, 2, true);
        $second = $this->service->calculate(100, BillEntryCalculationService::CURRENCY_USDT, 7.5, 3, true);

        $this->assertSame(7.2, $first['exchange_rate']);
        $this->assertSame(2.0, $first['fee_rate']);
        $this->assertSame(705.6, $first['payable_amount']);
        $this->assertSame(7.5, $second['exchange_rate']);
        $this->assertSame(3.0, $second['fee_rate']);
        $this->assertSame(727.5, $second['payable_amount']);
    }

    public function test_large_usdt_amount_is_calculated_without_display_formatting(): void
    {
        $result = $this->service->calculate(100000, BillEntryCalculationService::CURRENCY_USDT, 7, 2, true);

        $this->assertSame(700000.0, $result['amount']);
        $this->assertSame(686000.0, $result['payable_amount']);
    }

    public function test_saved_log_snapshot_is_not_recalculated_with_new_group_settings(): void
    {
        $log = new BillLog([
            'type' => 1,
            'amount' => 720,
            'original_currency' => BillEntryCalculationService::CURRENCY_USDT,
            'original_amount' => 100,
            'exchange_rate' => 7.2,
            'fee_rate' => 2,
            'payable_amount' => 705.6,
        ]);
        $action = new BillAction(new stdClass(), $this->service);
        $method = new \ReflectionMethod($action, 'resolveBillLogAmounts');
        $method->setAccessible(true);

        $result = $method->invoke($action, $log, 9, 9);

        $this->assertSame(720.0, $result['cny_amount']);
        $this->assertSame(100.0, $result['usd_amount']);
        $this->assertSame(705.6, $result['payable_cny_amount']);
        $this->assertSame(98.0, $result['payable_usd_amount']);
        $this->assertSame(7.2, $result['exchange_rate']);
        $this->assertSame(2.0, $result['fee_rate']);
    }

    public function test_usdt_entry_requires_a_positive_exchange_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('请先设置大于0的汇率');

        $this->service->calculate(100, BillEntryCalculationService::CURRENCY_USDT, 0, 2, true);
    }
}
