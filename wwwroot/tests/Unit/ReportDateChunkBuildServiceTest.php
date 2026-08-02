<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Report\ReportDateChunkBuildService;

class ReportDateChunkBuildServiceTest extends TestCase
{
    public function test_merchant_created_and_success_time_metrics_are_classified_separately(): void
    {
        $service = new ReportDateChunkBuildService();
        $this->setProperty($service, 'date', '2026-07-18');

        $this->invoke($service, 'addDepositCreatedOrder', [$this->depositOrder(5, 120.25)]);
        $this->invoke($service, 'addDepositCreatedOrder', [$this->depositOrder(6, 80)]);
        $this->invoke($service, 'addTransferCreatedOrder', [$this->transferOrder(4, 210), 'transfer']);
        $this->invoke($service, 'addTransferCreatedOrder', [$this->transferOrder(5, 90), 'transfer']);
        $this->invoke($service, 'addTransferCreatedOrder', [$this->transferOrder(4, 310), 'settlement']);
        $this->invoke($service, 'addDepositSuccessOrder', [$this->depositSuccessOrder(125.5)]);
        $this->invoke($service, 'addTransferSuccessOrder', [$this->transferSuccessOrder(215), 'transfer']);
        $this->invoke($service, 'addTransferSuccessOrder', [$this->transferSuccessOrder(315), 'settlement']);

        foreach ([
            [2, -100], [2, -35], [5, 20],
            [6, -300], [6, -25], [15, 40],
            [9, -70], [9, -30], [10, 15],
            [11, 8], [12, -3],
        ] as [$type, $amount]) {
            $this->invoke($service, 'addMerchantBalanceLog', [(object) ['mid' => 24, 'type' => $type, 'amount' => $amount]]);
        }

        $stats = $this->getProperty($service, 'stats')['report_merchants']['24'];
        $this->assertSame(1.0, $stats['deposit_order_number_success']);
        $this->assertSame(120.25, $stats['deposit_order_total_amount']);
        $this->assertSame(1.0, $stats['deposit_created_success_number']);
        $this->assertSame(125.5, $stats['deposit_created_success_amount']);
        $this->assertSame(1.0, $stats['transfer_order_number_success']);
        $this->assertSame(210.0, $stats['transfer_order_total_amount']);
        $this->assertSame(1.0, $stats['transfer_created_success_number']);
        $this->assertSame(215.0, $stats['transfer_created_success_amount']);
        $this->assertSame(1.0, $stats['settlement_order_number_success']);
        $this->assertSame(310.0, $stats['settlement_order_total_amount']);
        $this->assertSame(1.0, $stats['settlement_created_success_number']);
        $this->assertSame(315.0, $stats['settlement_created_success_amount']);

        $this->assertSame(2.0, $stats['transfer_deduct_number']);
        $this->assertSame(135.0, $stats['transfer_deduct_amount']);
        $this->assertSame(1.0, $stats['transfer_corre_number']);
        $this->assertSame(20.0, $stats['transfer_corre_amount']);
        $this->assertSame(2.0, $stats['settlement_deduct_number']);
        $this->assertSame(325.0, $stats['settlement_deduct_amount']);
        $this->assertSame(1.0, $stats['settlement_corre_number']);
        $this->assertSame(40.0, $stats['settlement_corre_amount']);
        $this->assertSame(2.0, $stats['deposit_freeze_number']);
        $this->assertSame(100.0, $stats['deposit_freeze_amount']);
        $this->assertSame(1.0, $stats['deposit_unfreeze_number']);
        $this->assertSame(15.0, $stats['deposit_unfreeze_amount']);
        $this->assertSame(8.0, $stats['add_total_amount']);
        $this->assertSame(3.0, $stats['jian_total_amount']);
    }

    private function depositOrder(int $status, float $actualAmount): object
    {
        return (object) [
            'mid' => 24,
            'user_id' => 0,
            'user_bank_id' => 0,
            'channel_id' => 0,
            'payment_id' => 0,
            'currency_id' => 0,
            'status' => $status,
            'actual_amount' => $actualAmount,
        ];
    }

    private function transferOrder(int $status, float $actualAmount): object
    {
        return (object) [
            'mid' => 24,
            'user_id' => 0,
            'channel_id' => 0,
            'currency_id' => 0,
            'status' => $status,
            'actual_amount' => $actualAmount,
        ];
    }

    private function depositSuccessOrder(float $actualAmount): object
    {
        return (object) array_merge((array) $this->depositOrder(5, $actualAmount), $this->successOrderValues());
    }

    private function transferSuccessOrder(float $actualAmount): object
    {
        return (object) array_merge((array) $this->transferOrder(4, $actualAmount), $this->successOrderValues());
    }

    private function successOrderValues(): array
    {
        return [
            'merchant_fee' => 0,
            'merchant_extra_fee' => 0,
            'profit' => 0,
            'user_commission' => 0,
            'user_agent1_id' => 0,
            'user_agent2_id' => 0,
            'user_agent3_id' => 0,
            'user_agent4_id' => 0,
            'user_agent5_id' => 0,
            'user_agent1_commission' => 0,
            'user_agent2_commission' => 0,
            'user_agent3_commission' => 0,
            'user_agent4_commission' => 0,
            'user_agent5_commission' => 0,
            'merchant_agent1_id' => 0,
            'merchant_agent2_id' => 0,
            'merchant_agent3_id' => 0,
            'merchant_agent1_commission' => 0,
            'merchant_agent2_commission' => 0,
            'merchant_agent3_commission' => 0,
        ];
    }

    private function invoke(object $object, string $method, array $arguments = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    private function setProperty(object $object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    private function getProperty(object $object, string $property)
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
