<?php

namespace App\Admin\Actions\Grid\MerchantBalanceLog;

use App\Jobs\AdminMerchantBalanceLogDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;
use App\Admin\Renderable\MerchantBalanceLog\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminMerchantBalanceLogDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_MERCHANT_BALANCE_LOG_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_merchant_balance_logs';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }
}
