<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\App;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\User\GetUserListService;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\User\GetUserAgentListService;
use App\Services\Cache\User\GetUserRoundTimesService;

class UserObserver
{
    public bool $afterCommit = true;

    public function saved(User $model): void
    {
        $userDetailService = App::make(GetUserDetailService::class);
        $userDetailService->excute($model->id, true);

        if (!$model->wasRecentlyCreated && $model->wasChanged(CacheConstPrefixService::CACHE_USER_FIELD)) {
            $userDetailService->forgetDescendantCaches($model);
        }

        if ($this->shouldRefreshUserList($model)) {
            App::make(GetUserListService::class)->excute(true);
        }

        if ($this->shouldRefreshAgentList($model)) {
            App::make(GetUserAgentListService::class)->excute(true);
        }

        if ($model->wasChanged('round_times')) {
            app(GetUserRoundTimesService::class)->excute($model->id, true);
        }
    }

    public function deleted(User $model): void
    {
        if ((int) $model->is_agent === 1) {
            App::make(GetUserAgentListService::class)->excute(true);
        } else {
            App::make(GetUserListService::class)->excute(true);
        }
    }

    public function restored(User $model): void
    {
        if ((int) $model->is_agent === 1) {
            App::make(GetUserAgentListService::class)->excute(true);
        } else {
            App::make(GetUserListService::class)->excute(true);
        }
    }

    private function shouldRefreshUserList(User $model): bool
    {
        if ($model->wasRecentlyCreated) {
            return (int) $model->is_agent === 0;
        }

        $isUser = (int) $model->is_agent === 0 || (int) $model->getOriginal('is_agent') === 0;

        return $isUser && $model->wasChanged(['is_agent', 'name', 'username', 'status', 'acquisition_status']);
    }

    private function shouldRefreshAgentList(User $model): bool
    {
        $isAgent = (int) $model->is_agent === 1 || (int) $model->getOriginal('is_agent') === 1;

        return $isAgent && ($model->wasRecentlyCreated || $model->wasChanged(['is_agent', 'name', 'username', 'pid', 'level']));
    }
}
