<?php

namespace App\Telegram\Commands;

class TryCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'try';
    protected string $description = '查询 TRY 统计';

    protected function currencyCode(): string
    {
        return 'TRY';
    }
}
