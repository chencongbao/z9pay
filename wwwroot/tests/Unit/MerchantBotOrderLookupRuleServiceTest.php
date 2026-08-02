<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Telegram\MerchantBotOrderLookupRuleService;

class MerchantBotOrderLookupRuleServiceTest extends TestCase
{
    public function test_it_distinguishes_unconfigured_and_configured_merchants(): void
    {
        $service = new MerchantBotOrderLookupRuleService();
        $rows = [
            ['mid' => 24, 'status' => 1, 'order_no_rules' => 'CZ[A-Z0-9]{6,20}'],
            ['mid' => 25, 'status' => 0, 'order_no_rules' => 'E[A-Z0-9]{6,20}'],
        ];

        $this->assertTrue($service->hasMerchantConfigurationInRows(24, $rows));
        $this->assertTrue($service->hasMerchantConfigurationInRows(25, $rows));
        $this->assertFalse($service->hasMerchantConfigurationInRows(26, $rows));
    }

    public function test_enabled_configuration_extracts_order_numbers(): void
    {
        $service = new MerchantBotOrderLookupRuleService();
        $rows = [['mid' => 24, 'status' => 1, 'order_no_rules' => "CZ[A-Z0-9]{6,20}\n(E[A-Z0-9]{6,20})"]];

        $this->assertSame(
            ['CZ12345678', 'EABC123456'],
            $service->extractOrderNumbersFromRows(24, '查单 CZ12345678 / EABC123456', $rows)
        );
    }

    public function test_disabled_configuration_does_not_extract_order_numbers(): void
    {
        $service = new MerchantBotOrderLookupRuleService();
        $rows = [['mid' => 24, 'status' => 0, 'order_no_rules' => 'CZ[A-Z0-9]{6,20}']];

        $this->assertSame([], $service->extractOrderNumbersFromRows(24, 'CZ12345678', $rows));
    }
}
