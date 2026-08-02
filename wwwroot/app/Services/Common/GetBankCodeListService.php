<?php

namespace App\Services\Common;

use App\Models\BankCode;
use App\Traits\ServiceTraits;

class GetBankCodeListService
{
    use ServiceTraits;

    public function excute()
    {
        $currencies = collect(config('default.currency', []))->keyBy('id');

        return BankCode::query()->get(['id', 'currency_id', 'name', 'code'])->map(function ($item) use ($currencies) {
            $currencyName = optional($currencies->get($item->currency_id))->offsetGet('name');
            $item->bbname = "【#{$item->id}】【{$currencyName}】{$item->name}【{$item->code}】";

            return $item;
        })->pluck('bbname', 'id');
    }
}
