<?php

namespace App\Services\Cache\User;

use App\Models\User;
use Illuminate\Support\Arr;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetUserDetailService
{
    use ServiceTraits;

    private const MISSING_CACHE = ['missing' => true];

    private const LEVEL_KEYS = ['one', 'two', 'three', 'four', 'five'];

    private array $items = [];

    public function excute(int $id = 0, bool $force = false): array
    {
        if ($id <= 0) {
            return [];
        }

        if (!$force && array_key_exists($id, $this->items)) {
            return $this->items[$id];
        }

        $key = $this->cacheKey($id);
        if ($force) {
            return $this->refresh($id, $key);
        }

        $cache = Cache::get($key);
        if ($cache === self::MISSING_CACHE) {
            $this->items[$id] = [];

            return [];
        }

        if ($this->isValidCache($cache)) {
            $this->items[$id] = $cache;

            return $cache;
        }

        return $this->refresh($id, $key);
    }

    public function forgetDescendantCaches(User $user): void
    {
        $user->queryDescendants()->pluck($user->qualifyColumn('id'))->each(function ($id) {
            $id = (int) $id;
            $this->forget($id);
        });
    }

    public function forget(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        Cache::forget($this->cacheKey($id));
        unset($this->items[$id]);
    }

    private function refresh(int $id, string $key): array
    {
        $model = User::query()->whereKey($id)->withTrashed()->first(CacheConstPrefixService::CACHE_USER_FIELD);
        if (!$model || $model->trashed()) {
            Cache::put($key, self::MISSING_CACHE, now()->addSeconds(30));
            $this->items[$id] = [];

            return [];
        }

        $data = $model->toArray();
        $ancestorFields = array_map(fn (string $field) => $model->qualifyColumn($field), CacheConstPrefixService::CACHE_USER_FIELD);
        $ancestors = $model->getAncestors($ancestorFields)->sortBy('level')->values();
        $ancestorCacheFields = [...CacheConstPrefixService::CACHE_USER_FIELD, 'bname'];

        foreach (self::LEVEL_KEYS as $index => $levelKey) {
            $ancestor = $ancestors->get($index);
            $data[$levelKey] = $ancestor ? Arr::only($ancestor->toArray(), $ancestorCacheFields) : [];
        }

        Cache::forever($key, $data);
        $this->items[$id] = $data;

        return $data;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache) || !array_key_exists('bname', $cache) || !array_key_exists('deleted_at', $cache) || !empty($cache['deleted_at'])) {
            return false;
        }

        foreach (self::LEVEL_KEYS as $levelKey) {
            if (!array_key_exists($levelKey, $cache) || !is_array($cache[$levelKey]) || ($cache[$levelKey] !== [] && !array_key_exists('bname', $cache[$levelKey]))) {
                return false;
            }
        }

        return true;
    }

    private function cacheKey(int $id): string
    {
        return CacheConstPrefixService::USER_DETAIL . $id;
    }
}
