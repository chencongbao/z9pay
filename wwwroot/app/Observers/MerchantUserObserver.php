<?php

namespace App\Observers;

use App\Models\MerchantUser;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Merchant\CacheApKeyService;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\Merchant\CacheMerchantWhiteIpByUsernameService;

class MerchantUserObserver
{
    private const BASE_INFO_FIELDS = ['status', 'login_white_ip', 'username'];

    public bool $afterCommit = true;

    public function saved(MerchantUser $model): void
    {
        if ((int) $model->pid > 0) {
            return;
        }

        if ($model->wasChanged('status')) {
            App::make(CacheApKeyService::class)->excute(optional($model->merchant_info)->offsetGet('appkey'), true);
        }

        if ($model->wasChanged('username')) {
            App::make(CacheMerchantWhiteIpByUsernameService::class)->forget((string) $model->getOriginal('username'));
        }

        if ($model->wasRecentlyCreated || $model->wasChanged(['username', 'login_white_ip'])) {
            App::make(CacheMerchantWhiteIpByUsernameService::class)->excute((string) $model->username, true);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged(self::BASE_INFO_FIELDS)) {
            App::make(CacheMerchantBaseInfoService::class)->excute($model->id, true);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged('status')) {
            App::make(GetMerchantListInfoService::class)->excute(true);
        }
    }

    public function deleted(MerchantUser $model): void
    {
        App::make(CacheMerchantWhiteIpByUsernameService::class)->forget((string) $model->username);
        $this->refreshCaches($model);
    }

    public function restored(MerchantUser $model): void
    {
        App::make(CacheMerchantWhiteIpByUsernameService::class)->excute((string) $model->username, true);
        $this->refreshCaches($model);
    }

    private function refreshCaches(MerchantUser $model): void
    {
        if ((int) $model->pid > 0) {
            return;
        }

        App::make(CacheApKeyService::class)->excute(optional($model->merchant_info)->offsetGet('appkey'), true);
        App::make(CacheMerchantBaseInfoService::class)->excute($model->id, true);
        App::make(GetMerchantListInfoService::class)->excute(true);
    }
}
