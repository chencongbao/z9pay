<?php

namespace App\Observers;

use App\Models\AgentUser;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class AgentUserObserver
{
    private const CACHE_FIELDS = ['name', 'status', 'level', 'balance_amount', 'pid', 'username'];

    public bool $afterCommit = true;

    public function saved(AgentUser $model): void
    {
        $detailService = App::make(GetMerchantAgentDetailService::class);
        $detailService->excute($model->id, true);

        if (!$model->wasRecentlyCreated && $model->wasChanged(self::CACHE_FIELDS)) {
            $detailService->forgetDescendantCaches($model);
        }

        if (!$model->wasRecentlyCreated && $model->wasChanged(['pid', 'level'])) {
            $agentIds = $model->queryDescendants()->pluck($model->qualifyColumn('id'))->map(fn ($id) => (int) $id)->prepend((int) $model->id)->unique()->all();
            App::make(CacheMerchantBaseInfoService::class)->forgetByAgentIds($agentIds);
        }

        if ($model->wasRecentlyCreated || $model->wasChanged(self::CACHE_FIELDS)) {
            App::make(GetMerchantAgentListService::class)->excute(true);
        }
    }

    public function deleted(AgentUser $model): void
    {
        App::make(GetMerchantAgentDetailService::class)->excute($model->id, true);
        App::make(GetMerchantAgentListService::class)->excute(true);
        App::make(CacheMerchantBaseInfoService::class)->forgetByAgentIds([(int) $model->id]);
    }

    public function restored(AgentUser $model): void
    {
        App::make(GetMerchantAgentDetailService::class)->excute($model->id, true);
        App::make(GetMerchantAgentListService::class)->excute(true);
        App::make(CacheMerchantBaseInfoService::class)->forgetByAgentIds([(int) $model->id]);
    }
}
