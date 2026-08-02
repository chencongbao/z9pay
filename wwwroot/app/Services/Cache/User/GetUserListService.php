<?php

namespace App\Services\Cache\User;

use App\Models\User;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetUserListService
{
    use ServiceTraits;

    private const CACHE_FIELDS = ['id', 'name', 'username', 'status', 'acquisition_status', 'bname'];

    public function excute(bool $force = false): array
    {
        $key = CacheConstPrefixService::USER_LIST;
        if ($force) {
            return $this->refresh($key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache;
        }

        return $this->refresh($key);
    }

    private function refresh(string $key): array
    {
        $data = User::query()
            ->where('is_agent', 0)
            ->orderByDesc('id')
            ->toBase()
            ->get(['id', 'name', 'username', 'status', 'acquisition_status'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'status' => $user->status,
                'acquisition_status' => $user->acquisition_status,
                'bname' => "【#{$user->id}】【{$user->username}】{$user->name}",
            ])
            ->all();

        Cache::forever($key, $data);

        return $data;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache) || ($cache !== [] && !is_array($cache[0] ?? null))) {
            return false;
        }

        return $cache === [] || array_keys($cache[0]) === self::CACHE_FIELDS;
    }
}
