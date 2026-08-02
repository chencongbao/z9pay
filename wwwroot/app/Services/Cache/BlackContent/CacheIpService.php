<?php

namespace App\Services\Cache\BlackContent;

use App\Models\BlackContent;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class CacheIpService
{
    use ServiceTraits;

    public function excute(bool $force = false): array
    {
        $cacheName = CacheConstPrefixService::BLACK_CONTENT_IP;
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
        $data = [];

        BlackContent::query()
            ->where('type', 1)
            ->where('status', 1)
            ->get(['mid', 'content'])
            ->each(function ($item) use (&$data) {
                $mid = intval($item->mid);
                foreach ($this->formatIps($item->content) as $ip) {
                    $data[$mid][$ip] = true;
                }
            });

        Cache::forever($cacheName, $data);

        return $data;
    }

    private function formatIps($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $ips = preg_split('/[\r\n,，]+/', $value) ?: [];
        $result = [];
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $result[$ip] = true;
            }
        }

        return array_keys($result);
    }
}
