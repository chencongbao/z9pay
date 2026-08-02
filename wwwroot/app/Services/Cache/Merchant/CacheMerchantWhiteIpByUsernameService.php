<?php

namespace App\Services\Cache\Merchant;

use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class CacheMerchantWhiteIpByUsernameService
{
    use ServiceTraits;

    private const CACHE_FIELDS = ['login_white_ip', 'coder'];

    public function excute(string $username = '', bool $force = false): array
    {
        $username = trim($username);
        if ($username === '') {
            return [];
        }

        $key = $this->cacheKey($username);
        if ($force) {
            return $this->refresh($username, $key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache;
        }

        return $this->refreshWithLock($username, $key);
    }

    public function forget(string $username): void
    {
        $username = trim($username);
        if ($username !== '') {
            Cache::forget($this->cacheKey($username));
        }
    }

    private function refreshWithLock(string $username, string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($username, $key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache;
                }

                return $this->refresh($username, $key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($username, $key);
        }
    }

    private function refresh(string $username, string $key): array
    {
        $merchantUserTable = (new MerchantUser())->getTable();
        $merchantInfoTable = (new MerchantInfo())->getTable();
        $result = MerchantUser::query()
            ->join($merchantInfoTable, "{$merchantInfoTable}.merchant_user_id", '=', "{$merchantUserTable}.id")
            ->where("{$merchantUserTable}.username", $username)
            ->where("{$merchantUserTable}.pid", 0)
            ->whereNull("{$merchantInfoTable}.deleted_at")
            ->toBase()
            ->first(["{$merchantUserTable}.login_white_ip", "{$merchantInfoTable}.coder"]);

        if (!$result || empty($result->login_white_ip)) {
            Cache::put($key, [], now()->addMinutes(10));

            return [];
        }

        $data = [
            'login_white_ip' => bob_format_muti_data_to_array($result->login_white_ip),
            'coder' => (string) $result->coder,
        ];
        Cache::forever($key, $data);

        return $data;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache) || ($cache !== [] && array_keys($cache) !== self::CACHE_FIELDS)) {
            return false;
        }

        return true;
    }

    private function cacheKey(string $username): string
    {
        return CacheConstPrefixService::MERCHANT_WHITE_IP_BY_USERNAME . $username;
    }
}
