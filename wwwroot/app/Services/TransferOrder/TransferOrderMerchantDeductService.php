<?php

namespace App\Services\TransferOrder;

use RuntimeException;
use App\Models\TransferOrder;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\App;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Services\MerchantChannel\CheckMerchantChannelWhereService;
use App\Services\MerchantPayment\ApplyTransferChannelBankRateService;

class TransferOrderMerchantDeductService
{
    private const PAYMENT_ID_TRANSFER = 7;
    private const ORDER_TYPE_TRANSFER = 2;
    private const TRANSFER_DEDUCT_TYPE = 2;
    private const TRANSFER_REVERSE_TYPE = 5;
    private const SETTLEMENT_DEDUCT_TYPE = 6;
    private const SETTLEMENT_REVERSE_TYPE = 15;

    public function deductForChannel(TransferOrder $order, int $channelId, int $adminId = 0, string $deductRemark = '', ?string $originalOrdernumber = null, $logService = null, $merchantExtraFee = null): array
    {
        $originalOrdernumber = $originalOrdernumber ?: $order->ordernumber;
        // 自动派发已经选中具体商户通道时，优先使用选中通道的额外手续费，避免同渠道多配置时二次匹配错费率。
        $extraFee = $merchantExtraFee === null ? bob_amount_format(App::make(CheckMerchantChannelWhereService::class)->excute($order->mid, $channelId, $order->amount)) : bob_amount_format($merchantExtraFee);
        $this->applyRate($order, $channelId, $logService);

        $merchantFee = bob_amount_format((float)$order->amount * (float)$order->merchant_rate);
        $totalFee = bob_amount_format($merchantFee + $extraFee);
        if ((float)$order->amount + (float)$totalFee <= 0) {
            throw new RuntimeException('代付金额计算异常');
        }

        $balanceService = App::make(MerchantBalanceChangeService::class);
        $oldDeductLog = $this->oldDeductLog($order, $originalOrdernumber);
        if ($oldDeductLog) {
            $this->assertBalanceResult($balanceService->excute($this->reverseBalanceData($order, $oldDeductLog, self::TRANSFER_REVERSE_TYPE, $adminId, $originalOrdernumber)), '商户余额冲正失败');
        }

        $this->assertBalanceResult($balanceService->deductTransferOrder($order, (float)$order->amount, (float)$totalFee, $deductRemark, $adminId), '商户代付扣款失败');

        $order->forceFill([
            'merchant_fee' => $merchantFee,
            'merchant_extra_fee' => $extraFee,
            'channel_id' => $channelId,
        ]);

        return [
            'merchant_fee' => $merchantFee,
            'merchant_extra_fee' => $extraFee,
            'total_fee' => $totalFee,
        ];
    }

    public function deductSettlementForChannel(TransferOrder $order, int $channelId, int $adminId = 0, string $deductRemark = '', ?string $originalOrdernumber = null): array
    {
        $originalOrdernumber = $originalOrdernumber ?: $order->ordernumber;
        $feeData = $this->fillSettlementFeeForChannel($order, $channelId);
        $totalFee = $feeData['total_fee'];
        if ((float)$order->amount + (float)$totalFee <= 0) {
            throw new RuntimeException('结算金额计算异常');
        }

        $balanceService = App::make(MerchantBalanceChangeService::class);
        $oldDeductLog = $this->oldDeductLog($order, $originalOrdernumber, self::SETTLEMENT_DEDUCT_TYPE, self::SETTLEMENT_REVERSE_TYPE);
        if ($oldDeductLog) {
            $this->assertBalanceResult($balanceService->excute($this->reverseBalanceData($order, $oldDeductLog, self::SETTLEMENT_REVERSE_TYPE, $adminId, $originalOrdernumber)), '商户结算冲正失败');
        }

        $this->assertBalanceResult($balanceService->deductSettlementOrder($order, (float)$order->amount, (float)$totalFee, $deductRemark, $adminId), '商户结算扣款失败');

        return $feeData;
    }

    public function fillSettlementFeeForChannel(TransferOrder $order, int $channelId): array
    {
        $extraFee = bob_amount_format(App::make(CheckMerchantChannelWhereService::class)->excute($order->mid, $channelId, $order->amount));
        $merchantFee = bob_amount_format((float)$order->amount * (float)$order->merchant_rate);
        $totalFee = bob_amount_format($merchantFee + $extraFee);

        $order->forceFill([
            'merchant_fee' => $merchantFee,
            'merchant_extra_fee' => $extraFee,
            'channel_id' => $channelId,
        ]);

        return [
            'merchant_fee' => $merchantFee,
            'merchant_extra_fee' => $extraFee,
            'total_fee' => $totalFee,
        ];
    }

    private function applyRate(TransferOrder $order, int $channelId, $logService = null): void
    {
        $rateResult = App::make(ApplyTransferChannelBankRateService::class)->excute($order, $channelId, $logService);
        if (empty($rateResult['success'])) {
            throw new RuntimeException($rateResult['zh_message'] ?? '未匹配到代付费率,请联系客服确认代付金额');
        }
    }

    private function oldDeductLog(TransferOrder $order, string $ordernumber, int $type = self::TRANSFER_DEDUCT_TYPE, int $reverseType = self::TRANSFER_REVERSE_TYPE): ?MerchantBalanceLog
    {
        $query = MerchantBalanceLog::query()
            ->where('type', $type)
            ->where('type_id', $order->id);

        if ($type !== self::SETTLEMENT_DEDUCT_TYPE) {
            $query->where('payment_id', self::PAYMENT_ID_TRANSFER)
                ->whereIn('order_type', [0, self::ORDER_TYPE_TRANSFER]);
        }

        $logs = $query
            ->orderByDesc('id')
            ->get(['id', 'fee', 'amount', 'type_id', 'payment_id', 'order_type', 'ordernumber']);

        $exactLog = $logs->first(function (MerchantBalanceLog $log) use ($ordernumber, $reverseType) {
            return (string)$log->ordernumber === $ordernumber && !$this->deductLogReversed($log, $reverseType);
        });
        if ($exactLog) {
            return $exactLog;
        }

        return $logs->first(function (MerchantBalanceLog $log) use ($reverseType) {
            return !$this->deductLogReversed($log, $reverseType);
        });
    }

    private function deductLogReversed(MerchantBalanceLog $log, int $reverseType): bool
    {
        $query = MerchantBalanceLog::query()
            ->where('type', $reverseType)
            ->where('type_id', $log->type_id)
            ->where('ordernumber', $log->ordernumber);

        if ($reverseType !== self::SETTLEMENT_REVERSE_TYPE) {
            $query->where('payment_id', $log->payment_id)
                ->whereIn('order_type', array_unique([intval($log->order_type), self::ORDER_TYPE_TRANSFER]));
        }

        return $query->exists();
    }

    private function assertBalanceResult(array $result, string $message): void
    {
        if (empty($result['success'])) {
            throw new RuntimeException($result['message'] ?? $message);
        }
    }

    private function deductBalanceData(TransferOrder $order, int $type, float $totalFee, string $remark, int $adminId): array
    {
        return [
            'mid' => $order->mid,
            'amount' => -$order->amount,
            'fee' => $totalFee,
            'type' => $type,
            'type_id' => $order->id,
            'currency_id' => $order->currency_id,
            'payment_id' => self::PAYMENT_ID_TRANSFER,
            'order_type' => self::ORDER_TYPE_TRANSFER,
            'remark' => $remark,
            'admin_id' => $adminId,
            'ordernumber' => $order->ordernumber,
            'order_no' => $order->order_no,
        ];
    }

    private function reverseBalanceData(TransferOrder $order, MerchantBalanceLog $oldDeductLog, int $type, int $adminId, string $originalOrdernumber): array
    {
        return [
            'mid' => $order->mid,
            'amount' => -1 * $oldDeductLog->amount,
            'fee' => bob_amount_format(-1 * abs($oldDeductLog->fee)),
            'type' => $type,
            'type_id' => $order->id,
            'currency_id' => $order->currency_id,
            'payment_id' => self::PAYMENT_ID_TRANSFER,
            'order_type' => self::ORDER_TYPE_TRANSFER,
            'remark' => '冲正：' . $originalOrdernumber,
            'admin_id' => $adminId,
            'ordernumber' => $originalOrdernumber,
            'order_no' => $order->order_no,
        ];
    }
}
