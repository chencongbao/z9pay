<?php

namespace App\Services\Cache\User;

use App\Models\User;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetUserAgentListService
{
    use ServiceTraits;

    private const CACHE_FIELDS = ['id', 'name', 'username', 'pid', 'level', 'bname'];

    public function excute(bool $force = false): array
    {
        $key = CacheConstPrefixService::USER_AGENT_LIST;
        if ($force) {
            return $this->refresh($key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache;
        }

        return $this->refresh($key);
    }

    public function forget(): void
    {
        Cache::forget(CacheConstPrefixService::USER_AGENT_LIST);
    }

    private function refresh(string $key): array
    {
        $data = User::query()
            ->where('is_agent', 1)
            ->orderByDesc('id')
            ->toBase()
            ->get(['id', 'name', 'username', 'pid', 'level'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'pid' => $user->pid,
                'level' => $user->level,
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
