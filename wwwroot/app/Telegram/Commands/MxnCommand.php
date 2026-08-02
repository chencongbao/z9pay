<?php

namespace App\Telegram\Commands;

class MxnCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'mxn';
    protected string $description = '查询 MXN 统计';

    protected function currencyCode(): string
    {
        return 'MXN';
    }
}
