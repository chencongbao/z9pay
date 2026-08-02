<?php

namespace App\Telegram\Commands;

class NgnCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'ngn';
    protected string $description = '查询 NGN 统计';

    protected function currencyCode(): string
    {
        return 'NGN';
    }
}
