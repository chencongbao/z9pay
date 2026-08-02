<?php

namespace App\Services\Cache\Merchant;

use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class GetMerchantListInfoService
{
    use ServiceTraits;

    private const CACHE_VERSION = 2;

    private const CACHE_FIELDS = ['id', 'merchant_user_id', 'name', 'coder', 'agent_user_id', 'currency_id', 'status', 'bname'];

    private const QUERY_FIELDS = ['merchant_user_id', 'name', 'coder', 'agent_user_id', 'currency_id'];

    public function excute(bool $force = false): array
    {
        $key = CacheConstPrefixService::MERCHANT_LIST;
        if ($force) {
            return $this->refresh($key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache['items'];
        }

        return $this->refreshWithLock($key);
    }

    private function refreshWithLock(string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache['items'];
                }

                return $this->refresh($key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($key);
        }
    }

    private function refresh(string $key): array
    {
        $merchants = MerchantInfo::query()
            ->whereHas('merchant_user')
            ->orderByDesc('merchant_user_id')
            ->toBase()
            ->get(self::QUERY_FIELDS);
        $merchantIds = $merchants->pluck('merchant_user_id')->map(fn ($id) => (int) $id)->all();
        $statuses = $merchantIds === [] ? [] : MerchantUser::query()->whereIn('id', $merchantIds)->pluck('status', 'id')->all();
        $currencies = [];

        foreach (config('default.currency', []) as $currency) {
            $currencies[(int) $currency['id']] = $currency['name'];
        }

        $data = $merchants->map(function ($merchant) use ($statuses, $currencies) {
            $id = (int) $merchant->merchant_user_id;
            $currencyName = $currencies[(int) $merchant->currency_id] ?? '';

            return [
                'id' => $id,
                'merchant_user_id' => $id,
                'name' => $merchant->name,
                'coder' => $merchant->coder,
                'agent_user_id' => $merchant->agent_user_id,
                'currency_id' => $merchant->currency_id,
                'status' => $statuses[$id] ?? 0,
                'bname' => "【#{$id}】【{$currencyName}】{$merchant->name}",
            ];
        })->all();

        Cache::forever($key, [
            'version' => self::CACHE_VERSION,
            'items' => $data,
        ]);

        return $data;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache) || ($cache['version'] ?? null) !== self::CACHE_VERSION || !is_array($cache['items'] ?? null)) {
            return false;
        }

        $items = $cache['items'];

        return $items === [] || array_keys($items[0] ?? []) === self::CACHE_FIELDS;
    }
}
