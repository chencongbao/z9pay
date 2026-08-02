<?php

namespace App\Services\MerchantPayment;

use App\Models\TransferOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class ApplyTransferChannelBankRateService
{
    private const RATE_FIELDS = [
        'merchant_rate',
        'merchant_agent1_rate',
        'merchant_agent2_rate',
        'merchant_agent3_rate',
    ];

    public function excute(TransferOrder $order, int $channelId, $logService = null): array
    {
        $saveData = [
            'mid' => $order->mid,
            'bank_id' => $order->bank_id,
            'amount' => $order->amount,
            'merchant_rate' => $order->merchant_rate,
            'merchant_agent1_rate' => $order->merchant_agent1_rate,
            'merchant_agent2_rate' => $order->merchant_agent2_rate,
            'merchant_agent3_rate' => $order->merchant_agent3_rate,
        ];

        $result = App::make(MerchantOrderRateService::class)->fillTransferFinalRate($saveData, $channelId);
        if (empty($result['success'])) {
            return $result;
        }

        $order->fill(Arr::only($saveData, self::RATE_FIELDS));
        if ($logService) {
            $logService->excute($order->id, '命中商户代付费率', [
                'rate_source' => $result['source'] ?? '',
                '渠道ID' => $channelId,
                '银行ID' => $order->bank_id,
                '代付费率' => $saveData['merchant_rate'] ?? 0,
                '一级代理费率' => $saveData['merchant_agent1_rate'] ?? 0,
                '二级代理费率' => $saveData['merchant_agent2_rate'] ?? 0,
                '三级代理费率' => $saveData['merchant_agent3_rate'] ?? 0,
            ], 'debug');
        }

        return $result;
    }
}
