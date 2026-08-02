<?php

namespace App\Telegram\Commands;

class JpyCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'jpy';
    protected string $description = '查询 JPY 统计';

    protected function currencyCode(): string
    {
        return 'JPY';
    }
}
