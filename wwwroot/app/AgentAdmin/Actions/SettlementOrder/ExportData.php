<?php

namespace App\AgentAdmin\Actions\SettlementOrder;

use App\AgentAdmin\Actions\Common\AsyncExportData;
use App\AgentAdmin\Renderable\SettlementOrder\HistoryExportData;
use App\Jobs\AgentDataExportJob;
use App\Services\Cache\CacheConstPrefixService;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AgentDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::AGENT_SETTLEMENT_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'agent_settlement_orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['agent_export_type' => 'settlement_orders', 'type' => 1];
    }
}
