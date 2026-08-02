<?php

namespace App\Telegram\Commands;

class MyrCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'myr';
    protected string $description = '查询 MYR 统计';

    protected function currencyCode(): string
    {
        return 'MYR';
    }
}
