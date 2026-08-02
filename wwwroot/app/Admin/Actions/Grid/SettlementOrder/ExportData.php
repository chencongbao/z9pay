<?php

namespace App\Admin\Actions\Grid\SettlementOrder;

use App\Jobs\AdminSettlementOrderDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;
use App\Admin\Renderable\SettlementOrder\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminSettlementOrderDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_SETTLEMENT_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_settlement_orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['type' => 1];
    }
}
