<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use App\Jobs\AdminMerchantUserDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Actions\Grid\Common\AsyncExportData;
use App\Admin\Renderable\MerchantUser\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = AdminMerchantUserDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::ADMIN_MERCHANT_USER_EXPORT_HAS_EXIST;

    protected string $eventType = 'admin_merchant_users';

    protected string $historyRenderableClass = HistoryExportData::class;
}
