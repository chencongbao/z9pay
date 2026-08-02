<?php

namespace App\Services\Api\V3;

use App\Models\MerchantInfo;
use App\Traits\ServiceResponseTrait;
use Illuminate\Support\Facades\Cache;

class QueryMerchantBalanceService
{
    use ServiceResponseTrait;

    public function excute($mid): array
    {
        $mid = intval($mid);
        $data = [
            'balance' => 0,
            'available_balance' => 0,
            'poll_interval' => 2,
        ];

        $model = Cache::remember('merchant:balance:' . $mid, now()->addSeconds(2), function () use ($mid) {
            return MerchantInfo::query()
                ->where('merchant_user_id', $mid)
                ->select('balance_amount', 'available_balance')
                ->first();
        });

        if ($model) {
            $data['balance'] = $model->balance_amount;
            $data['available_balance'] = $model->available_balance;
        }

        return $this->success($data);
    }
}
