<?php

namespace App\Services\Cache\ChannelAccount;

use App\Models\ChannelAccount;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class CacheLastChannelAccountMapService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function excute(array $channelIds = [], $force = false): array
    {
        $channelIds = array_values(array_unique(array_filter(array_map('intval', $channelIds), function (int $channelId) {
            return $channelId > 0;
        })));
        if (empty($channelIds)) {
            return [];
        }

        $keys = [];
        foreach ($channelIds as $channelId) {
            $keys[$channelId] = $this->cacheKey($channelId);
        }

        if ($force) {
            return $this->updateCache($channelIds);
        }

        $cached = Cache::many(array_values($keys));
        $data = [];
        $missChannelIds = [];

        foreach ($keys as $channelId => $key) {
            if (array_key_exists($key, $cached) && !is_null($cached[$key])) {
                $data[$channelId] = $cached[$key];
                continue;
            }

            $missChannelIds[] = $channelId;
        }

        if (!empty($missChannelIds)) {
            $data = $data + $this->updateCache($missChannelIds);
        }

        return $data;
    }

    private function updateCache(array $channelIds): array
    {
        $rows = ChannelAccount::query()
            ->whereIn('channel_id', $channelIds)
            ->where('status', 1)
            ->orderBy('channel_id')
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $channelId = (int) $row->channel_id;
            if (isset($data[$channelId])) {
                continue;
            }
            $data[$channelId] = $row->toArray();
        }

        foreach ($channelIds as $channelId) {
            $data[$channelId] = $data[$channelId] ?? [];
            Cache::put($this->cacheKey($channelId), $data[$channelId], now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $data;
    }

    private function cacheKey(int $channelId): string
    {
        return CacheConstPrefixService::CHANNEL_ACCOUNT_INFO . $channelId;
    }
}
