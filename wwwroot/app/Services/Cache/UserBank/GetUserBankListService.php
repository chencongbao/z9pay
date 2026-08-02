<?php

namespace App\Services\Cache\UserBank;

use App\Models\UserBank;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetUserBankListService
{
    use ServiceTraits;

    public function excute($force = true): array
    {
        $key = CacheConstPrefixService::USER_BANK_LIST;
        if ($force) {
            return $this->refresh($key);
        }

        $cache = Cache::get($key);
        if (is_array($cache)) {
            return $cache;
        }

        return $this->refresh($key);
    }

    private function refresh(string $key): array
    {
        $data = UserBank::query()
            ->leftJoin('bank_codes', 'bank_codes.id', '=', 'user_banks.bank_id')
            ->orderByDesc('user_banks.collection_status')
            ->orderByDesc('user_banks.id')
            ->toBase()
            ->get(['user_banks.id', 'user_banks.name', 'user_banks.account_type', 'user_banks.card_no', 'user_banks.collection_status', 'bank_codes.name as bank_name'])
            ->map(function ($userBank) {
                $bname = UserBank::formatDisplayName((int) $userBank->id, (string) $userBank->name, (int) $userBank->account_type, $userBank->bank_name, $userBank->card_no);

                return ['id' => $userBank->id, 'text' => $bname, 'bname' => $bname, 'collection_status' => $userBank->collection_status];
            })
            ->all();

        Cache::put($key, $data, now()->addDays(30));

        return $data;
    }
}
