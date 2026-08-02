<?php

namespace App\Telegram\Commands;

class NprCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'npr';
    protected string $description = '查询 NPR 统计';

    protected function currencyCode(): string
    {
        return 'NPR';
    }
}
