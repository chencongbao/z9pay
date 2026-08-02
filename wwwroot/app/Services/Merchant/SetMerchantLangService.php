<?php

namespace App\Services\Merchant;

use Illuminate\Support\Facades\App;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class SetMerchantLangService
{
    public function excute($mid = 0): void
    {
        $mid = (int) $mid;
        if ($mid <= 0) {
            return;
        }

        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute($mid);
        $currencyId = (int) ($merchant['currency_id'] ?? 0);
        if ($currencyId <= 0) {
            return;
        }

        $languages = array_column((array) config('default.currency', []), 'lang', 'id');
        $lang = $languages[$currencyId] ?? '';
        if (is_string($lang) && $lang !== '') {
            App::setLocale($lang);
        }
    }
}
