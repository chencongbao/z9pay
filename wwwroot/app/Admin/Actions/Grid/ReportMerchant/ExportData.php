<?php

namespace App\Admin\Actions\Grid\ReportMerchant;

use App\Jobs\AdminReportMerchantDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;
use App\Admin\Renderable\ReportMerchant\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminReportMerchantDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_REPORT_MERCHANT_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_report_merchants';

    protected string $historyRenderableClass = HistoryExportData::class;
}
