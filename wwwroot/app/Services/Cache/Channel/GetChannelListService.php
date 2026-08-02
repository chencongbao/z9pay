<?php

namespace App\Services\Cache\Channel;

use App\Models\Channel;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class GetChannelListService
{
    use ServiceTraits;

    private const CACHE_FIELDS = ['id', 'name', 'status', 'bname'];

    public function excute(bool $force = false): array
    {
        $key = CacheConstPrefixService::CHANNEL_LIST;
        if ($force) {
            return $this->refresh($key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache;
        }

        return $this->refreshWithLock($key);
    }

    private function refreshWithLock(string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache;
                }

                return $this->refresh($key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($key);
        }
    }

    private function refresh(string $key): array
    {
        $data = Channel::query()
            ->orderByDesc('id')
            ->toBase()
            ->get(['id', 'name', 'status'])
            ->map(fn ($channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'status' => $channel->status,
                'bname' => '【#' . $channel->id . '】' . $channel->name,
            ])
            ->all();

        Cache::forever($key, $data);

        return $data;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache) || ($cache !== [] && !is_array($cache[0] ?? null))) {
            return false;
        }

        return $cache === [] || array_keys($cache[0]) === self::CACHE_FIELDS;
    }
}
