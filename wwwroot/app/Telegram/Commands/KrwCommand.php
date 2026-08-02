<?php

namespace App\Telegram\Commands;

class KrwCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'krw';
    protected string $description = '查询 KRW 统计';

    protected function currencyCode(): string
    {
        return 'KRW';
    }
}
