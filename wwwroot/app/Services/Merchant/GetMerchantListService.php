<?php

namespace App\Services\Merchant;

use App\Models\MerchantInfo;
use App\Traits\ServiceTraits;
use Illuminate\Support\Arr;

class GetMerchantListService
{
    use ServiceTraits;

    public function excute($field = [], $select = 0)
    {
        $result = MerchantInfo::query()
            ->withWhereHas('merchant_user', function ($query) {
                $query->where('status', 1);
            })
            ->orderByDesc('merchant_user_id')
            ->get(Arr::collapse([['merchant_user_id', 'name', 'coder'], $field]));

        if ($result->isEmpty()) {
            return [];
        }

        return $select ? $result->pluck('bname', 'merchant_user_id') : $result;
    }
}
