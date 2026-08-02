<?php

namespace App\Services\Common;

use App\Traits\ServiceTraits;

class GetPaymentDetailService
{
    use ServiceTraits;

    public function excute($payment_id = 0)
    {
        $payment = collect(config('payment', []))->firstWhere('id', intval($payment_id));
        if (empty($payment)) {
            return null;
        }

        return ($payment['name'] ?? '') . '【' . ($payment['code'] ?? '') . '】';
    }
}
