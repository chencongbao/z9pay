<?php

namespace App\Admin\Actions\Grid\FreezeOrder;

use App\Jobs\AdminFreezeOrderDataExportJob;
use App\Admin\Renderable\FreezeOrder\HistoryExportData;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminFreezeOrderDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_FREEZE_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_freeze_orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }
}
