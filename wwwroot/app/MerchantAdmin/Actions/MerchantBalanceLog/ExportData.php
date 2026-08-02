<?php

namespace App\MerchantAdmin\Actions\MerchantBalanceLog;

use App\Jobs\MerchantMerchantBalanceLogDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\MerchantAdmin\Actions\Common\AsyncExportData;
use App\MerchantAdmin\Renderable\MerchantBalanceLog\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = MerchantMerchantBalanceLogDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::MERCHANT_MERCHANT_BALANCE_LOG_EXPORT_HAS_EXIST;

    protected string $eventType = 'merchant_merchant_balance_logs';

    protected string $requestRuleKey = 'balance-logs';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }
}
