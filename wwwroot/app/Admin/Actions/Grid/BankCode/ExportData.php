<?php

namespace App\Admin\Actions\Grid\BankCode;

use App\Jobs\AdminBankCodeDataExportJob;
use App\Admin\Renderable\BankCode\HistoryExportData;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminBankCodeDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_BANK_CODE_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_bank_codes';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function forceParams(): array
    {
        return ['locale' => config('app.locale')];
    }
}
