<?php

namespace App\Admin\Actions\Grid\AgentBalanceLog;

use App\Jobs\AdminAgentBalanceLogDataExportJob;
use App\Admin\Renderable\AgentBalanceLog\HistoryExportData;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminAgentBalanceLogDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_AGENT_BALANCE_LOGS_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_agent_balance_logs';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }
}
