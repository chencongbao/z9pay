<?php

namespace App\Observers;

use App\Models\MerchantInfo;
use App\Models\MerchantPayment;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\Merchant\CacheMerchantWhiteIpByUsernameService;
use App\Services\Cache\MerchantPayment\RefreshMerchantPaymentRateCacheService;

class MerchantInfoObserver
{
    private const LIST_FIELDS = ['name', 'coder', 'agent_user_id', 'currency_id'];

    public bool $afterCommit = true;

    public function saved(MerchantInfo $model): void
    {
        if ($model->wasChanged('agent_user_id')) {
            MerchantPayment::query()->where('merchant_user_id', $model->merchant_user_id)->update(['agent_user_id' => $model->agent_user_id]);
            App::make(RefreshMerchantPaymentRateCacheService::class)->excute($model->merchant_user_id);
        }

        if ($model->wasChanged('is_usdt_ava_rate') && (int) $model->is_usdt_ava_rate === 1 && $model->usdt_ava_rate > 0 && $model->available_usdt_balance == 0) {
            MerchantInfo::query()->whereKey($model->merchant_user_id)->update([
                'available_usdt_balance' => bcdiv($model->available_balance, $model->usdt_ava_rate, 6),
            ]);
        }

        if ($model->wasChanged('is_usdt_ava_rate') && (int) $model->is_usdt_ava_rate === 0) {
            MerchantInfo::query()->whereKey($model->merchant_user_id)->update(['available_usdt_balance' => 0, 'usdt_ava_rate' => 0]);
        }

        App::make(CacheMerchantBaseInfoService::class)->excute($model->merchant_user_id, true);

        if ($model->wasRecentlyCreated || $model->wasChanged('coder')) {
            $this->refreshWhiteIpCache($model);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged(self::LIST_FIELDS)) {
            App::make(GetMerchantListInfoService::class)->excute(true);
        }
    }

    public function deleted(MerchantInfo $model): void
    {
        App::make(CacheMerchantBaseInfoService::class)->excute($model->merchant_user_id, true);
        App::make(GetMerchantListInfoService::class)->excute(true);
        $this->forgetWhiteIpCache($model);
    }

    public function restored(MerchantInfo $model): void
    {
        App::make(CacheMerchantBaseInfoService::class)->excute($model->merchant_user_id, true);
        App::make(GetMerchantListInfoService::class)->excute(true);
        $this->refreshWhiteIpCache($model);
    }

    private function refreshWhiteIpCache(MerchantInfo $model): void
    {
        $username = (string) optional($model->merchant_user)->username;
        if ($username !== '') {
            App::make(CacheMerchantWhiteIpByUsernameService::class)->excute($username, true);
        }
    }

    private function forgetWhiteIpCache(MerchantInfo $model): void
    {
        $username = (string) optional($model->merchant_user)->username;
        if ($username !== '') {
            App::make(CacheMerchantWhiteIpByUsernameService::class)->forget($username);
        }
    }
}
