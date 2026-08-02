<?php

namespace App\Telegram\Commands;

class BrlCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'brl';
    protected string $description = '查询 BRL 统计';

    protected function currencyCode(): string
    {
        return 'BRL';
    }
}
