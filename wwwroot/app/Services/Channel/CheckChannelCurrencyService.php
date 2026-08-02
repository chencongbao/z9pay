<?php

namespace App\Services\Channel;

use App\Traits\ServiceTraits;

class CheckChannelCurrencyService
{
    use ServiceTraits;

    public function excute($currency_id = 0, $currency = ''): bool
    {
        $allowedCurrencies = $this->parseCurrencies($currency);
        if (empty($allowedCurrencies)) {
            return true;
        }

        return in_array(trim((string) $currency_id), $allowedCurrencies, true);
    }

    private function parseCurrencies($currency): array
    {
        if ($currency === null || $currency === '') {
            return [];
        }

        if (is_array($currency)) {
            $items = $currency;
        } else {
            $items = explode(',', (string) $currency);
        }

        return array_values(array_unique(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $items), fn ($item) => $item !== '')));
    }
}
