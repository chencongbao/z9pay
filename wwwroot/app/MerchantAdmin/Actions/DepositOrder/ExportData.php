<?php

namespace App\MerchantAdmin\Actions\DepositOrder;

use App\Models\DepositOrder;
use Illuminate\Support\Facades\App;
use App\Services\Common\ModelQueryService;
use App\Jobs\MerchantDepositOrderDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\MerchantAdmin\Actions\Common\AsyncExportData;
use App\MerchantAdmin\Renderable\DepositOrder\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = MerchantDepositOrderDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::MERCHANT_DEPOSIT_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'merchant_deposit_export';

    protected string $requestRuleKey = 'deposit-orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function hasExportData(array $params): ?bool
    {
        return App::make(ModelQueryService::class)->excute(new DepositOrder(), $params)->exists();
    }
}
