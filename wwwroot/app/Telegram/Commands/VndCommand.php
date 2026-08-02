<?php

namespace App\Telegram\Commands;

class VndCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'vnd';
    protected string $description = '查询 VND 统计';

    protected function currencyCode(): string
    {
        return 'VND';
    }
}
