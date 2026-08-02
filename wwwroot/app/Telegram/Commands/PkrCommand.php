<?php

namespace App\Telegram\Commands;

class PkrCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'pkr';
    protected string $description = '查询 PKR 统计';

    protected function currencyCode(): string
    {
        return 'PKR';
    }
}
