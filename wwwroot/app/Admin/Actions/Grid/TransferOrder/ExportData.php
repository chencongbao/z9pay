<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use App\Jobs\AdminTransferOrderDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;
use App\Admin\Renderable\TransferOrder\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminTransferOrderDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_TRANSFER_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_transfer_orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['type' => 0];
    }
}
