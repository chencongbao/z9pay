<?php

namespace App\MerchantAdmin\Actions\SettlementOrder;

use App\Jobs\MerchantSettlementOrderDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\MerchantAdmin\Actions\Common\AsyncExportData;
use App\MerchantAdmin\Renderable\SettlementOrder\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = MerchantSettlementOrderDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::MERCHANT_SETTLEMENT_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'merchant_settlement_orders';

    protected string $requestRuleKey = 'settlement-orders';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function defaultParams(): array
    {
        return $this->todayDateParams();
    }

    protected function forceParams(): array
    {
        return ['type' => 1];
    }
}
