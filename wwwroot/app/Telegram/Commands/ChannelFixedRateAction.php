<?php

namespace App\Telegram\Commands;

class ChannelFixedRateAction extends ChannelRateAction
{
    protected function rateType(): int
    {
        return 1;
    }

    protected function rateField(): string
    {
        return 'fixed_rate';
    }

    protected function rateLabel(): string
    {
        return '固定成本费率';
    }

    protected function callbackType(): int
    {
        return 21;
    }

    protected function parsePrefix(): string
    {
        return '修改固定渠道费率';
    }

    protected function commandExample(): string
    {
        return '/channel_fixed_rate 1 alipay/3 alipay_uid/5';
    }

    protected function cacheKeySuffix(): string
    {
        return 'channel_fixed_rate';
    }

    protected function systemActionKey(): string
    {
        return 'channel.rate.telegram_update_fixed_rate';
    }

    protected function systemLogText(): string
    {
        return 'Telegram批量修改渠道固定成本费率';
    }

    protected function formatRateValue($value, bool $allowEmptyPlaceholder = false): string
    {
        if ($allowEmptyPlaceholder && ($value === '' || $value === null)) {
            return '-';
        }

        return bob_amount_format($value);
    }
}
