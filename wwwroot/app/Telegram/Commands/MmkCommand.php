<?php

namespace App\Telegram\Commands;

class MmkCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'mmk';
    protected string $description = '查询 MMK 统计';

    protected function currencyCode(): string
    {
        return 'MMK';
    }
}
