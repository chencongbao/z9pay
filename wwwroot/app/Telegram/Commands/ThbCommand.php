<?php

namespace App\Telegram\Commands;

class ThbCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'thb';
    protected string $description = '查询 THB 统计';

    protected function currencyCode(): string
    {
        return 'THB';
    }
}
