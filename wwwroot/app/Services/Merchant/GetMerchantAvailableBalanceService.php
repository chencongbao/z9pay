<?php

namespace App\Services\Merchant;

use App\Models\MerchantInfo;
use App\Traits\ServiceTraits;

class GetMerchantAvailableBalanceService
{
    use ServiceTraits;

    public function excute($mid = 0)
    {
        $mid = (int) $mid;
        if ($mid <= 0) {
            return bob_amount_format(0);
        }

        return bob_amount_format(MerchantInfo::query()->where('merchant_user_id', $mid)->value('available_balance'));
    }
}
