<?php

namespace App\Services\Cache\Channel;

use App\Models\Channel;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class ChannelInfoByChannelIdService
{
    use ServiceTraits;

    private const REQUIRED_FIELDS = ['id', 'name', 'status', 'classname', 'appsecret', 'bname'];

    public function excute(int|string|null $channelId = 0, bool $force = false): array
    {
        $channelId = (int) $channelId;
        if ($channelId <= 0) {
            return [];
        }

        $key = CacheConstPrefixService::CHANNEL_DETAIL . $channelId;
        if ($force) {
            return $this->refresh($channelId, $key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache;
        }

        return $this->refreshWithLock($channelId, $key);
    }

    private function refreshWithLock(int $channelId, string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($channelId, $key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache;
                }

                return $this->refresh($channelId, $key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($channelId, $key);
        }
    }

    private function refresh(int $channelId, string $key): array
    {
        $channel = Channel::query()->whereKey($channelId)->first();
        $data = $channel ? $channel->toArray() : [];
        Cache::forever($key, $data);

        return $data;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache)) {
            return false;
        }

        if ($cache === []) {
            return true;
        }

        if ((int) ($cache['id'] ?? 0) <= 0) {
            return false;
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $cache)) {
                return false;
            }
        }

        return true;
    }
}
