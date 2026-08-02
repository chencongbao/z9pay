<?php

namespace App\Observers;

use App\Models\ChannelAccount;
use Illuminate\Support\Facades\App;
use App\Services\Cache\ChannelAccount\CacheLastChannelAccountInfoService;

class ChannelAccountObserver
{
    public $afterCommit = true;

    public function saved(ChannelAccount $model)
    {
        $this->refreshCache($model);
    }

    public function deleted(ChannelAccount $model)
    {
        $this->refreshCache($model);
    }

    private function refreshCache(ChannelAccount $model): void
    {
        $channelIds = array_unique(array_filter([
            $model->getOriginal('channel_id'),
            $model->channel_id,
        ]));

        foreach ($channelIds as $channelId) {
            // 通道账号变更后刷新最后可用账号缓存，避免下发继续使用旧账号。
            App::make(CacheLastChannelAccountInfoService::class)->excute($channelId, true);
        }
    }
}
