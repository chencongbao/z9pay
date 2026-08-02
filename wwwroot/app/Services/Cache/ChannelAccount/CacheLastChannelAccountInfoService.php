<?php

namespace App\Services\Cache\ChannelAccount;

use App\Models\ChannelAccount;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class CacheLastChannelAccountInfoService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function excute($channel_id = 0, $force = false): array
    {
        $channelId = intval($channel_id);
        if ($channelId <= 0) {
            return [];
        }

        if ($force) {
            return $this->updateCache($channelId);
        }

        $key = $this->cacheKey($channelId);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return is_array($cached) ? $cached : [];
        }

        return Cache::remember($key, now()->addSeconds(self::CACHE_TTL_SECONDS), function () use ($channelId) {
            return $this->queryAccount($channelId);
        });
    }

    private function updateCache(int $channelId): array
    {
        $data = $this->queryAccount($channelId);
        Cache::put($this->cacheKey($channelId), $data, now()->addSeconds(self::CACHE_TTL_SECONDS));

        return $data;
    }

    private function queryAccount(int $channelId): array
    {
        return optional(ChannelAccount::query()
            ->where('channel_id', $channelId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->first())->toArray() ?: [];
    }

    private function cacheKey(int $channelId): string
    {
        return CacheConstPrefixService::CHANNEL_ACCOUNT_INFO . $channelId;
    }
}
