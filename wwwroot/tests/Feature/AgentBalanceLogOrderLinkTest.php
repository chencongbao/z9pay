<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\AgentAdmin\Controllers\BalanceLogController;
use App\AgentAdmin\Controllers\DepositOrderController;
use App\AgentAdmin\Controllers\TransferOrderController;

class AgentBalanceLogOrderLinkTest extends TestCase
{
    public function test_historical_deposit_and_transfer_links_include_the_log_day(): void
    {
        $controller = new TestableBalanceLogController();

        foreach (['D20260718183543323473237', 'T20260718183543323473237'] as $ordernumber) {
            $this->assertSame([
                'ordernumber' => $ordernumber,
                'created_at' => [
                    'start' => '2026-07-18 00:00:00',
                    'end' => '2026-07-18 23:59:59',
                ],
            ], $controller->linkParameters($ordernumber, '2026-07-18 18:35:43'));
        }
    }

    public function test_order_pages_use_the_link_date_range_when_provided(): void
    {
        $request = Request::create('/agent/deposit-orders', 'GET', [
            'created_at' => ['start' => '2026-07-18 00:00:00', 'end' => '2026-07-18 23:59:59'],
        ]);
        $this->app->instance('request', $request);

        $expected = ['2026-07-18 00:00:00', '2026-07-18 23:59:59'];
        $this->assertSame($expected, (new TestableDepositOrderController())->dateRange());
        $this->assertSame($expected, (new TestableTransferOrderController())->dateRange());
    }

    public function test_normal_order_pages_keep_today_as_the_default_range(): void
    {
        $this->travelTo('2026-07-23 12:00:00');
        $this->app->instance('request', Request::create('/agent/deposit-orders', 'GET'));

        $expected = ['2026-07-23 00:00:00', '2026-07-23 23:59:59'];
        $this->assertSame($expected, (new TestableDepositOrderController())->dateRange());
        $this->assertSame($expected, (new TestableTransferOrderController())->dateRange());
    }
}

class TestableBalanceLogController extends BalanceLogController
{
    public function linkParameters(string $ordernumber, $createdAt): array
    {
        return $this->orderLinkParameters($ordernumber, $createdAt);
    }
}

class TestableDepositOrderController extends DepositOrderController
{
    public function dateRange(): array
    {
        return $this->orderDateRange();
    }
}

class TestableTransferOrderController extends TransferOrderController
{
    public function dateRange(): array
    {
        return $this->orderDateRange();
    }
}
