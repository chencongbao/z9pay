<?php

namespace App\Observers;

use App\Models\UserBank;
use Illuminate\Support\Facades\App;
use App\Services\Cache\UserBank\GetUserBankListService;
use App\Services\Cache\UserBank\GetUserBankDetailService;

class UserBankObserver
{
    public bool $afterCommit = true;

    public function saved(UserBank $model): void
    {
        App::make(GetUserBankDetailService::class)->excute($model->id, true);

        if ($model->wasChanged(['card_no', 'bank_id', 'account_type', 'name', 'collection_status'])) {
            App::make(GetUserBankListService::class)->excute(true);
        }
    }

    public function deleted(UserBank $model): void
    {
        App::make(GetUserBankListService::class)->excute(true);
    }

    public function restored(UserBank $model): void
    {
        App::make(GetUserBankDetailService::class)->excute($model->id, true);
        App::make(GetUserBankListService::class)->excute(true);
    }
}
