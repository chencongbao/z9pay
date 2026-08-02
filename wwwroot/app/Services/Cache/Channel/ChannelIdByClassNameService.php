<?php

namespace App\Services\Cache\Channel;

use App\Models\Channel;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class ChannelIdByClassNameService
{
    use ServiceTraits;

    public function excute($classname = '', $force = false): int
    {
        $classname = trim(strval($classname));
        if ($classname === '') {
            return 0;
        }

        $key = CacheConstPrefixService::CHANNEL_CLASSNAME . strtolower($classname);
        if ($force) {
            return $this->refreshCache($classname, $key);
        }

        $cached = Cache::get($key);
        $cachedChannelId = $this->cachedChannelId($cached);
        if ($cachedChannelId !== null) {
            return $cachedChannelId;
        }

        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($classname, $key) {
                $cachedChannelId = $this->cachedChannelId(Cache::get($key));
                if ($cachedChannelId !== null) {
                    return $cachedChannelId;
                }

                return $this->refreshCache($classname, $key);
            });
        } catch (LockTimeoutException $e) {
            return $this->refreshCache($classname, $key);
        }
    }

    private function refreshCache(string $classname, string $key): int
    {
        $channelId = intval(optional(Channel::query()->where('classname', $classname)->first(['id']))->id);
        Cache::forever($key, $channelId);

        return $channelId;
    }

    private function cachedChannelId($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return intval($value);
        }

        return null;
    }
}
