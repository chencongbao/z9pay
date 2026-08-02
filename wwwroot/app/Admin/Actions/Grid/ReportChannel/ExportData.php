<?php

namespace App\Admin\Actions\Grid\ReportChannel;

use App\Jobs\AdminReportChannelDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;
use App\Admin\Renderable\ReportChannel\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminReportChannelDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_REPORT_CHANNEL_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_report_channels';

    protected string $historyRenderableClass = HistoryExportData::class;
}
