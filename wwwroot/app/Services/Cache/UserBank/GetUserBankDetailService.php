<?php

namespace App\Services\Cache\UserBank;

use App\Models\UserBank;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetUserBankDetailService
{
    use ServiceTraits;

    public function excute($userBankId = 0, $force = false): array
    {
        $userBankId = (int) $userBankId;
        if ($userBankId <= 0) {
            return [];
        }

        $key = CacheConstPrefixService::USER_BANK_DETAIL . $userBankId;
        if ($force) {
            return $this->refresh($userBankId, $key);
        }

        $cache = Cache::get($key);
        if (is_array($cache)) {
            return $cache;
        }

        return $this->refresh($userBankId, $key);
    }

    private function refresh(int $userBankId, string $key): array
    {
        $fields = CacheConstPrefixService::CACHE_USER_BANK_FIELD;
        $select = array_map(fn (string $field) => 'user_banks.' . $field, $fields);
        $result = UserBank::query()
            ->leftJoin('bank_codes', 'bank_codes.id', '=', 'user_banks.bank_id')
            ->withTrashed()
            ->whereKey($userBankId)
            ->first([...$select, 'bank_codes.name as bank_name']);

        if (!$result) {
            Cache::put($key, [], now()->addSeconds(30));

            return [];
        }

        $data = array_intersect_key($result->getAttributes(), array_flip($fields));
        $data['bname'] = UserBank::formatDisplayName((int) $data['id'], (string) $data['name'], (int) $data['account_type'], $result->getAttribute('bank_name'), $data['card_no']);
        Cache::put($key, $data, now()->addDays(30));

        return $data;
    }
}
