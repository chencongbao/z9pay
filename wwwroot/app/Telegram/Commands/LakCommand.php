<?php

namespace App\Telegram\Commands;

class LakCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'lak';
    protected string $description = '查询 LAK 统计';

    protected function currencyCode(): string
    {
        return 'LAK';
    }
}
