<?php

namespace App\Observers;

use App\Models\Channel;
use App\Models\MerchantChannel;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Channel\GetChannelListService;
use App\Services\Cache\Channel\CoderByChannelIdService;
use App\Services\Cache\Channel\ChannelIdByClassNameService;
use App\Services\Cache\Channel\ChannelInfoByChannelIdService;
use App\Services\Cache\Channel\ChannelWhiteIpByClassNameService;
use App\Services\Cache\MerchantChannel\GetMerchantChannelListService;

class ChannelObserver
{
    public bool $afterCommit = true;

    public function saved(Channel $model): void
    {
        App::make(ChannelInfoByChannelIdService::class)->excute($model->id, true);

        if ($model->wasRecentlyCreated || $model->wasChanged('coder')) {
            App::make(CoderByChannelIdService::class)->excute($model->id, true);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged('classname')) {
            $this->refreshChannelIdCaches($model);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged(['classname', 'callback_white_ip'])) {
            $this->refreshCallbackWhiteIpCaches($model);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged(['name', 'status'])) {
            App::make(GetChannelListService::class)->excute(true);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged([
            'name',
            'status',
            'classname',
            'currency',
            'is_real_name',
            'transfer_payment',
        ])) {
            $this->refreshMerchantChannelListCache($model);
        }
    }

    public function deleted(Channel $model): void
    {
        App::make(ChannelInfoByChannelIdService::class)->excute($model->id, true);
        App::make(CoderByChannelIdService::class)->excute($model->id, true);
        $this->refreshChannelIdCaches($model);
        $this->refreshCallbackWhiteIpCaches($model);
        App::make(GetChannelListService::class)->excute(true);
        $this->refreshMerchantChannelListCache($model);
    }

    private function refreshChannelIdCaches(Channel $model): void
    {
        foreach ($this->classNames($model) as $className) {
            App::make(ChannelIdByClassNameService::class)->excute($className, true);
        }
    }

    private function refreshCallbackWhiteIpCaches(Channel $model): void
    {
        foreach ($this->classNames($model) as $className) {
            App::make(ChannelWhiteIpByClassNameService::class)->excute($className, true);
        }
    }

    private function classNames(Channel $model): array
    {
        return array_values(array_unique(array_filter([
            $model->getOriginal('classname'),
            $model->classname,
        ])));
    }

    private function refreshMerchantChannelListCache(Channel $model): void
    {
        $items = MerchantChannel::query()
            ->where('channel_id', $model->id)
            ->select(['merchant_user_id', 'payment_id'])
            ->distinct()
            ->get();

        $refreshed = [];
        foreach ($items as $item) {
            if (empty($item->merchant_user_id) || empty($item->payment_id)) {
                continue;
            }

            $key = $item->merchant_user_id . '_' . $item->payment_id;
            if (isset($refreshed[$key])) {
                continue;
            }
            $refreshed[$key] = true;

            App::make(GetMerchantChannelListService::class)->excute($item->merchant_user_id, $item->payment_id, true);
        }
    }
}
