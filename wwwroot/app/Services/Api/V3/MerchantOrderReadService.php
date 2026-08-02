<?php

namespace App\Services\Api\V3;

use Illuminate\Support\Facades\App;
use App\Services\Order\OrderCacheService;

class MerchantOrderReadService
{
    public function getDepositOrder($orderNo, $mid): array
    {
        $order = App::make(OrderCacheService::class)->getDepositByMerchantOrder($mid, $orderNo);
        if (empty($order)) {
            return [];
        }

        return [
            'mid' => $order['mid'] ?? intval($mid),
            'order_no' => $order['order_no'] ?? $orderNo,
            'no' => $order['ordernumber'] ?? '',
            'pay_name' => $order['pay_name'] ?? '',
            'amount' => $order['pay_amount'] ?? $order['amount'] ?? 0,
            'actual_amount' => $order['actual_amount'] ?? 0,
            'fee' => $this->fee($order),
            'created_time' => $this->time($order['created_at'] ?? null),
            'deposit_time' => $order['success_time'] ?? 0,
            'notify_time' => $order['callback_time'] ?? 0,
            'status' => $this->depositStatus(intval($order['status'] ?? 0)),
            'utr' => $order['utr'] ?? '',
        ];
    }

    public function getTransferOrder($orderNo, $mid): array
    {
        $order = App::make(OrderCacheService::class)->getTransferByMerchantOrder($mid, $orderNo);
        if (empty($order)) {
            return [];
        }

        [$status, $failReason] = $this->transferStatus(intval($order['status'] ?? 0), $order['remark'] ?? '');

        return [
            'mid' => $order['mid'] ?? intval($mid),
            'order_no' => $order['order_no'] ?? $orderNo,
            'no' => $order['ordernumber'] ?? '',
            'amount' => $order['amount'] ?? 0,
            'actual_amount' => $order['actual_amount'] ?? 0,
            'fee' => $this->fee($order),
            'created_time' => $this->time($order['created_at'] ?? null),
            'deposit_time' => $order['success_time'] ?? 0,
            'notify_time' => $order['callback_time'] ?? 0,
            'status' => $status,
            'fail_reason' => $failReason,
            'utr' => $order['utr'] ?? '',
        ];
    }

    private function depositStatus(int $status): string
    {
        if ($status === 5) {
            return 'succeeded';
        }

        if ($status === 6) {
            return 'failed';
        }

        return 'inprogress';
    }

    private function transferStatus(int $status, $remark = ''): array
    {
        if ($status === 4) {
            return ['succeeded', ''];
        }

        if ($status === 5) {
            return ['failed', $remark ?: '请咨询客服'];
        }

        return ['inprogress', ''];
    }

    private function fee(array $order): float
    {
        return floatval($order['merchant_fee'] ?? 0) + floatval($order['merchant_extra_fee'] ?? 0);
    }

    private function time($value): int
    {
        if (empty($value)) {
            return 0;
        }

        if (is_numeric($value)) {
            return intval($value);
        }

        $time = strtotime($value);

        return $time ?: 0;
    }
}
