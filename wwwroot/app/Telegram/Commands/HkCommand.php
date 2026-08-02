<?php

namespace App\Telegram\Commands;

class HkCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'hk';
    protected string $description = '查询 HK 统计';

    protected function currencyCode(): string
    {
        return 'HK';
    }
}
