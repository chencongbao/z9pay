<?php

namespace App\Services\Cache\BlackContent;

use App\Models\BlackContent;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class CacheAreaService
{
    use ServiceTraits;

    public function excute(bool $force = false): array
    {
        $cacheName = CacheConstPrefixService::BLACK_CONTENT_AREA;
        if ($force) {
            return $this->refreshCache($cacheName);
        }

        $cache = Cache::get($cacheName);
        if (is_array($cache)) {
            return $cache;
        }

        try {
            return Cache::lock($cacheName . ':lock', 10)->block(3, function () use ($cacheName) {
                $cache = Cache::get($cacheName);
                if (is_array($cache)) {
                    return $cache;
                }

                return $this->refreshCache($cacheName);
            });
        } catch (LockTimeoutException $e) {
            return $this->refreshCache($cacheName);
        }
    }

    private function refreshCache(string $cacheName): array
    {
        // 地区黑名单变更后，清理已被地区规则拦截过的收银台 IP 缓存。
        Cache::forget(CacheConstPrefixService::CASHIER_USER_BLACK_IP_ADDRESS);

        $data = [];

        BlackContent::query()
            ->where('type', 3)
            ->where('status', 1)
            ->get(['content'])
            ->each(function ($item) use (&$data) {
                foreach ($this->parseArea($item->content) as $area) {
                    $data[$area] = true;
                }
            });

        $data = array_keys($data);
        Cache::forever($cacheName, $data);

        return $data;
    }

    private function parseArea($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $areas = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $result = [];
        foreach ($areas as $area) {
            $area = trim($area);
            if ($area !== '') {
                $result[$area] = true;
            }
        }

        return array_keys($result);
    }
}
