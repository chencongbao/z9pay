<?php

namespace App\Services\Cache\Channel;

use App\Models\Channel;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class ChannelWhiteIpByClassNameService
{
    use ServiceTraits;

    public function excute(string $classname = '', bool $force = false): array
    {
        $classname = trim($classname);
        if ($classname === '') {
            return [];
        }

        $key = CacheConstPrefixService::CHANNEL_CALLBACK_WHITE_IP . strtolower($classname);
        if ($force) {
            return $this->refresh($classname, $key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache;
        }

        return $this->refreshWithLock($classname, $key);
    }

    private function refreshWithLock(string $classname, string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($classname, $key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache;
                }

                return $this->refresh($classname, $key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($classname, $key);
        }
    }

    private function refresh(string $classname, string $key): array
    {
        $callbackWhiteIp = Channel::query()->where('classname', $classname)->value('callback_white_ip');
        $data = empty($callbackWhiteIp) ? [] : bob_format_muti_data_to_array($callbackWhiteIp);
        Cache::forever($key, $data);

        return $data;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache)) {
            return false;
        }

        foreach ($cache as $ip) {
            if (!is_string($ip) || $ip === '') {
                return false;
            }
        }

        return true;
    }
}
