<?php

namespace App\Services\Cache\MerchantPayment;

use App\Models\MerchantPayment;
use Illuminate\Support\Facades\App;

class RefreshMerchantPaymentRateCacheService
{
    public function excute($mid, array $paymentIds = []): void
    {
        $mid = intval($mid);
        if ($mid <= 0) {
            return;
        }

        $paymentIds = array_merge(
            $paymentIds,
            MerchantPayment::query()->where('merchant_user_id', $mid)->pluck('payment_id')->all()
        );
        $paymentIds = array_values(array_unique(array_filter(array_map('intval', $paymentIds))));

        foreach ($paymentIds as $paymentId) {
            App::make(GetMerchantPaymentRateListService::class)->excute($mid, $paymentId, true);
        }

        if (in_array(7, $paymentIds, true)) {
            App::make(GetMerchantTransferBankRateService::class)->excute($mid, true);
        }
    }
}
