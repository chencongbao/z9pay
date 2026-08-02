<?php

namespace App\MerchantAdmin\Actions\BankCode;

use App\Jobs\MerchantBankCodeDataExportJob;
use Illuminate\Support\Facades\App;
use App\Services\Cache\CacheConstPrefixService;
use App\MerchantAdmin\Actions\Common\AsyncExportData;
use App\MerchantAdmin\Renderable\BankCode\HistoryExportData;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ExportData extends AsyncExportData
{
    protected string $jobClass = MerchantBankCodeDataExportJob::class;

    protected string $lockPrefix = CacheConstPrefixService::MERCHANT_BANK_CODE_EXPORT_HAS_EXIST;

    protected string $eventType = 'merchant_bank_codes';

    protected bool $withMerchantId = false;

    protected string $requestRuleKey = 'bank-codes';

    protected string $historyRenderableClass = HistoryExportData::class;

    protected function forceParams(): array
    {
        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute(bob_merchant_user_pid());

        return ['currency_id' => optional($merchant)->offsetGet('currency_id')];
    }
}
