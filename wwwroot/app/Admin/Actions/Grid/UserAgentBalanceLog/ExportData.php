<?php

namespace App\Admin\Actions\Grid\UserAgentBalanceLog;

use App\Jobs\AdminUserAgentBalanceLogDataExportJob;
use App\Admin\Renderable\UserAgentBalanceLog\HistoryExportData;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminUserAgentBalanceLogDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_USER_AGENT_BALANCE_LOGS_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_user_agent_balance_logs';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['is_agent' => 1];
    }
}
