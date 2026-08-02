<?php

namespace App\Services\DepositOrder;

use App\Models\DepositOrder;
use Illuminate\Support\Facades\App;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class DepositOrderPayAmountService
{
    public function applyByChannel(DepositOrder $order, array $channel, $logService = null): DepositOrder
    {
        $merchantInfo = App::make(CacheMerchantBaseInfoService::class)->excute($channel['mid'] ?? $order->mid);
        $payAmountResult = $this->payAmount($channel, $merchantInfo ?: []);
        $payAmount = $payAmountResult['pay_amount'];

        if ($this->sameAmount($order->pay_amount, $payAmount) && $this->sameAmount($order->show_amount, $payAmount)) {
            $this->writeLog($logService, $order, $channel, $payAmountResult, '金额未变化');

            return $order;
        }

        $order->forceFill([
            'pay_amount' => $payAmount,
            'show_amount' => $payAmount,
        ])->save();

        App::make(OrderCacheService::class)->putDeposit($order, true);

        $order = $order->refresh();
        $this->writeLog($logService, $order, $channel, $payAmountResult, '金额已更新');

        return $order;
    }

    public function applyByChannelData(array $channel): ?DepositOrder
    {
        $orderId = intval($channel['deposit_order_id'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $order = DepositOrder::query()->find($orderId);
        if (!$order) {
            return null;
        }

        return $this->applyByChannel($order, $channel);
    }

    private function payAmount(array $channel, array $merchantInfo): array
    {
        $amount = $this->normalizeAmount($channel['amount'] ?? 0);
        $floatAmount = $this->floatAmount($channel, $merchantInfo);

        return [
            'amount' => $amount,
            'pay_amount' => bcadd($amount, $floatAmount, 2),
            'float_amount' => $floatAmount,
            'float_status' => intval($channel['float_status'] ?? 0),
            'amount_float_type' => intval($merchantInfo['amount_float_type'] ?? 0),
            'max_float_amount' => $merchantInfo['float_amount'] ?? 0,
            'is_need_decimal' => intval($merchantInfo['is_need_decimal'] ?? 1),
        ];
    }

    private function floatAmount(array $channel, array $merchantInfo): string
    {
        if (intval($channel['float_status'] ?? 0) <= 0 || intval($merchantInfo['amount_float_type'] ?? 0) <= 0) {
            return '0.00';
        }

        $maxAmount = (float)($merchantInfo['float_amount'] ?? 0);
        if ($maxAmount <= 1) {
            return '0.00';
        }

        $randomAmount = mt_rand(1, (int)$maxAmount);
        if (intval($merchantInfo['is_need_decimal'] ?? 1) !== 0) {
            $randomAmount = bcdiv((string)$randomAmount, '100', 2);
        } else {
            $randomAmount = $this->normalizeAmount($randomAmount);
        }

        return intval($merchantInfo['amount_float_type'] ?? 0) === 2 ? '-' . ltrim((string)$randomAmount, '-') : (string)$randomAmount;
    }

    private function normalizeAmount($amount): string
    {
        return bcadd((string)$amount, '0', 2);
    }

    private function sameAmount($left, $right): bool
    {
        return bccomp((string)$left, (string)$right, 2) === 0;
    }

    private function writeLog($logService, DepositOrder $order, array $channel, array $payAmountResult, string $status): void
    {
        if (!$logService || !method_exists($logService, 'excute')) {
            return;
        }

        $logService->excute($order->id, '代收订单最终支付金额计算', [
            '处理状态' => $status,
            '请求金额' => $payAmountResult['amount'],
            '渠道浮动' => $payAmountResult['float_status'] > 0 ? '开启' : '关闭',
            '商户浮动模式' => $this->floatTypeText($payAmountResult['amount_float_type']),
            '最大差额' => $payAmountResult['max_float_amount'],
            '需要小数' => $payAmountResult['is_need_decimal'] === 0 ? '否' : '是',
            '浮动金额' => $payAmountResult['float_amount'],
            '最终支付金额' => $payAmountResult['pay_amount'],
            '渠道ID' => $channel['channel_id'] ?? 0,
            '渠道名称' => $channel['name'] ?? '',
        ], 'debug');
    }

    private function floatTypeText(int $type): string
    {
        return [0 => '关闭', 1 => '上浮', 2 => '下浮'][$type] ?? '未知';
    }
}
