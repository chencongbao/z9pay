<?php

namespace App\Observers;

use App\Models\MerchantChannel;
use App\Services\Cache\MerchantChannel\GetMerchantChannelListService;
use Illuminate\Support\Facades\App;

class MerchantChannelObserver
{
    public $afterCommit = true;

    public function saved(MerchantChannel $model)
    {
        $this->refreshCache($model);
    }

    public function deleted(MerchantChannel $model)
    {
        $this->refreshCache($model);
    }

    private function refreshCache(MerchantChannel $model): void
    {
        $items = [
            [
                'merchant_user_id' => $model->getOriginal('merchant_user_id'),
                'payment_id' => $model->getOriginal('payment_id'),
            ],
            [
                'merchant_user_id' => $model->merchant_user_id,
                'payment_id' => $model->payment_id,
            ],
        ];

        $refreshed = [];
        foreach ($items as $item) {
            if (empty($item['merchant_user_id']) || empty($item['payment_id'])) {
                continue;
            }

            $key = $item['merchant_user_id'] . '_' . $item['payment_id'];
            if (isset($refreshed[$key])) {
                continue;
            }
            $refreshed[$key] = true;

            App::make(GetMerchantChannelListService::class)->excute($item['merchant_user_id'], $item['payment_id'], true);
        }
    }
}
