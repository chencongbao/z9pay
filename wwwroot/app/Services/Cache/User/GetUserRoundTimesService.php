<?php

namespace App\Services\Cache\User;

use App\Models\User;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetUserRoundTimesService
{
    use ServiceTraits;

    private const DEFAULT_ROUND_TIMES = 1;

    private const MAX_ROUND_TIMES = 5;

    public function excute(int $userId = 0, bool $force = false): int
    {
        if ($userId <= 0) {
            return self::DEFAULT_ROUND_TIMES;
        }

        $key = CacheConstPrefixService::USER_ROUND_TIMES . $userId;
        if ($force) {
            return $this->refresh($userId, $key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $this->normalize($cache);
        }

        return $this->refresh($userId, $key);
    }

    private function refresh(int $userId, string $key): int
    {
        $roundTimes = User::query()->whereKey($userId)->value('round_times');
        if ($roundTimes === null) {
            Cache::put($key, self::DEFAULT_ROUND_TIMES, now()->addSeconds(30));

            return self::DEFAULT_ROUND_TIMES;
        }

        $roundTimes = $this->normalize($roundTimes);
        Cache::forever($key, $roundTimes);

        return $roundTimes;
    }

    private function isValidCache($cache): bool
    {
        return is_int($cache) || (is_string($cache) && preg_match('/^-?\d+$/D', $cache) === 1);
    }

    private function normalize($roundTimes): int
    {
        return min(self::MAX_ROUND_TIMES, max(self::DEFAULT_ROUND_TIMES, (int) $roundTimes));
    }
}
