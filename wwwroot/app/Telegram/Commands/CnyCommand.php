<?php

namespace App\Telegram\Commands;

class CnyCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'cny';
    protected string $description = '查询 CNY 统计';

    protected function currencyCode(): string
    {
        return 'CNY';
    }
}
