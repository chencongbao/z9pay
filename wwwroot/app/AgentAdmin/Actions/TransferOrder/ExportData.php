<?php

namespace App\AgentAdmin\Actions\TransferOrder;

use App\AgentAdmin\Actions\Common\AsyncExportData;
use App\AgentAdmin\Renderable\TransferOrder\HistoryExportData;
use App\Jobs\AgentDataExportJob;
use App\Services\Cache\CacheConstPrefixService;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AgentDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::AGENT_TRANSFER_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'agent_transfer_orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['agent_export_type' => 'transfer_orders', 'type' => 0];
    }
}
