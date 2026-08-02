<?php

namespace App\Services\Cache\Merchant;

use App\Models\MerchantInfo;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class CacheMerchantBaseInfoService
{
    use ServiceTraits;

    private const CACHE_VERSION = 2;

    private array $items = [];

    public function excute(int $mid = 0, bool $force = false): array
    {
        if ($mid <= 0) {
            return [];
        }

        if (!$force && array_key_exists($mid, $this->items)) {
            return $this->items[$mid];
        }

        $key = $this->cacheKey($mid);
        if ($force) {
            return $this->refresh($mid, $key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $this->items[$mid] = $cache['item'];
        }

        return $this->items[$mid] = $this->refreshWithLock($mid, $key);
    }

    public function forgetByAgentIds(array $agentIds): void
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds), fn (int $id) => $id > 0)));
        if ($agentIds === []) {
            return;
        }

        MerchantInfo::query()->withTrashed()->whereIn('agent_user_id', $agentIds)->pluck('merchant_user_id')->each(function ($mid) {
            $mid = (int) $mid;
            Cache::forget($this->cacheKey($mid));
            unset($this->items[$mid]);
        });
    }

    private function refreshWithLock(int $mid, string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($mid, $key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache['item'];
                }

                return $this->refresh($mid, $key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($mid, $key);
        }
    }

    private function refresh(int $mid, string $key): array
    {
        $result = MerchantInfo::query()->where('merchant_user_id', $mid)->with(['merchant_user' => function ($query) {
            $query->withTrashed()->select('id', 'status', 'login_white_ip', 'username', 'deleted_at');
        }])->withTrashed()->first();
        if (!$result) {
            Cache::put($key, ['version' => self::CACHE_VERSION, 'item' => []], now()->addMinutes(10));
            $this->items[$mid] = [];

            return [];
        }

        $data = $result->toArray();
        $merchantInfoDeletedAt = $data['deleted_at'] ?? null;
        $merchantUser = is_array($data['merchant_user'] ?? null) ? $data['merchant_user'] : [];
        unset($data['merchant_user']);
        $data = array_replace($data, [
            'id' => $mid,
            'status' => 0,
            'login_white_ip' => null,
            'username' => '',
        ], $merchantUser);
        $data['deleted_at'] = $merchantInfoDeletedAt ?: ($merchantUser['deleted_at'] ?? null);
        $data['update_time'] = now()->toDateTimeString();
        $data['merchant_agent1_id'] = (int) ($data['agent_user_id'] ?? 0);
        $data['merchant_agent2_id'] = 0;
        $data['merchant_agent3_id'] = 0;

        if ($data['merchant_agent1_id'] > 0) {
            $agent = App::make(GetMerchantAgentDetailService::class)->excute($data['merchant_agent1_id']);
            $data['merchant_agent2_id'] = (int) ($agent['one']['id'] ?? 0);
            $data['merchant_agent3_id'] = (int) ($agent['two']['id'] ?? 0);
        }

        Cache::put($key, ['version' => self::CACHE_VERSION, 'item' => $data], now()->addDays(7));
        $this->items[$mid] = $data;

        return $data;
    }

    private function isValidCache($cache): bool
    {
        return is_array($cache)
            && ($cache['version'] ?? null) === self::CACHE_VERSION
            && is_array($cache['item'] ?? null);
    }

    private function cacheKey(int $mid): string
    {
        return CacheConstPrefixService::CACHE_MERCHANT_BASE_INFO . $mid;
    }
}
