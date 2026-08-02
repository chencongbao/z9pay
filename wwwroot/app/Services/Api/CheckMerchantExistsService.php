<?php

namespace App\Services\Api;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class CheckMerchantExistsService
{
    use ServiceTraits;

    public function excute($mid = 0): ?array
    {
        if (intval($mid) <= 0) {
            return null;
        }

        $merchantInfo = App::make(CacheMerchantBaseInfoService::class)->excute($mid);
        if (empty($merchantInfo)) {
            return null;
        }

        if (!empty($merchantInfo['deleted_at'])) {
            return null;
        }

        if (intval($merchantInfo['status'] ?? 0) !== 1) {
            return null;
        }

        return $merchantInfo;
    }
}
