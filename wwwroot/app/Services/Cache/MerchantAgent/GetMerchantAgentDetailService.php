<?php

namespace App\Services\Cache\MerchantAgent;

use App\Models\AgentUser;
use Illuminate\Support\Arr;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class GetMerchantAgentDetailService
{
    use ServiceTraits;

    private const CACHE_VERSION = 2;

    private const FIELDS = ['id', 'name', 'status', 'level', 'balance_amount', 'pid', 'username'];

    private const LEVEL_KEYS = ['one', 'two'];

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
        if ($this->isValidCache($cache)) {
            return $this->items[$id] = $cache['item'];
        }

        return $this->items[$id] = $this->refreshWithLock($id, $key);
    }

    public function forgetDescendantCaches(AgentUser $agent): void
    {
        $agent->queryDescendants()->pluck($agent->qualifyColumn('id'))->each(function ($id) {
            $id = (int) $id;
            Cache::forget($this->cacheKey($id));
            unset($this->items[$id]);
        });
    }

    private function refreshWithLock(int $id, string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($id, $key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache['item'];
                }

                return $this->refresh($id, $key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($id, $key);
        }
    }

    private function refresh(int $id, string $key): array
    {
        $model = AgentUser::query()->whereKey($id)->first(self::FIELDS);
        if (!$model) {
            Cache::put($key, ['version' => self::CACHE_VERSION, 'item' => []], now()->addMinutes(10));
            $this->items[$id] = [];

            return [];
        }

        $data = $model->toArray();
        $ancestorFields = array_map(fn (string $field) => $model->qualifyColumn($field), self::FIELDS);
        $ancestors = $model->getAncestors($ancestorFields)->sortByDesc('level')->values();
        $ancestorCacheFields = [...self::FIELDS, 'bname'];

        foreach (self::LEVEL_KEYS as $index => $levelKey) {
            $ancestor = $ancestors->get($index);
            $data[$levelKey] = $ancestor ? Arr::only($ancestor->toArray(), $ancestorCacheFields) : [];
        }

        Cache::put($key, ['version' => self::CACHE_VERSION, 'item' => $data], now()->addDays(7));
        $this->items[$id] = $data;

        return $data;
    }

    private function isValidCache($cache): bool
    {
        return is_array($cache)
            && ($cache['version'] ?? null) === self::CACHE_VERSION
            && is_array($cache['item'] ?? null);
    }

    private function cacheKey(int $id): string
    {
        return CacheConstPrefixService::MERCHANT_AGENT_DETAIL . $id;
    }
}
