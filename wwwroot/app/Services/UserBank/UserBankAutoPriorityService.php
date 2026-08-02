<?php

namespace App\Services\UserBank;

use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Support\Facades\Cache;

class UserBankAutoPriorityService
{
    public function get(string $key = ''): int
    {
        return (int) Cache::get($this->cacheKey($key), 0);
    }

    public function many(array $keys): array
    {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys), fn ($key) => $key !== '')));
        if (empty($keys)) {
            return [];
        }

        $cacheKeys = [];
        foreach ($keys as $key) {
            $cacheKeys[$key] = $this->cacheKey($key);
        }

        $cached = Cache::many(array_values($cacheKeys));
        $data = [];
        foreach ($cacheKeys as $key => $cacheKey) {
            $data[$key] = (int)($cached[$cacheKey] ?? 0);
        }

        return $data;
    }

    public function set(int $value = 0, string $key = ''): void
    {
        Cache::forever($this->cacheKey($key), $value);
    }

    private function cacheKey(string $key): string
    {
        return CacheConstPrefixService::USER_BANK_AUTO_PRIORITY . $key;
    }
}
