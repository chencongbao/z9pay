<?php

namespace App\Telegram\Commands;

class InrCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'inr';
    protected string $description = '查询 INR 统计';

    protected function currencyCode(): string
    {
        return 'INR';
    }
}
