<?php

namespace App\Services\Api;

use App\Traits\ServiceTraits;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use App\Services\SystemNotice\SystemNoticeService;

class CheckMerchantSignService
{
    use ServiceTraits;

    public function excute($data = [], $merchantInfo = [], $error = '', $signSpace = 1): bool
    {
        $sign = bob_sign(Arr::except($data, ['sign']), $merchantInfo['appsecret'] ?? '');

        if (bob_check_sign($sign, $data['sign'] ?? '', $signSpace)) {
            return true;
        }

        $this->noticeSignError($data, $merchantInfo, $error, $sign, $signSpace);

        return false;
    }

    private function noticeSignError(array $data, array $merchantInfo, string $error, string $sign, int $signSpace): void
    {
        App::make(SystemNoticeService::class)->warning(
            'merchant_sign_error',
            $this->signErrorLogData($data, $merchantInfo, $error, $sign, $signSpace),
            SystemNoticeService::DEFAULT_TTL_SECONDS,
            intval($data['mid'] ?? 0) ?: null
        );
    }

    private function signErrorLogData(array $data, array $merchantInfo, string $error, string $sign, int $signSpace): array
    {
        $logData = $data;
        $logData['sign_string'] = bob_sign_string(Arr::except($data, ['sign']));
        $logData['appsecret'] = bob_str_replace($merchantInfo['appsecret'] ?? '');
        $logData['self_sign'] = $sign;
        $logData['error'] = $error;
        $logData['sign_space'] = $signSpace;

        return $logData;
    }
}
