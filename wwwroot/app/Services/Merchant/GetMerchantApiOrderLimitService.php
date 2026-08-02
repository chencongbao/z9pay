<?php

namespace App\Services\Merchant;

use App\Traits\ServiceTraits;

class GetMerchantApiOrderLimitService
{
    use ServiceTraits;

    public function excute($mid = 0, $type = 1): int
    {
        $mid = intval($mid);
        $type = intval($type);
        $settings = bob_admin_setting('api_merchant_order_limit');
        if (empty($settings)) {
            return 0;
        }

        foreach (json_decode($settings, true) ?: [] as $item) {
            if (intval($item['mid'] ?? 0) !== $mid) {
                continue;
            }

            return intval($type === 2 ? ($item['transfer_order'] ?? 0) : ($item['deposit_order'] ?? 0));
        }

        return 0;
    }
}
