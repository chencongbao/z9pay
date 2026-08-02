<?php

namespace App\AgentAdmin\Actions\DepositOrder;

use App\AgentAdmin\Actions\Common\AsyncExportData;
use App\AgentAdmin\Renderable\DepositOrder\HistoryExportData;
use App\Jobs\AgentDataExportJob;
use App\Services\Cache\CacheConstPrefixService;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AgentDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::AGENT_DEPOSIT_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'agent_deposit_orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['agent_export_type' => 'deposit_orders'];
    }
}
