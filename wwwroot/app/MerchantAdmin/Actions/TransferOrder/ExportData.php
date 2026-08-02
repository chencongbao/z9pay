<?php

namespace App\MerchantAdmin\Actions\TransferOrder;

use App\Jobs\MerchantTransferOrderDataExportJob;
use App\Services\Cache\CacheConstPrefixService;
use App\MerchantAdmin\Actions\Common\AsyncExportData;
use App\MerchantAdmin\Renderable\TransferOrder\HistoryExportData;

class ExportData extends AsyncExportData
{
    protected string $jobClass = MerchantTransferOrderDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::MERCHANT_TRANSFER_ORDER_EXPORT_HAS_EXIST;

    protected string $eventType = 'merchant_transfer_orders';

    protected string $requestRuleKey = 'transfer-orders';

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
