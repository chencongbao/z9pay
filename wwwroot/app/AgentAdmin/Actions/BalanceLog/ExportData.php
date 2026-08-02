<?php

namespace App\AgentAdmin\Actions\BalanceLog;

use App\AgentAdmin\Actions\Common\AsyncExportData;
use App\AgentAdmin\Renderable\BalanceLog\HistoryExportData;
use App\Jobs\AgentDataExportJob;
use App\Services\Cache\CacheConstPrefixService;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AgentDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::AGENT_BALANCE_LOG_EXPORT_HAS_EXIST;

    protected string $eventType = 'agent_balance_logs';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['agent_export_type' => 'balance_logs'];
    }
}
