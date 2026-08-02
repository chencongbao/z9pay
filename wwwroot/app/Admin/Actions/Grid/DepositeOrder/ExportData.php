<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use App\Jobs\AdminDepositOrderDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;
use App\Admin\Renderable\DepositOrder\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminDepositOrderDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_DEPOSIT_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_deposit_export';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }
}
