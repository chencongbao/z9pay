<?php

namespace App\Services\Cache\AdminUser;

use App\Models\AdminUser;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class GetAdminUserInfoByIdService
{
    use ServiceTraits;

    public function excute($id = 0, bool $force = false): array
    {
        $id = intval($id);
        if ($id <= 0) {
            return [];
        }

        $key = CacheConstPrefixService::CACHE_ADMIN_INFO . $id;
        if ($force) {
            return $this->refreshCache($id, $key);
        }

        $cache = Cache::get($key);
        if (is_array($cache)) {
            return $cache;
        }

        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($id, $key) {
                $cache = Cache::get($key);
                if (is_array($cache)) {
                    return $cache;
                }

                return $this->refreshCache($id, $key);
            });
        } catch (LockTimeoutException $e) {
            return $this->refreshCache($id, $key);
        }
    }

    private function refreshCache(int $id, string $cacheName): array
    {
        $result = AdminUser::query()->whereKey($id)->first(['id', 'status', 'username', 'name']);
        $data = $result ? $result->toArray() : [];

        Cache::put($cacheName, $data, now()->addDays(30));

        return $data;
    }
}
