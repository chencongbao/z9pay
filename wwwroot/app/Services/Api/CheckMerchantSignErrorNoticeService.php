<?php

namespace App\Services\Api;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\SystemNotice\SystemNoticeService;

class CheckMerchantSignErrorNoticeService
{
    use ServiceTraits;

    public function excute($mid = 0): bool
    {
        if (intval($mid) <= 0) {
            return false;
        }

        return App::make(SystemNoticeService::class)->enabled('merchant_sign_error', intval($mid));
    }
}
