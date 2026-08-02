<?php

namespace App\Services\Telegram;

use App\Models\MerchantInfo;
use App\Traits\TelegramTrait;

class MerchantBalanceTextService
{
    use TelegramTrait;

    public function excute(MerchantInfo $merchant, string $lang = ''): string
    {
        $text = $this->buildTelegramBalanceHeader($lang);
        $text .= $this->buildTelegramBalanceLine('商家币种', $this->merchantCurrencyName($merchant), $lang, 'merchant_currency');
        $text .= $this->buildTelegramBalanceLine('账户总额', bob_unit_format($merchant->balance_amount), $lang, 'total_balance');
        $text .= $this->buildTelegramBalanceLine('可用金额', bob_unit_format($merchant->available_balance), $lang, 'available_balance');
        $text .= $this->buildTelegramBalanceLine('冻结金额', bob_unit_format($merchant->freeze_amount), $lang, 'frozen_amount');

        return $text;
    }

    private function merchantCurrencyName(MerchantInfo $merchant): string
    {
        return (string)(optional(collect(config('default.currency'))->firstWhere('id', $merchant->currency_id))->offsetGet('name') ?: '-');
    }
}
