<?php

namespace App\Observers;

use App\Models\BankCode;
use App\Models\UserBank;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\UserBank\GetUserBankListService;

class BankCodeObserver
{
    public bool $afterCommit = true;

    public function saved(BankCode $bankCode): void
    {
        $this->refreshUserBankCache($bankCode);
    }

    public function deleted(BankCode $bankCode): void
    {
        $this->refreshUserBankCache($bankCode);
    }

    private function refreshUserBankCache(BankCode $bankCode): void
    {
        App::make(GetUserBankListService::class)->excute(true);

        UserBank::query()->withTrashed()->where('bank_id', $bankCode->id)->pluck('id')->each(function ($userBankId) {
            Cache::forget(CacheConstPrefixService::USER_BANK_DETAIL . $userBankId);
        });
    }
}
