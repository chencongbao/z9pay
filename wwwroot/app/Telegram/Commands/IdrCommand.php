<?php

namespace App\Telegram\Commands;

class IdrCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'idr';
    protected string $description = '查询 IDR 统计';

    protected function currencyCode(): string
    {
        return 'IDR';
    }
}
