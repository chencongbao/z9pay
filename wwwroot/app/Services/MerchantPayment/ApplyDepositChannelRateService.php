<?php

namespace App\Services\MerchantPayment;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class ApplyDepositChannelRateService
{
    private const RATE_FIELDS = [
        'merchant_rate',
        'merchant_agent1_rate',
        'merchant_agent2_rate',
        'merchant_agent3_rate',
    ];

    public function excute(array &$saveData, array &$updateData, int $orderId, $logService = null): array
    {
        $channelId = (int)($updateData['channel_id'] ?? 0);
        $result = App::make(MerchantOrderRateService::class)->fillDepositFinalRate($saveData, $channelId);
        if (empty($result['success'])) {
            return $result;
        }

        $updateData = array_merge($updateData, Arr::only($saveData, self::RATE_FIELDS));
        if ($logService) {
            $logService->excute($orderId, '命中商户代收费率', [
                'rate_source' => $result['source'] ?? '',
                'channel_id' => $channelId,
                'merchant_rate' => $saveData['merchant_rate'] ?? 0,
                'merchant_agent1_rate' => $saveData['merchant_agent1_rate'] ?? 0,
                'merchant_agent2_rate' => $saveData['merchant_agent2_rate'] ?? 0,
                'merchant_agent3_rate' => $saveData['merchant_agent3_rate'] ?? 0,
            ], 'debug');
        }

        return $result;
    }
}
