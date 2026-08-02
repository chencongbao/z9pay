<?php

namespace App\Observers;

use App\Models\MerchantPayment;
use App\Services\Cache\MerchantPayment\RefreshMerchantPaymentRateCacheService;
use Illuminate\Support\Facades\App;

class MerchantPaymentObserver
{
    public $afterCommit = true;

    public function saved(MerchantPayment $model)
    {
        App::make(RefreshMerchantPaymentRateCacheService::class)->excute($model->merchant_user_id, [$model->payment_id]);
    }

    public function deleted(MerchantPayment $model)
    {
        App::make(RefreshMerchantPaymentRateCacheService::class)->excute($model->merchant_user_id, [$model->payment_id]);
    }
}
